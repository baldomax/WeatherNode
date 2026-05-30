<?php

namespace App\Services\Weather\Sources;

use App\Models\Setting;
use App\Services\Weather\Normalization\UnitConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class WundergroundAdapter implements WeatherSourceAdapter
{
    public function key(): string
    {
        return 'wu';
    }

    public function fetch(): ?array
    {
        $apiKey = Setting::getValue('wunderground.api_key', '');
        $stationId = Setting::getValue('wunderground.station_id', '');

        if ($apiKey === '' || $stationId === '') {
            return null;
        }

        $units = $this->resolveUnits();

        try {
            $response = Http::timeout(10)->get('https://api.weather.com/v2/pws/observations/current', [
                'stationId' => $stationId,
                'format' => 'json',
                'units' => $units,
                'numericPrecision' => 'decimal',
                'apiKey' => $apiKey,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $payload = $response->json();
        } catch (\Exception $e) {
            return null;
        }

        $obs = $payload['observations'][0] ?? null;
        if (!$obs || !is_array($obs)) {
            return null;
        }

        $data = $obs['metric'] ?? $obs['imperial'] ?? [];
        $usingImperial = isset($obs['imperial']) && ($units === 'e' || !isset($obs['metric']));

        $recordedAt = null;
        if (!empty($obs['epoch'])) {
            $recordedAt = Carbon::createFromTimestamp((int) $obs['epoch']);
        }

        $reading = [
            'recorded_at' => $recordedAt,
            'temperature' => $this->convertTemp($data['temp'] ?? null, $usingImperial),
            'humidity' => $obs['humidity'] ?? $data['humidity'] ?? null,
            'dew_point' => $this->convertTemp($data['dewpt'] ?? null, $usingImperial),
            'heat_index' => $this->convertTemp($data['heatIndex'] ?? null, $usingImperial),
            'wind_chill' => $this->convertTemp($data['windChill'] ?? null, $usingImperial),
            'pressure_rel' => $this->convertPressure($data['pressure'] ?? null, $usingImperial),
            'wind_speed' => $this->convertWind($data['windSpeed'] ?? null, $usingImperial),
            'wind_gust' => $this->convertWind($data['windGust'] ?? null, $usingImperial),
            'wind_direction' => $obs['winddir'] ?? null,
            'rain_rate' => $this->convertRain($data['precipRate'] ?? null, $usingImperial),
            'rain_daily' => $this->convertRain($data['precipTotal'] ?? null, $usingImperial),
            'uv_index' => $obs['uv'] ?? null,
            'solar_radiation' => $obs['solarRadiation'] ?? null,
        ];

        return array_filter($reading, static fn ($value) => $value !== null);
    }

    private function resolveUnits(): string
    {
        $system = Setting::getValue('display.unit_system', 'metric');

        if ($system === 'imperial') {
            return 'e';
        }

        if ($system === 'uk') {
            return 'h';
        }

        return 'm';
    }

    private function convertTemp(?float $value, bool $imperial): ?float
    {
        if ($value === null) {
            return null;
        }

        return $imperial ? UnitConverter::fahrenheitToCelsius($value, 1) : $value;
    }

    private function convertPressure(?float $value, bool $imperial): ?float
    {
        if ($value === null) {
            return null;
        }

        return $imperial ? UnitConverter::inHgToHpa($value, 1) : $value;
    }

    private function convertWind(?float $value, bool $imperial): ?float
    {
        if ($value === null) {
            return null;
        }

        return $imperial ? UnitConverter::mphToKmh($value, 1) : $value;
    }

    private function convertRain(?float $value, bool $imperial): ?float
    {
        if ($value === null) {
            return null;
        }

        return $imperial ? UnitConverter::inchesToMm($value, 2) : $value;
    }
}
