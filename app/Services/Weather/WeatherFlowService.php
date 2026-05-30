<?php

namespace App\Services\Weather;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherFlowService
{
    private ?string $stationId;
    private string $apiUrl = 'https://swd.weatherflow.com/swd/rest/';

    public function __construct()
    {
        $this->stationId = Setting::getValue('weatherflow.station_id', '');
    }

    /**
     * Token for Tempest API (station owner token or 'public' for public stations).
     */
    private function getToken(): string
    {
        $token = Setting::getValue('weatherflow.api_token', '');
        return ($token !== null && $token !== '') ? (string) $token : 'public';
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->stationId);
    }

    /**
     * Get station metadata
     */
    public function getStationMetadata(): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('WeatherFlow station ID not configured');
            return null;
        }

        $cacheKey = "weatherflow_meta_{$this->stationId}";

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $response = Http::timeout(10)->get($this->apiUrl . 'stations/' . $this->stationId, [
                    'token' => $this->getToken(),
                ]);

                if (!$response->successful()) {
                    Log::error('WeatherFlow metadata request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();
                // API returns { "status", "locations": [ station, ... ] }
                $locations = $data['locations'] ?? [];
                if (empty($locations)) {
                    return null;
                }
                $stationIdInt = (int) $this->stationId;
                foreach ($locations as $loc) {
                    if ((int) ($loc['station_id'] ?? 0) === $stationIdInt) {
                        return $loc;
                    }
                }
                return $locations[0];
            } catch (\Exception $e) {
                Log::error('WeatherFlow metadata exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get current observation
     */
    public function getCurrentConditions(): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('WeatherFlow station ID not configured');
            return null;
        }

        $cacheKey = "weatherflow_current_{$this->stationId}";

        return Cache::remember($cacheKey, 60, function () {
            try {
                $response = Http::timeout(10)->get(
                    $this->apiUrl . 'observations/station/' . $this->stationId,
                    ['token' => $this->getToken()]
                );

                if (!$response->successful()) {
                    Log::error('WeatherFlow observation request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $this->parseObservation($response->json());

            } catch (\Exception $e) {
                Log::error('WeatherFlow observation exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse WeatherFlow observation response.
     * API returns top-level station_name, public_name, latitude, longitude, elevation and obs[].
     */
    private function parseObservation(array $data): array
    {
        $station = $data['station'] ?? [];
        $obs = $data['obs'][0] ?? [];
        // Support API shape with station info at top level
        $name = $station['name'] ?? $data['station_name'] ?? null;
        $publicName = $station['public_name'] ?? $data['public_name'] ?? null;
        $lat = $station['latitude'] ?? $data['latitude'] ?? null;
        $lon = $station['longitude'] ?? $data['longitude'] ?? null;
        $elevation = $station['station_meta']['elevation'] ?? $data['elevation'] ?? null;

        $stationPressure = $obs['station_pressure'] ?? $obs['barometric_pressure'] ?? null;

        return [
            'station' => [
                'name' => $name,
                'public_name' => $publicName,
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => $elevation,
            ],
            'timestamp' => isset($obs['timestamp']) 
                ? date('Y-m-d H:i:s', $obs['timestamp'])
                : null,
            'outdoor' => [
                'temperature' => $obs['air_temperature'] ?? null,
                'feels_like' => $obs['feels_like'] ?? null,
                'humidity' => $obs['relative_humidity'] ?? null,
                'dew_point' => $obs['dew_point'] ?? null,
                'wet_bulb' => $obs['wet_bulb_temperature'] ?? null,
            ],
            'wind' => [
                'speed' => $this->msToKmh($obs['wind_avg'] ?? null),
                'gust' => $this->msToKmh($obs['wind_gust'] ?? null),
                'lull' => $this->msToKmh($obs['wind_lull'] ?? null),
                'direction' => $obs['wind_direction'] ?? null,
                'beaufort' => $this->msToBeaufort($obs['wind_avg'] ?? 0),
            ],
            'pressure' => [
                'station' => $stationPressure,
                'sea_level' => $obs['sea_level_pressure'] ?? null,
                'trend' => $obs['pressure_trend'] ?? null,
            ],
            'rain' => [
                'rate' => $obs['precip'] ?? null, // mm/min
                'rate_hourly' => ($obs['precip'] ?? 0) * 60, // mm/h
                'daily' => $obs['precip_accum_local_day'] ?? null,
                'yesterday' => $obs['precip_accum_local_yesterday'] ?? null,
                'minutes_checked' => $obs['precip_minutes_local_day'] ?? null,
            ],
            'solar' => [
                'uv_index' => $obs['uv'] ?? null,
                'solar_radiation' => $obs['solar_radiation'] ?? null,
                'brightness' => $obs['brightness'] ?? null,
            ],
            'lightning' => [
                'strike_count_1h' => $obs['lightning_strike_count_last_1hr'] ?? null,
                'strike_count_3h' => $obs['lightning_strike_count_last_3hr'] ?? null,
                'last_distance' => $obs['lightning_strike_last_distance'] ?? null,
                'last_epoch' => $obs['lightning_strike_last_epoch'] ?? null,
            ],
            'battery' => $obs['battery'] ?? null,
        ];
    }

    /**
     * Get forecast
     */
    public function getForecast(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = "weatherflow_forecast_{$this->stationId}";

        return Cache::remember($cacheKey, 1800, function () {
            try {
                $response = Http::timeout(10)->get(
                    $this->apiUrl . 'better_forecast',
                    [
                        'station_id' => $this->stationId,
                        'token' => $this->getToken(),
                    ]
                );

                if (!$response->successful()) {
                    return null;
                }

                return $this->parseForecast($response->json());

            } catch (\Exception $e) {
                Log::error('WeatherFlow forecast exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse WeatherFlow forecast response
     */
    private function parseForecast(array $data): array
    {
        $forecast = $data['forecast'] ?? [];
        $daily = $forecast['daily'] ?? [];
        $hourly = $forecast['hourly'] ?? [];

        $result = [
            'current_conditions' => $forecast['current_conditions'] ?? null,
            'daily' => [],
            'hourly' => [],
        ];

        foreach ($daily as $day) {
            $result['daily'][] = [
                'date' => date('Y-m-d', $day['day_start_local'] ?? time()),
                'day_num' => $day['day_num'] ?? null,
                'temp_high' => $day['air_temp_high'] ?? null,
                'temp_low' => $day['air_temp_low'] ?? null,
                'precipitation_probability' => $day['precip_probability'] ?? null,
                'precipitation_icon' => $day['precip_icon'] ?? null,
                'conditions' => $day['conditions'] ?? null,
                'icon' => $day['icon'] ?? null,
                'sunrise' => $day['sunrise'] ?? null,
                'sunset' => $day['sunset'] ?? null,
            ];
        }

        foreach (array_slice($hourly, 0, 24) as $hour) {
            $result['hourly'][] = [
                'time' => isset($hour['time']) ? date('H:i', $hour['time']) : null,
                'temperature' => $hour['air_temperature'] ?? null,
                'feels_like' => $hour['feels_like'] ?? null,
                'humidity' => $hour['relative_humidity'] ?? null,
                'precipitation_probability' => $hour['precip_probability'] ?? null,
                'conditions' => $hour['conditions'] ?? null,
                'icon' => $hour['icon'] ?? null,
                'wind_avg' => $this->msToKmh($hour['wind_avg'] ?? null),
                'wind_direction' => $hour['wind_direction'] ?? null,
            ];
        }

        return $result;
    }

    // Unit conversion helpers
    private function msToKmh(?float $ms): ?float
    {
        return $ms !== null ? round($ms * 3.6, 1) : null;
    }

    private function msToBeaufort(float $ms): int
    {
        $scales = [0.3, 1.5, 3.3, 5.5, 8.0, 10.8, 13.9, 17.2, 20.7, 24.5, 28.4, 32.6];
        
        foreach ($scales as $i => $limit) {
            if ($ms < $limit) {
                return $i;
            }
        }
        
        return 12;
    }
}
