<?php

namespace App\Services\Weather;

use App\Models\WeatherReading;
use Carbon\Carbon;

/**
 * Tracks which sensors have been active over a time window and detects
 * when one stops reporting (e.g. empty battery, lost contact).
 * Works with Ecowitt and other stations that store battery_status and
 * per-channel data (temp_1..8, soil_1..8, pm25_ch1..4, etc.).
 */
class SensorTrackerService
{
    /**
     * Sensor IDs we derive from each reading (data channels + battery keys).
     * Used to build "active" set and "last seen" per sensor.
     */
    public static function getSensorIdsFromReading(WeatherReading $reading): array
    {
        $ids = [];

        // Battery-backed sensors (Ecowitt keys: wh26batt, batt1, soilbatt1, etc.)
        $battery = $reading->battery_status;
        if (is_array($battery)) {
            foreach (array_keys($battery) as $key) {
                $ids[] = $key;
            }
        }

        // Extra temperature sensors (temp_1 .. temp_8)
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"temp_{$i}"} !== null) {
                $ids[] = "temp_{$i}";
            }
        }

        // Extra humidity (humidity_1 .. humidity_8)
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"humidity_{$i}"} !== null) {
                $ids[] = "humidity_{$i}";
            }
        }

        // Soil channels (soil_1 .. soil_8) – present if moisture or temp reported
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"soil_moisture_{$i}"} !== null || $reading->{"soil_temp_{$i}"} !== null) {
                $ids[] = "soil_{$i}";
            }
        }

        // PM2.5 channels
        for ($i = 1; $i <= 4; $i++) {
            if ($reading->{"pm25_ch{$i}"} !== null) {
                $ids[] = "pm25_ch{$i}";
            }
        }

        // Leak channels
        for ($i = 1; $i <= 4; $i++) {
            if ($reading->{"leak_ch{$i}"} !== null) {
                $ids[] = "leak_{$i}";
            }
        }

        // Lightning
        if ($reading->lightning_distance !== null || $reading->lightning_count_daily !== null) {
            $ids[] = 'lightning';
        }

        // CO2
        if ($reading->co2 !== null) {
            $ids[] = 'co2';
        }

        return array_values(array_unique($ids));
    }

    /**
     * Get all sensor IDs that have been seen in the last $trackDays days.
     * Uses chunking to avoid loading all readings into memory.
     */
    public function getActiveSensorIds(int $trackDays): array
    {
        $since = now()->subDays($trackDays);
        $active = [];

        WeatherReading::where('recorded_at', '>=', $since)
            ->orderBy('recorded_at', 'desc')
            ->chunk(500, function ($readings) use (&$active) {
                foreach ($readings as $r) {
                    foreach (self::getSensorIdsFromReading($r) as $id) {
                        $active[$id] = true;
                    }
                }
            });

        return array_keys($active);
    }

    /**
     * For each active sensor, get the latest recorded_at from readings that contain it.
     * Returns array keyed by sensor_id => Carbon (last_seen).
     * Uses chunking to avoid loading all readings into memory.
     */
    public function getLastSeenForSensors(array $sensorIds, int $withinDays): array
    {
        if (empty($sensorIds)) {
            return [];
        }

        $since = now()->subDays($withinDays);
        $lastSeen = array_fill_keys($sensorIds, null);

        WeatherReading::where('recorded_at', '>=', $since)
            ->orderBy('recorded_at', 'asc')
            ->chunk(500, function ($readings) use (&$lastSeen) {
                foreach ($readings as $r) {
                    $ids = self::getSensorIdsFromReading($r);
                    $at = $r->recorded_at instanceof \DateTimeInterface
                        ? Carbon::instance($r->recorded_at)
                        : Carbon::parse($r->recorded_at);
                    foreach ($ids as $id) {
                        if (isset($lastSeen[$id])) {
                            if ($lastSeen[$id] === null || $at->isAfter($lastSeen[$id])) {
                                $lastSeen[$id] = $at;
                            }
                        }
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
        $activeIds = $this->getActiveSensorIds($trackDays);
        if (empty($activeIds)) {
            return [];
        }

        $lastSeen = $this->getLastSeenForSensors($activeIds, $trackDays);
        $threshold = now()->subMinutes($failMinutes);
        $failed = [];

        foreach ($lastSeen as $sensorId => $at) {
            if ($at === null) {
                continue;
            }
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
     * Human-friendly label for a sensor ID (for alerts and UI).
     */
    public static function sensorIdToLabel(string $id): string
    {
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
        // Battery keys: wh26batt, batt1, soilbatt1, etc.
        if (str_ends_with($id, 'batt')) {
            return __('Battery :id', ['id' => $id]);
        }
        return $id;
    }
}
