<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class YrNoService implements ForecastServiceInterface
{
    private float $latitude;
    private float $longitude;
    private string $baseUrl = 'https://api.met.no/weatherapi/locationforecast/2.0/';

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
    }

    /**
     * Fetch weather forecast from Yr.no
     */
    public function fetchForecast(): ?array
    {
        $cacheKey = "yrno_forecast_{$this->latitude}_{$this->longitude}";
        
        return Cache::remember($cacheKey, 1800, function () {
            try {
                $http = Http::timeout(15);
                if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                    $http = $http->withoutVerifying();
                }

                $response = $http
                    ->withHeaders([
                        'User-Agent' => UserAgentService::forExternalApi(),
                    ])->get($this->baseUrl . 'complete', [
                        'lat' => round($this->latitude, 4),
                        'lon' => round($this->longitude, 4),
                    ]);

                if ($response->successful()) {
                    return $this->parseForecast($response->json());
                }

                Log::error('Yr.no API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::error('Yr.no API exception', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * Parse Yr.no API response into simplified structure
     */
    private function parseForecast(array $data): array
    {
        $timeseries = $data['properties']['timeseries'] ?? [];
        $forecast = [];

        foreach ($timeseries as $entry) {
            $time = $entry['time'];
            $instant = $entry['data']['instant']['details'] ?? [];
            $next1h = $entry['data']['next_1_hours'] ?? null;
            $next6h = $entry['data']['next_6_hours'] ?? null;
            $next12h = $entry['data']['next_12_hours'] ?? null;

            $windSpeedMs = $instant['wind_speed'] ?? null;
            $windSpeedKmh = $windSpeedMs !== null ? $windSpeedMs * 3.6 : null;

            $forecast[] = [
                'time' => $time,
                'temperature' => $instant['air_temperature'] ?? null,
                'humidity' => $instant['relative_humidity'] ?? null,
                'pressure' => $instant['air_pressure_at_sea_level'] ?? null,
                // Convert m/s -> km/h for consistent metric base in UI.
                'wind_speed' => $windSpeedKmh,
                'wind_direction' => $instant['wind_from_direction'] ?? null,
                'cloud_cover' => $instant['cloud_area_fraction'] ?? null,
                'symbol' => $next1h['summary']['symbol_code'] ?? $next6h['summary']['symbol_code'] ?? null,
                'precipitation_1h' => $next1h['details']['precipitation_amount'] ?? null,
                'precipitation_6h' => $next6h['details']['precipitation_amount'] ?? null,
            ];
        }

        return [
            'updated_at' => $data['properties']['meta']['updated_at'] ?? now()->toIso8601String(),
            'forecast' => $forecast,
        ];
    }

    /**
     * Get hourly forecast for next 48 hours
     */
    public function getHourlyForecast(int $hours = 48): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        return array_slice($data['forecast'], 0, $hours);
    }

    /**
     * Get daily forecast summary
     */
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
            if ($hour['temperature'] > $daily[$date]['temp_high']) {
                $daily[$date]['temp_high'] = $hour['temperature'];
            }
            if ($hour['temperature'] < $daily[$date]['temp_low']) {
                $daily[$date]['temp_low'] = $hour['temperature'];
            }
            
            if ($hour['symbol']) {
                $daily[$date]['symbols'][] = $hour['symbol'];
            }
            
            $daily[$date]['precipitation'] += $hour['precipitation_1h'] ?? $hour['precipitation_6h'] ?? 0;
            
            // Collect wind data
            if (isset($hour['wind_speed']) && $hour['wind_speed'] !== null) {
                $daily[$date]['wind_speeds'][] = $hour['wind_speed'];
            }
            if (isset($hour['wind_direction']) && $hour['wind_direction'] !== null) {
                $daily[$date]['wind_directions'][] = $hour['wind_direction'];
            }
        }

        // Calculate dominant symbol and average wind for each day
        foreach ($daily as &$day) {
            $day['temp_avg'] = count($day['temps']) > 0 ? array_sum($day['temps']) / count($day['temps']) : null;
            $day['symbol'] = $this->getDominantSymbol($day['symbols']);
            
            // Calculate average wind speed
            $day['wind_speed'] = !empty($day['wind_speeds']) 
                ? array_sum($day['wind_speeds']) / count($day['wind_speeds']) 
                : null;
            
            // Calculate average wind direction (circular mean)
            $day['wind_direction'] = null;
            if (!empty($day['wind_directions'])) {
                $sinSum = 0;
                $cosSum = 0;
                foreach ($day['wind_directions'] as $dir) {
                    $rad = deg2rad($dir);
                    $sinSum += sin($rad);
                    $cosSum += cos($rad);
                }
                $avgRad = atan2($sinSum / count($day['wind_directions']), $cosSum / count($day['wind_directions']));
                $day['wind_direction'] = round(rad2deg($avgRad));
                // Normalize to 0-360
                if ($day['wind_direction'] < 0) {
                    $day['wind_direction'] += 360;
                }
            }
            
            unset($day['temps'], $day['symbols'], $day['wind_speeds'], $day['wind_directions']);
        }

        return array_values($daily);
    }

    /**
     * Get most common weather symbol for a day
     */
    private function getDominantSymbol(array $symbols): ?string
    {
        if (empty($symbols)) {
            return null;
        }

        $counts = array_count_values($symbols);
        arsort($counts);
        return array_key_first($counts);
    }
}
