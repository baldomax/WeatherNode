<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeatherReading;
use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\User;
use App\Services\Weather\SensorTrackerService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        $lastReading = WeatherReading::latest('recorded_at')->first();
        
        $stats = [
            'total_readings' => WeatherReading::count(),
            'daily_summaries' => DailySummary::count(),
            'users' => User::count(),
            'last_reading' => $lastReading?->recorded_at,
            'database_size' => $this->getDatabaseSize(),
        ];

        $recentReadings = WeatherReading::latest('recorded_at')
            ->take(10)
            ->get();

        // Battery status from most recent reading
        $batteryStatus = $this->parseBatteryStatus($lastReading?->battery_status);
        
        // Every sensor known from the tracking window, with its current state,
        // so one that has gone quiet stays visible instead of disappearing.
        $sensorStates = $this->getSensorStates();

        // Station info
        $stationInfo = [
            'type' => $lastReading?->station_type,
            'model' => $lastReading?->station_model,
            'runtime_hours' => $lastReading?->station_runtime ? round($lastReading->station_runtime / 3600, 1) : null,
            'freq' => $lastReading?->station_freq,
        ];

        // Telemetry status
        $telemetryEnabled = Setting::getValue('telemetry.enabled', false);
        $telemetryLastUpdated = Setting::getValue('telemetry.last_updated', '');
        $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
        $telemetryData = $telemetryService->collectStationData();

        return view('admin.dashboard', compact(
            'stats',
            'recentReadings',
            'batteryStatus',
            'sensorStates',
            'stationInfo',
            'telemetryEnabled',
            'telemetryLastUpdated',
            'telemetryData'
        ));
    }

    /**
     * Parse battery status into human-readable format
     */
    private function parseBatteryStatus(mixed $batteries): array
    {
        if (!$batteries) {
            return [];
        }
        
        // Handle JSON string (from database)
        if (is_string($batteries)) {
            $batteries = json_decode($batteries, true);
            if (!is_array($batteries)) {
                return [];
            }
        }

        $status = [];
        
        // Battery name mappings for better display (English keys; translated below)
        $names = [
            'wh26batt' => ['name' => 'Temperature/Humidity Sensor (WH26)', 'type' => 'voltage'],
            'wh40batt' => ['name' => 'Rain Sensor (WH40)', 'type' => 'voltage'],
            'wh57batt' => ['name' => 'Lightning Sensor (WH57)', 'type' => 'level'],
            'wh65batt' => ['name' => 'Outdoor Sensor (WH65)', 'type' => 'voltage'],
            'wh68batt' => ['name' => 'Solar/Wind Sensor (WH68)', 'type' => 'voltage'],
            'wh80batt' => ['name' => 'Ultrasonic Wind Sensor (WH80)', 'type' => 'voltage'],
            'batt1' => ['name' => 'Sensor 1', 'type' => 'voltage'],
            'batt2' => ['name' => 'Sensor 2', 'type' => 'voltage'],
            'batt3' => ['name' => 'Sensor 3', 'type' => 'voltage'],
            'batt4' => ['name' => 'Sensor 4', 'type' => 'voltage'],
            'batt5' => ['name' => 'Sensor 5', 'type' => 'voltage'],
            'batt6' => ['name' => 'Sensor 6', 'type' => 'voltage'],
            'batt7' => ['name' => 'Sensor 7', 'type' => 'voltage'],
            'batt8' => ['name' => 'Sensor 8', 'type' => 'voltage'],
            'soilbatt1' => ['name' => 'Soil Sensor 1', 'type' => 'voltage'],
            'soilbatt2' => ['name' => 'Soil Sensor 2', 'type' => 'voltage'],
            'soilbatt3' => ['name' => 'Soil Sensor 3', 'type' => 'voltage'],
            'soilbatt4' => ['name' => 'Soil Sensor 4', 'type' => 'voltage'],
            'pm25batt1' => ['name' => 'PM2.5 Sensor 1', 'type' => 'level'],
            'pm25batt2' => ['name' => 'PM2.5 Sensor 2', 'type' => 'level'],
            'pm25batt3' => ['name' => 'PM2.5 Sensor 3', 'type' => 'level'],
            'pm25batt4' => ['name' => 'PM2.5 Sensor 4', 'type' => 'level'],
            'leakbatt1' => ['name' => 'Leak Sensor 1', 'type' => 'level'],
            'leakbatt2' => ['name' => 'Leak Sensor 2', 'type' => 'level'],
            'leakbatt3' => ['name' => 'Leak Sensor 3', 'type' => 'level'],
            'leakbatt4' => ['name' => 'Leak Sensor 4', 'type' => 'level'],
            'co2_batt' => ['name' => 'CO2 Sensor', 'type' => 'level'],
            'leafbatt1' => ['name' => 'Leaf Wetness Sensor 1', 'type' => 'voltage'],
            'leafbatt2' => ['name' => 'Leaf Wetness Sensor 2', 'type' => 'voltage'],
        ];

        foreach ($batteries as $key => $value) {
            $info = $names[$key] ?? ['name' => $key, 'type' => 'voltage'];
            
            // Determine battery state
            // For voltage type: 0 = OK, 1 = Low
            // For level type (WH57, PM25): 0-5 scale where 5 is full
            if ($info['type'] === 'voltage') {
                $state = $value == 0 ? 'good' : 'low';
                $percentage = $value == 0 ? 100 : 20;
                $display = $value == 0 ? __('OK') : __('Low');
            } else {
                // Level type (0-5)
                $percentage = min(100, ($value / 5) * 100);
                if ($value >= 4) {
                    $state = 'good';
                    $display = __('Good');
                } elseif ($value >= 2) {
                    $state = 'medium';
                    $display = __('Moderate');
                } else {
                    $state = 'low';
                    $display = __('Low');
                }
            }

            $status[] = [
                'key' => $key,
                'name' => __($info['name']),
                'type' => $info['type'],
                'value' => $value,
                'state' => $state,
                'percentage' => round($percentage),
                'display' => $display,
            ];
        }

        return $status;
    }

    /**
     * Sensor states for display, served from the cache the scheduled health
     * check refreshes. Scanning the reading window here would cost seconds.
     */
    private function getSensorStates(): array
    {
        $trackDays = max(1, min(30, (int) Setting::getValue('sensor_health.track_days', 7)));
        $failMinutes = max(15, min(10080, (int) Setting::getValue('sensor_health.fail_minutes', 30)));

        return app(SensorTrackerService::class)->getCachedSensorStates($trackDays, $failMinutes);
    }

    /**
     * Get database size in MB.
     */
    private function getDatabaseSize(): ?float
    {
        try {
            if (config('database.default') === 'mysql') {
                $result = DB::select("
                    SELECT SUM(data_length + index_length) / 1024 / 1024 AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [config('database.connections.mysql.database')]);
                return round($result[0]->size ?? 0, 2);
            } elseif (config('database.default') === 'sqlite') {
                $path = config('database.connections.sqlite.database');
                if (file_exists($path)) {
                    return round(filesize($path) / 1024 / 1024, 2);
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        return null;
    }
}
