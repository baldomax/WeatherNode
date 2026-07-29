<?php

namespace App\Services\Weather\LocalFiles;

use App\Services\Weather\Normalization\UnitConverter;
use Carbon\Carbon;

class ClientrawParser
{
    public function parse(string $filePath): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        return $this->parseContent($content);
    }

    public function parseContent(string $content): ?array
    {
        $parts = preg_split('/\s+/', trim($content));
        if (!$parts || count($parts) < 142) {
            return null;
        }

        $recordedAt = $this->parseTimestamp($parts) ?? now();

        $data = [
            'recorded_at' => $recordedAt,
            'temperature' => $this->getFloat($parts, 4),
            'humidity' => $this->getFloat($parts, 5),
            'dew_point' => $this->getFloat($parts, 72),
            'heat_index' => $this->getFloat($parts, 112),
            'wind_chill' => $this->getFloat($parts, 44),
            'wet_bulb' => $this->getFloat($parts, 159),
            'temperature_indoor' => $this->getFloat($parts, 12),
            'humidity_indoor' => $this->getFloat($parts, 13),
            'indoor_temperature' => $this->getFloat($parts, 12),
            'indoor_humidity' => $this->getFloat($parts, 13),
            'pressure_rel' => $this->getFloat($parts, 6),
            'wind_speed' => UnitConverter::knotsToKmh($this->getFloat($parts, 2)),
            'wind_gust' => UnitConverter::knotsToKmh($this->getFloat($parts, 133)),
            'wind_direction' => $this->getInt($parts, 3),
            'wind_speed_avg_10m' => UnitConverter::knotsToKmh($this->getFloat($parts, 158)),
            'wind_direction_avg_10m' => $this->getInt($parts, 117),
            'wind_gust_max_daily' => UnitConverter::knotsToKmh($this->getFloat($parts, 71)),
            'rain_rate' => $this->getFloat($parts, 10) !== null ? $this->getFloat($parts, 10) * 60 : null,
            'rain_daily' => $this->getFloat($parts, 7),
            'rain_monthly' => $this->getFloat($parts, 8),
            'rain_yearly' => $this->getFloat($parts, 9),
            'uv_index' => $this->getFloat($parts, 79),
            'solar_radiation' => $this->getFloat($parts, 127),
            // Weather Display clientraw field 696 = Sunshine Hours (present on longer files).
            'solar_hours' => $this->getFloat($parts, 696),
            'temp_1' => $this->getFloat($parts, 20),
            'temp_2' => $this->getFloat($parts, 21),
            'temp_3' => $this->getFloat($parts, 22),
            'humidity_1' => $this->getFloat($parts, 26),
            'humidity_2' => $this->getFloat($parts, 27),
            'humidity_3' => $this->getFloat($parts, 28),
        ];

        $soilTemp = $this->getFloat($parts, 14);
        if ($soilTemp !== null && $soilTemp != 0.0) {
            $data['soil_temp_1'] = $soilTemp;
        }

        $soilMoisture = $this->getFloat($parts, 157);
        if ($soilMoisture !== null) {
            $data['soil_moisture_1'] = $soilMoisture;
        }

        $leafWetness = $this->getFloat($parts, 156);
        if ($leafWetness !== null) {
            $data['leaf_wetness_1'] = $leafWetness;
        }

        if ($data['solar_radiation'] !== null) {
            $data['lux'] = (int) round($data['solar_radiation'] * 126.7);
        }

        return array_filter($data, static fn ($value) => $value !== null);
    }

    private function parseTimestamp(array $parts): ?Carbon
    {
        $hour = $this->getInt($parts, 29);
        $minute = $this->getInt($parts, 30);
        $second = $this->getInt($parts, 31);
        $month = $this->getInt($parts, 36);
        $day = $this->getInt($parts, 35);
        $year = $this->getInt($parts, 141);

        if ($hour === null || $minute === null || $second === null || $month === null || $day === null || $year === null) {
            return null;
        }

        return Carbon::create($year, $month, $day, $hour, $minute, $second);
    }

    private function getFloat(array $parts, int $index): ?float
    {
        if (!array_key_exists($index, $parts)) {
            return null;
        }

        $value = trim((string) $parts[$index]);
        if ($value === '' || $value === '---' || strtolower($value) === 'n/a') {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    private function getInt(array $parts, int $index): ?int
    {
        $value = $this->getFloat($parts, $index);
        return $value === null ? null : (int) round($value);
    }
}
