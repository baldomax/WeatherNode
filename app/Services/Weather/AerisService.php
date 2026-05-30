<?php

namespace App\Services\Weather;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class AerisService
{
    private ?string $accessId;
    private ?string $secretKey;
    private float $latitude;
    private float $longitude;
    private string $apiUrl = 'https://api.aerisapi.com/';

    public function __construct()
    {
        $this->accessId = $this->getDecryptedValue('aeris.access_id');
        $this->secretKey = $this->getDecryptedValue('aeris.secret_key');
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
    }

    /**
     * Get decrypted value from settings
     */
    private function getDecryptedValue(string $key): ?string
    {
        $encrypted = Setting::getValue($key, '');
        
        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            return $encrypted;
        }
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessId) && !empty($this->secretKey);
    }

    /**
     * Make API request to Aeris
     */
    private function makeRequest(string $endpoint, array $params = []): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Aeris Weather API not configured');
            return null;
        }

        $params['client_id'] = $this->accessId;
        $params['client_secret'] = $this->secretKey;

        try {
            $response = Http::timeout(10)->get($this->apiUrl . $endpoint, $params);

            if (!$response->successful()) {
                Log::error('Aeris API request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            
            if (!($data['success'] ?? false)) {
                Log::error('Aeris API error', [
                    'error' => $data['error'] ?? 'Unknown error',
                ]);
                return null;
            }

            return $data['response'] ?? null;

        } catch (\Exception $e) {
            Log::error('Aeris API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get current conditions
     */
    public function getCurrentConditions(): ?array
    {
        $cacheKey = "aeris_conditions_{$this->latitude}_{$this->longitude}";

        return Cache::remember($cacheKey, 300, function () {
            $response = $this->makeRequest("conditions/{$this->latitude},{$this->longitude}");
            
            if (!$response || empty($response[0])) {
                return null;
            }

            $ob = $response[0]['ob'] ?? [];
            $place = $response[0]['place'] ?? [];

            return [
                'temperature' => $ob['tempC'] ?? null,
                'feels_like' => $ob['feelslikeC'] ?? null,
                'dew_point' => $ob['dewpointC'] ?? null,
                'humidity' => $ob['humidity'] ?? null,
                'pressure' => $ob['pressureMB'] ?? null,
                'wind_speed' => $ob['windSpeedKPH'] ?? null,
                'wind_gust' => $ob['windGustKPH'] ?? null,
                'wind_direction' => $ob['windDirDEG'] ?? null,
                'wind_direction_text' => $ob['windDir'] ?? null,
                'visibility' => $ob['visibilityKM'] ?? null,
                'weather' => $ob['weather'] ?? null,
                'weather_short' => $ob['weatherShort'] ?? null,
                'weather_code' => $ob['weatherCoded'] ?? null,
                'cloud_cover' => $ob['cloudsCoded'] ?? null,
                'uv_index' => $ob['uvi'] ?? null,
                'solar_radiation' => $ob['solradWM2'] ?? null,
                'precipitation' => $ob['precipMM'] ?? null,
                'snow_depth' => $ob['snowDepthCM'] ?? null,
                'location' => [
                    'name' => $place['name'] ?? null,
                    'state' => $place['state'] ?? null,
                    'country' => $place['country'] ?? null,
                ],
                'timestamp' => $ob['timestamp'] ?? null,
            ];
        });
    }

    /**
     * Get weather alerts
     */
    public function getAlerts(): ?array
    {
        $cacheKey = "aeris_alerts_{$this->latitude}_{$this->longitude}";

        return Cache::remember($cacheKey, 300, function () {
            $response = $this->makeRequest("alerts/{$this->latitude},{$this->longitude}");
            
            if (!$response) {
                return [];
            }

            $alerts = [];
            foreach ($response as $alert) {
                $details = $alert['details'] ?? [];
                $timestamps = $alert['timestamps'] ?? [];
                
                $alerts[] = [
                    'id' => $alert['id'] ?? null,
                    'type' => $details['type'] ?? null,
                    'name' => $details['name'] ?? null,
                    'body' => $details['body'] ?? null,
                    'body_full' => $details['bodyFull'] ?? null,
                    'color' => $details['color'] ?? null,
                    'priority' => $alert['priority'] ?? null,
                    'issued' => isset($timestamps['issued']) 
                        ? date('Y-m-d H:i:s', $timestamps['issued']) 
                        : null,
                    'expires' => isset($timestamps['expires']) 
                        ? date('Y-m-d H:i:s', $timestamps['expires']) 
                        : null,
                ];
            }

            return $alerts;
        });
    }

    /**
     * Get forecast
     */
    public function getForecast(int $days = 7): ?array
    {
        $cacheKey = "aeris_forecast_{$this->latitude}_{$this->longitude}_{$days}";

        return Cache::remember($cacheKey, 1800, function () use ($days) {
            $response = $this->makeRequest("forecasts/{$this->latitude},{$this->longitude}", [
                'filter' => 'day',
                'limit' => $days,
            ]);
            
            if (!$response || empty($response[0]['periods'])) {
                return null;
            }

            $forecast = [];
            foreach ($response[0]['periods'] as $period) {
                $forecast[] = [
                    'date' => date('Y-m-d', $period['timestamp'] ?? time()),
                    'weekday' => $period['weekday'] ?? null,
                    'temp_high' => $period['maxTempC'] ?? null,
                    'temp_low' => $period['minTempC'] ?? null,
                    'temp_avg' => $period['avgTempC'] ?? null,
                    'weather' => $period['weather'] ?? null,
                    'weather_short' => $period['weatherPrimary'] ?? null,
                    'icon' => $period['icon'] ?? null,
                    'precipitation' => $period['precipMM'] ?? null,
                    'precipitation_chance' => $period['pop'] ?? null,
                    'humidity' => $period['humidity'] ?? null,
                    'wind_speed' => $period['windSpeedKPH'] ?? null,
                    'wind_direction' => $period['windDir'] ?? null,
                    'uv_index' => $period['uvi'] ?? null,
                    'sunrise' => $period['sunrise'] ?? null,
                    'sunset' => $period['sunset'] ?? null,
                ];
            }

            return $forecast;
        });
    }

    /**
     * Get radar/satellite imagery URL
     */
    public function getRadarUrl(string $type = 'radar'): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $endpoint = match($type) {
            'satellite' => 'satellite',
            'satellite-infrared' => 'satellite-infrared',
            default => 'radar',
        };

        return $this->apiUrl . "maps/{$endpoint}/{$this->latitude},{$this->longitude}" 
            . "?client_id={$this->accessId}&client_secret={$this->secretKey}"
            . "&width=600&height=400&zoom=7";
    }

    /**
     * Get lightning data
     */
    public function getLightning(): ?array
    {
        $cacheKey = "aeris_lightning_{$this->latitude}_{$this->longitude}";

        return Cache::remember($cacheKey, 120, function () {
            $response = $this->makeRequest("lightning/{$this->latitude},{$this->longitude}", [
                'radius' => '50km',
                'limit' => 20,
            ]);
            
            if (!$response) {
                return [];
            }

            $strikes = [];
            foreach ($response as $strike) {
                $strikes[] = [
                    'timestamp' => $strike['timestamp'] ?? null,
                    'time_ago' => isset($strike['timestamp']) 
                        ? $this->timeAgo($strike['timestamp'])
                        : null,
                    'latitude' => $strike['loc']['lat'] ?? null,
                    'longitude' => $strike['loc']['long'] ?? null,
                    'distance_km' => isset($strike['relativeTo']) 
                        ? round($strike['relativeTo']['distanceKM'] ?? 0, 1)
                        : null,
                    'direction' => $strike['relativeTo']['bearingDEG'] ?? null,
                ];
            }

            return $strikes;
        });
    }

    /**
     * Get time ago string
     */
    private function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return date('M j', $timestamp);
    }
}
