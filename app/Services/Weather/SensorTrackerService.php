<?php

namespace App\Services\Weather;

use App\Models\WeatherReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks which sensors have been active over a time window and detects
 * when one stops reporting (e.g. empty battery, lost contact).
 *
 * Detection works off the normalized WeatherReading columns, so it covers every
 * source that writes readings (Ecowitt, WeatherLink, WeatherFlow, Ambient, Aeris,
 * local files, ...). Ecowitt battery keys are tracked too where present, but they
 * are not required: a station that goes quiet stops filling its columns, and that
 * alone is enough to notice.
 */
class SensorTrackerService
{
    /**
     * Primary sensor ID => normalized columns that prove it reported.
     * A sensor counts as present when at least one of its columns is non-null.
     */
    private const PRIMARY_SENSORS = [
        'outdoor_temp_humidity' => ['temperature', 'humidity'],
        'indoor_temp_humidity' => ['temperature_indoor', 'humidity_indoor', 'indoor_temperature', 'indoor_humidity'],
        'barometer' => ['pressure_rel', 'pressure_abs'],
        'wind' => ['wind_speed', 'wind_gust', 'wind_direction', 'wind_speed_avg_10m', 'wind_direction_avg_10m'],
        'rain' => ['rain_rate', 'rain_hourly', 'rain_daily', 'rain_weekly', 'rain_monthly', 'rain_yearly', 'rain_event', 'rain_total'],
        'solar' => ['solar_radiation', 'lux'],
        'uv' => ['uv_index'],
        'water_temp' => ['water_temperature'],
        'pm10' => ['pm10', 'pm10_avg_24h'],
        'soil' => ['soil_temperature', 'soil_moisture'],
    ];

    /** Cache key holding the precomputed sensor states for the admin UI. */
    public const STATES_CACHE_KEY = 'sensor_states';

    /** Kept longer than the 5-minute refresh so a page load rarely recomputes. */
    private const STATES_CACHE_MINUTES = 30;

    /** Minutes of silence before a sensor is shown as stale (below the alert threshold). */
    private const STALE_MINUTES = 15;

    /**
     * Sensor IDs we derive from each reading (data channels + battery keys).
     * Accepts a model or a raw row, so callers can skip Eloquent hydration.
     */
    public static function getSensorIdsFromReading(object $reading): array
    {
        $ids = [];

        foreach (self::PRIMARY_SENSORS as $sensorId => $columns) {
            foreach ($columns as $column) {
                if (($reading->{$column} ?? null) !== null) {
                    $ids[] = $sensorId;
                    break;
                }
            }
        }

        // Battery-backed sensors (Ecowitt keys: wh26batt, batt1, soilbatt1, etc.)
        $battery = $reading->battery_status ?? null;
        if (is_string($battery)) {
            $battery = json_decode($battery, true);
        }
        if (is_array($battery)) {
            foreach (array_keys($battery) as $key) {
                $ids[] = $key;
            }
        }

        // Extra temperature sensors (temp_1 .. temp_8)
        for ($i = 1; $i <= 8; $i++) {
            if (($reading->{"temp_{$i}"} ?? null) !== null) {
                $ids[] = "temp_{$i}";
            }
        }

        // Extra humidity (humidity_1 .. humidity_8)
        for ($i = 1; $i <= 8; $i++) {
            if (($reading->{"humidity_{$i}"} ?? null) !== null) {
                $ids[] = "humidity_{$i}";
            }
        }

        // Soil channels (soil_1 .. soil_8) – present if moisture or temp reported
        for ($i = 1; $i <= 8; $i++) {
            if (($reading->{"soil_moisture_{$i}"} ?? null) !== null || ($reading->{"soil_temp_{$i}"} ?? null) !== null) {
                $ids[] = "soil_{$i}";
            }
        }

        // PM2.5 channels
        for ($i = 1; $i <= 4; $i++) {
            if (($reading->{"pm25_ch{$i}"} ?? null) !== null) {
                $ids[] = "pm25_ch{$i}";
            }
        }

        // Leak channels
        for ($i = 1; $i <= 4; $i++) {
            if (($reading->{"leak_ch{$i}"} ?? null) !== null) {
                $ids[] = "leak_{$i}";
            }
        }

        // Leaf wetness channels
        for ($i = 1; $i <= 8; $i++) {
            if (($reading->{"leaf_wetness_{$i}"} ?? null) !== null) {
                $ids[] = "leaf_{$i}";
            }
        }

        // Lightning
        if (($reading->lightning_distance ?? null) !== null || ($reading->lightning_count_daily ?? null) !== null) {
            $ids[] = 'lightning';
        }

        // CO2
        if (($reading->co2 ?? null) !== null) {
            $ids[] = 'co2';
        }

        return array_values(array_unique($ids));
    }

    /**
     * Latest recorded_at per sensor across the tracking window, in one pass.
     *
     * Rows are read as plain records rather than Eloquent models: this window is
     * one row per minute, so hydration dominated the cost.
     *
     * @return array<string, Carbon>
     */
    public function getLastSeenForAllSensors(int $trackDays): array
    {
        $since = now()->subDays($trackDays);
        $lastSeen = [];

        WeatherReading::query()
            ->toBase()
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at', 'asc')
            ->chunk(1000, function ($readings) use (&$lastSeen) {
                foreach ($readings as $r) {
                    $at = $r->recorded_at instanceof \DateTimeInterface
                        ? Carbon::instance($r->recorded_at)
                        : Carbon::parse($r->recorded_at);

                    // Ascending order, so a later row always wins.
                    foreach (self::getSensorIdsFromReading($r) as $id) {
                        $lastSeen[$id] = $at;
                    }
                }
            });

        return $lastSeen;
    }

