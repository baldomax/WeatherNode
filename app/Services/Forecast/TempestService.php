<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TempestService implements ForecastServiceInterface
{
    private string $apiUrl = 'https://swd.weatherflow.com/swd/rest/better_forecast';

    private ?string $stationId;
    private string $token;

    public function __construct()
    {
        $this->stationId = Setting::getValue('weatherflow.station_id', '');
        $token = Setting::getValue('weatherflow.api_token', '');
        $this->token = $token !== '' ? $token : 'public';
    }

    public function isConfigured(): bool
    {
        return !empty($this->stationId);
    }

    /**
     * Fetch forecast from Tempest better_forecast API (station-scoped).
     */
    public function fetchForecast(): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Tempest forecast: station ID not configured');
            return null;
        }

        $cacheKey = "tempest_forecast_{$this->stationId}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null && isset($cached['forecast']) && count($cached['forecast']) > 0) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)->get($this->apiUrl, [
                'station_id' => $this->stationId,
                'token' => $this->token,
                'units_temp' => 'c',
                'units_wind' => 'kph',
                'units_pressure' => 'mb',
                'units_precip' => 'mm',
                'units_distance' => 'km',
            ]);

            if (!$response->successful()) {
                Log::error('Tempest forecast request failed', [
                    'status' => $response->status(),
                    'station_id' => $this->stationId,
                ]);
                return null;
            }

            $data = $response->json();
            if (empty($data['forecast'])) {
                return null;
            }

            $parsed = $this->parseForecast($data);
            if (!empty($parsed['forecast'])) {
                Cache::put($cacheKey, $parsed, now()->addMinutes(30));
            }
            return $parsed;
        } catch (\Exception $e) {
            Log::error('Tempest forecast exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse better_forecast response into normalized hourly forecast array.
     */
    private function parseForecast(array $data): array
    {
        $forecast = $data['forecast'] ?? [];
        $hourly = $forecast['hourly'] ?? [];
        $timezone = $data['timezone'] ?? 'UTC';
        $tz = new \DateTimeZone($timezone);

        $entries = [];
        foreach ($hourly as $hour) {
            $timeEpoch = $hour['time'] ?? null;
            if ($timeEpoch === null) {
                continue;
            }
            $dt = (new \DateTimeImmutable('@' . $timeEpoch))->setTimezone($tz);
            $timeIso = $dt->format('Y-m-d\TH:i:sP');

            $windKmh = isset($hour['wind_avg']) ? round((float) $hour['wind_avg'], 1) : null;

            $entries[] = [
                'time' => $timeIso,
                'temperature' => isset($hour['air_temperature']) ? (float) $hour['air_temperature'] : null,
                'humidity' => isset($hour['relative_humidity']) ? (float) $hour['relative_humidity'] : null,
                'pressure' => isset($hour['sea_level_pressure']) ? (float) $hour['sea_level_pressure'] : null,
                'wind_speed' => $windKmh,
                'wind_direction' => isset($hour['wind_direction']) ? (int) $hour['wind_direction'] : null,
                'cloud_cover' => null,
                'symbol' => $hour['icon'] ?? null,
                'precipitation_1h' => isset($hour['precip']) ? (float) $hour['precip'] : null,
                'precipitation_6h' => null,
            ];
        }

        return [
            'updated_at' => $data['current_conditions']['time'] ?? null
                ? (new \DateTimeImmutable('@' . ($data['current_conditions']['time'])))->format(\DateTimeInterface::ATOM)
                : now()->toIso8601String(),
            'forecast' => $entries,
        ];
    }

    public function getHourlyForecast(int $hours = 48): array
    {
        $data = $this->fetchForecast();
        if (!$data || empty($data['forecast'])) {
            return [];
        }
        return array_slice($data['forecast'], 0, $hours);
    }

    public function getDailyForecast(int $days = 7): array
    {
        $hourly = $this->getHourlyForecast(24 * $days);
        $daily = [];
        foreach ($hourly as $hour) {
            $date = substr($hour['time'], 0, 10);
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'temp_high' => $hour['temperature'],
                    'temp_low' => $hour['temperature'],
                    'temps' => [],
                    'symbols' => [],
                    'precipitation' => 0,
                    'wind_speeds' => [],
                    'wind_directions' => [],
                ];
            }
            $daily[$date]['temps'][] = $hour['temperature'];
            $daily[$date]['temp_high'] = max($daily[$date]['temp_high'], $hour['temperature'] ?? -999);
            $daily[$date]['temp_low'] = min($daily[$date]['temp_low'], $hour['temperature'] ?? 999);
            if (!empty($hour['symbol'])) {
                $daily[$date]['symbols'][] = $hour['symbol'];
            }
            $daily[$date]['precipitation'] += $hour['precipitation_1h'] ?? 0;
            if (isset($hour['wind_speed']) && $hour['wind_speed'] !== null) {
                $daily[$date]['wind_speeds'][] = $hour['wind_speed'];
            }
            if (isset($hour['wind_direction']) && $hour['wind_direction'] !== null) {
                $daily[$date]['wind_directions'][] = $hour['wind_direction'];
            }
        }

        $result = [];
        foreach ($daily as $day) {
            $day['temp_avg'] = !empty($day['temps']) ? array_sum($day['temps']) / count($day['temps']) : null;
            $day['symbol'] = !empty($day['symbols']) ? $this->dominantSymbol($day['symbols']) : null;
            $day['wind_speed'] = !empty($day['wind_speeds']) ? array_sum($day['wind_speeds']) / count($day['wind_speeds']) : null;
            $day['wind_direction'] = null;
            if (!empty($day['wind_directions'])) {
                $sinSum = $cosSum = 0;
                foreach ($day['wind_directions'] as $dir) {
                    $rad = deg2rad($dir);
                    $sinSum += sin($rad);
                    $cosSum += cos($rad);
                }
                $avgRad = atan2($sinSum / count($day['wind_directions']), $cosSum / count($day['wind_directions']));
                $day['wind_direction'] = (int) round(rad2deg($avgRad));
                if ($day['wind_direction'] < 0) {
                    $day['wind_direction'] += 360;
                }
            }
            unset($day['temps'], $day['symbols'], $day['wind_speeds'], $day['wind_directions']);
            $result[] = $day;
        }
        return array_slice($result, 0, $days);
    }

    private function dominantSymbol(array $symbols): ?string
    {
        $counts = array_count_values(array_filter($symbols));
        if (empty($counts)) {
            return null;
        }
        arsort($counts);
        return (string) array_key_first($counts);
    }
}
