<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Alerts\AlertAggregatorService;
use App\Services\Lightning\BoltekService;
use App\Services\Weather\AerisService;
use App\Services\Weather\WeatherLinkService;
use App\Services\Weather\AmbientWeatherService;
use App\Services\Weather\WeatherFlowService;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DataController extends Controller
{
    /**
     * Get weather alerts from configured source
     */
    public function alerts(): JsonResponse
    {
        $enabled = (bool) Setting::getValue('alerts.enabled', true);

        if (!$enabled) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'alerts' => [],
            ]);
        }

        $source  = Setting::getValue('alerts.source', 'europe');
        $alerts  = array_values(app(AlertAggregatorService::class)->getAll());
        $highest = $this->getHighestSeverityAlert($alerts);

        return response()->json([
            'success'          => true,
            'enabled'          => true,
            'has_warnings'     => !empty($alerts),
            'highest_severity' => $highest ? [
                'level' => $highest['severity'] ?? null,
                'color' => $highest['severity_color'] ?? null,
            ] : null,
            'alerts' => $alerts,
            'source' => $source,
            'region' => Setting::getValue('alerts.region_code', 'NL011'),
        ]);
    }

    /**
     * Get earthquake data from Seismic Portal
     */
    public function earthquakes(): JsonResponse
    {
        $enabled = (bool) Setting::getValue('earthquakes.enabled', true);

        if (!$enabled) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'earthquakes' => [],
            ]);
        }

        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $cacheKey = "earthquakes_{$latitude}_{$longitude}";
        $cachedEarthquakes = Cache::get($cacheKey, []);
        $earthquakes = is_array($cachedEarthquakes) ? $cachedEarthquakes : [];

        usort($earthquakes, function ($a, $b) {
            return strtotime($b['time'] ?? $b['date_time'] ?? '0') <=> strtotime($a['time'] ?? $a['date_time'] ?? '0');
        });
        $stats = $this->getEarthquakeStatistics($earthquakes);
        $earthquakes = array_slice($earthquakes, 0, 15);

        return response()->json([
            'success' => true,
            'enabled' => true,
            'earthquakes' => $earthquakes,
            'statistics' => $stats,
            'config' => [
                'radius_km' => (int) Setting::getValue('earthquakes.radius_km', 500),
                'min_magnitude' => (float) Setting::getValue('earthquakes.min_magnitude', 2.5),
            ],
            'source' => 'seismicportal.eu',
        ]);
    }

    /**
     * Get Luftdaten/Sensor.Community data
     */
    public function luftdaten(): JsonResponse
    {
        $enabled = (bool) Setting::getValue('luftdaten.enabled', true);

        if (!$enabled) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'data' => null,
            ]);
        }

        $sensorId = Setting::getValue('luftdaten.sensor_id', '69616');
        $data = !empty($sensorId) ? Cache::get("luftdaten_{$sensorId}") : null;

        return response()->json([
            'success' => $data !== null,
            'enabled' => true,
            'data' => $data,
            'sensor_id' => $sensorId,
            'source' => 'sensor.community',
        ]);
    }

    /**
     * Get PurpleAir data
     */
    public function purpleair(): JsonResponse
    {
        $enabled = (bool) Setting::getValue('purpleair.enabled', false);

        if (!$enabled) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'data' => null,
            ]);
        }

        $data = $this->getCachedPurpleAirData();

        return response()->json([
            'success' => $data !== null,
            'enabled' => true,
            'data' => $data,
            'source' => 'purpleair',
        ]);
    }

    /**
     * Get combined air quality from all sources
     * Reads from cache (populated by weather:poll-external)
     */
    public function airQuality(): JsonResponse
    {
        $sources = [];

        // WAQI - read from cache
        if ((bool) Setting::getValue('waqi.enabled', true)) {
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            $stationMode = Setting::getValue('waqi.station_mode', 'auto');
            $stationId = Setting::getValue('waqi.station_id', '');

            $waqiCacheKey = ($stationMode === 'manual' && !empty($stationId))
                ? "waqi_station_{$stationId}"
                : "waqi_{$latitude}_{$longitude}";
            $waqiData = Cache::get($waqiCacheKey);
            if ($waqiData) {
                $sources['waqi'] = $waqiData;
            }
        }

        // Luftdaten - read from cache
        if (Setting::getValue('luftdaten.enabled', '1') === '1') {
            $luftdatenSensorId = Setting::getValue('luftdaten.sensor_id', '');
            $luftdatenData = $luftdatenSensorId ? Cache::get("luftdaten_{$luftdatenSensorId}") : null;
            if ($luftdatenData) {
                $sources['luftdaten'] = $luftdatenData;
            }
        }

        // PurpleAir - read from cache only
        if ((bool) Setting::getValue('purpleair.enabled', false)) {
            $purpleairData = $this->getCachedPurpleAirData();
            if ($purpleairData) {
                $sources['purpleair'] = $purpleairData;
            }
        }

        // Determine primary AQI
        $primaryAqi = null;
        if (isset($sources['waqi']['aqi'])) {
            $primaryAqi = [
                'value' => $sources['waqi']['aqi'],
                'category' => $sources['waqi']['category'],
                'source' => 'waqi',
            ];
        } elseif (isset($sources['purpleair']['aqi'])) {
            $primaryAqi = [
                'value' => $sources['purpleair']['aqi']['value'],
                'category' => [
                    'level' => $sources['purpleair']['aqi']['level'],
                    'color' => $sources['purpleair']['aqi']['color'],
                ],
                'source' => 'purpleair',
            ];
        }

        return response()->json([
            'success' => !empty($sources),
            'primary_aqi' => $primaryAqi,
            'sources' => $sources,
        ]);
    }

    /**
     * Get lightning data
     */
    public function lightning(BoltekService $boltek): JsonResponse
    {
        $enabled = (bool) Setting::getValue('lightning.enabled', true);
        $source = Setting::getValue('lightning.source', 'ecowitt');
        
        if (!$enabled) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'data' => null,
            ]);
        }

        $data = null;
        
        if ($source === 'boltek' && $boltek->isConfigured()) {
            $data = $boltek->fetchLightningData();
        }

        // For ecowitt source, lightning data comes from the main weather reading
        // which is handled in the WeatherController

        return response()->json([
            'success' => $data !== null || $source === 'ecowitt',
            'enabled' => true,
            'source' => $source,
            'data' => $data,
        ]);
    }

    /**
     * Get all external data combined
     */
    public function external(): JsonResponse
    {
        $data = [];

        // Alerts
        if ((bool) Setting::getValue('alerts.enabled', true)) {
            $alerts = app(AlertAggregatorService::class)->getAll();
            $data['alerts'] = [
                'has_warnings' => !empty($alerts),
                'highest'      => $this->getHighestSeverityAlert($alerts),
            ];
        }

        // Earthquakes
        if ((bool) Setting::getValue('earthquakes.enabled', true)) {
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            $cacheKey = "earthquakes_{$latitude}_{$longitude}";
            $cachedEarthquakes = Cache::get($cacheKey, []);
            $earthquakes = is_array($cachedEarthquakes) ? $cachedEarthquakes : [];
            usort($earthquakes, function ($a, $b) {
                return strtotime($b['time'] ?? $b['date_time'] ?? '0') <=> strtotime($a['time'] ?? $a['date_time'] ?? '0');
            });

            $data['earthquakes'] = [
                'recent' => array_slice($earthquakes, 0, 5),
                'stats' => $this->getEarthquakeStatistics($earthquakes),
            ];
        }

        // Luftdaten
        if ((bool) Setting::getValue('luftdaten.enabled', true)) {
            $sensorId = Setting::getValue('luftdaten.sensor_id', '');
            $data['luftdaten'] = $sensorId ? Cache::get("luftdaten_{$sensorId}") : null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get Aeris Weather data
     */
    public function aeris(AerisService $service): JsonResponse
    {
        $enabled = (bool) Setting::getValue('aeris.enabled', false);
        
        if (!$enabled || !$service->isConfigured()) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'configured' => $service->isConfigured(),
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'configured' => true,
            'current' => $service->getCurrentConditions(),
            'alerts' => $service->getAlerts(),
            'forecast' => $service->getForecast(),
            'lightning' => $service->getLightning(),
            'source' => 'aeris',
        ]);
    }

    /**
     * Get Davis WeatherLink data
     */
    public function weatherlink(WeatherLinkService $service): JsonResponse
    {
        $enabled = (bool) Setting::getValue('weatherlink.enabled', false);
        
        if (!$enabled || !$service->isConfigured()) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'configured' => $service->isConfigured(),
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'configured' => true,
            'current' => $service->getCurrentConditions(),
            'source' => 'weatherlink',
        ]);
    }

    /**
     * Get Ambient Weather data
     */
    public function ambient(AmbientWeatherService $service): JsonResponse
    {
        $enabled = (bool) Setting::getValue('ambient.enabled', false);
        
        if (!$enabled || !$service->isConfigured()) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'configured' => $service->isConfigured(),
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'configured' => true,
            'devices' => $service->getDevices(),
            'current' => $service->getCurrentConditions(),
            'source' => 'ambient',
        ]);
    }

    /**
     * Get WeatherFlow data
     */
    public function weatherflow(WeatherFlowService $service): JsonResponse
    {
        $enabled = (bool) Setting::getValue('weatherflow.enabled', false);

        if (!$enabled || !$service->isConfigured()) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'configured' => $service->isConfigured(),
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'configured' => true,
            'metadata' => $service->getStationMetadata(),
            'current' => $service->getCurrentConditions(),
            'forecast' => $service->getForecast(),
            'source' => 'weatherflow',
        ]);
    }

    /**
     * Get highest-severity alert from cached alert list.
     */
    private function getHighestSeverityAlert(array $alerts): ?array
    {
        if (empty($alerts)) {
            return null;
        }

        usort($alerts, function ($a, $b) {
            return (int) ($b['severity'] ?? 0) <=> (int) ($a['severity'] ?? 0);
        });

        return $alerts[0] ?? null;
    }

    /**
     * Build earthquake statistics from cached earthquake entries.
     */
    private function getEarthquakeStatistics(array $earthquakes): array
    {
        if (empty($earthquakes)) {
            return [
                'total' => 0,
                'last_24h' => 0,
                'last_week' => 0,
                'max_magnitude' => null,
                'avg_magnitude' => null,
            ];
        }

        $now = time();
        $last24h = 0;
        $lastWeek = 0;
        $magnitudes = [];

        foreach ($earthquakes as $eq) {
            $timestamp = $eq['timestamp'] ?? null;
            if (!$timestamp) {
                $timestamp = strtotime($eq['time'] ?? $eq['date_time'] ?? '0') ?: null;
            }

            if ($timestamp) {
                if (($now - $timestamp) < 86400) {
                    $last24h++;
                }
                if (($now - $timestamp) < 604800) {
                    $lastWeek++;
                }
            }

            if (isset($eq['magnitude']) && is_numeric($eq['magnitude'])) {
                $magnitudes[] = (float) $eq['magnitude'];
            }
        }

        return [
            'total' => count($earthquakes),
            'last_24h' => $last24h,
            'last_week' => $lastWeek,
            'max_magnitude' => !empty($magnitudes) ? max($magnitudes) : null,
            'avg_magnitude' => !empty($magnitudes) ? round(array_sum($magnitudes) / count($magnitudes), 1) : null,
        ];
    }

    /**
     * PurpleAir cache helper with legacy and sensor-based key support.
     */
    private function getCachedPurpleAirData(): ?array
    {
        $cached = Cache::get('purpleair_air_quality');
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $sensorId = (int) Setting::getValue('purpleair.sensor_id', 0);
        if ($sensorId <= 0) {
            return null;
        }

        $sensorCached = Cache::get("purpleair_{$sensorId}");
        return is_array($sensorCached) ? $sensorCached : null;
    }
}