    /**
     * Sensors that were active in the tracking window but have not been seen
     * in the last $failMinutes minutes. Returns list of [ 'id' => string, 'last_seen' => Carbon ].
     */
    public function getFailedSensors(int $trackDays, int $failMinutes): array
    {
        $threshold = now()->subMinutes($failMinutes);
        $failed = [];

        foreach ($this->getLastSeenForAllSensors($trackDays) as $sensorId => $at) {
            if ($at->isBefore($threshold)) {
                $failed[] = [
                    'id' => $sensorId,
                    'last_seen' => $at,
                ];
            }
        }

        return $failed;
    }

    /**
     * Every known sensor with its current state: 'ok' when it reported recently,
     * 'failed' once past the alert threshold, 'stale' in between. Failed first.
     *
     * @return array<int, array{id: string, label: string, state: string, last_seen: Carbon, minutes_ago: int}>
     */
    public function getSensorStates(int $trackDays, int $failMinutes): array
    {
        $states = [];

        foreach ($this->getLastSeenForAllSensors($trackDays) as $sensorId => $at) {
            $minutesAgo = (int) round(abs(now()->diffInMinutes($at)));

            if ($minutesAgo >= $failMinutes) {
                $state = 'failed';
            } elseif ($minutesAgo >= self::STALE_MINUTES) {
                $state = 'stale';
            } else {
                $state = 'ok';
            }

            $states[] = [
                'id' => $sensorId,
                'label' => self::sensorIdToLabel($sensorId),
                'state' => $state,
                'last_seen' => $at,
                'minutes_ago' => $minutesAgo,
            ];
        }

        usort($states, function ($a, $b) {
            $rank = ['failed' => 0, 'stale' => 1, 'ok' => 2];

            return [$rank[$a['state']], $a['label']] <=> [$rank[$b['state']], $b['label']];
        });

        return $states;
    }

    /**
     * Recompute and store the states. Called from the scheduled health check so
     * that page loads never pay for the scan.
     */
    public function refreshSensorStates(int $trackDays, int $failMinutes): array
    {
        $states = $this->getSensorStates($trackDays, $failMinutes);
        Cache::put(self::STATES_CACHE_KEY, $states, now()->addMinutes(self::STATES_CACHE_MINUTES));

        return $states;
    }

    /**
     * States for display. Falls back to computing them once if the scheduler
     * has not run recently, rather than showing nothing.
     */
    public function getCachedSensorStates(int $trackDays, int $failMinutes): array
    {
        $cached = Cache::get(self::STATES_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        return $this->refreshSensorStates($trackDays, $failMinutes);
    }

    /**
     * Human-friendly label for a sensor ID (for alerts and UI).
     */
    public static function sensorIdToLabel(string $id): string
    {
        $primary = [
            'outdoor_temp_humidity' => 'Outdoor temp/humidity',
            'indoor_temp_humidity' => 'Indoor temp/humidity',
            'barometer' => 'Barometer',
            'wind' => 'Wind sensor',
            'rain' => 'Rain gauge',
            'solar' => 'Solar sensor',
            'uv' => 'UV sensor',
            'water_temp' => 'Water temperature',
            'pm10' => 'PM10 sensor',
            'soil' => 'Soil sensor',
        ];
        if (isset($primary[$id])) {
            return __($primary[$id]);
        }

        if (preg_match('/^leaf_(\d+)$/', $id, $m)) {
            return __('Leaf wetness :n', ['n' => $m[1]]);
        }
        if (preg_match('/^temp_(\d+)$/', $id, $m)) {
            return __('Extra temp :n', ['n' => $m[1]]);
        }
        if (preg_match('/^humidity_(\d+)$/', $id, $m)) {
            return __('Humidity :n', ['n' => $m[1]]);
        }
        if (preg_match('/^soil_(\d+)$/', $id, $m)) {
            return __('Soil sensor :n', ['n' => $m[1]]);
        }
        if (preg_match('/^pm25_ch(\d+)$/', $id, $m)) {
            return __('PM2.5 ch:n', ['n' => $m[1]]);
        }
        if (preg_match('/^leak_(\d+)$/', $id, $m)) {
            return __('Leak :n', ['n' => $m[1]]);
        }
        if ($id === 'lightning') {
            return __('Lightning sensor');
        }
        if ($id === 'co2') {
            return __('CO2 sensor');
        }
        // Battery keys: wh26batt, batt1, soilbatt1, pm25batt1, leakbatt1, leafbatt1, ...
        if (preg_match('/^batt(\d+)$/', $id, $m)) {
            return __('Battery sensor :n', ['n' => $m[1]]);
        }
        if (preg_match('/^(soil|pm25|leak|leaf)batt(\d+)$/', $id, $m)) {
            $kinds = [
                'soil' => 'Battery soil sensor :n',
                'pm25' => 'Battery PM2.5 sensor :n',
                'leak' => 'Battery leak sensor :n',
                'leaf' => 'Battery leaf sensor :n',
            ];
            return __($kinds[$m[1]], ['n' => $m[2]]);
        }
        if (str_ends_with($id, 'batt')) {
            return __('Battery :id', ['id' => $id]);
        }
        return $id;
    }
}
