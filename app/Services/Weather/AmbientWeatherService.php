<?php

namespace App\Services\Weather;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class AmbientWeatherService
{
    private ?string $apiKey;
    private ?string $applicationKey;
    private ?string $macAddress;
    private string $apiUrl = 'https://rt.ambientweather.net/v1/devices';

    public function __construct()
    {
        $this->apiKey = $this->getDecryptedValue('ambient.api_key');
        $this->applicationKey = $this->getDecryptedValue('ambient.application_key');
        $this->macAddress = Setting::getValue('ambient.mac_address', '');
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
        return !empty($this->apiKey) && !empty($this->applicationKey);
    }

    /**
     * Get devices for this account
     */
    public function getDevices(): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('Ambient Weather API not configured');
            return null;
        }

        $cacheKey = "ambient_devices";

        // The devices response includes lastData, so keep this cache below the
        // five-minute sensor-health threshold used by the dashboard.
        return Cache::remember($cacheKey, 30, function () {
            try {
                $response = Http::timeout(10)->get($this->apiUrl, [
                    'apiKey' => $this->apiKey,
                    'applicationKey' => $this->applicationKey,
                ]);

                if (!$response->successful()) {
                    Log::error('Ambient Weather API request failed', [
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                return $response->json();

            } catch (\Exception $e) {
                Log::error('Ambient Weather API exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get current conditions from first device or specified MAC
     */
    public function getCurrentConditions(): ?array
    {
        $devices = $this->getDevices();
        
        if (!$devices) {
            return null;
        }

        // Find device by MAC or use first one
        $device = null;
        if (!empty($this->macAddress)) {
            foreach ($devices as $d) {
                if (($d['macAddress'] ?? '') === $this->macAddress) {
                    $device = $d;
                    break;
                }
            }
        }
        
        if (!$device && !empty($devices)) {
            $device = $devices[0];
        }

        if (!$device || empty($device['lastData'])) {
            return null;
        }

        return $this->parseDeviceData($device);
    }

    /**
     * Parse device data from Ambient Weather
     */
    private function parseDeviceData(array $device): array
    {
        $data = $device['lastData'] ?? [];
        $info = $device['info'] ?? [];

        return [
            'device' => [
                'name' => $info['name'] ?? 'Ambient Weather Station',
                'mac_address' => $device['macAddress'] ?? null,
                'location' => $info['location'] ?? null,
            ],
            'timestamp' => isset($data['dateutc']) 
                ? date('Y-m-d H:i:s', $data['dateutc'] / 1000)
                : null,
            'outdoor' => [
                'temperature' => $this->fahrenheitToCelsius($data['tempf'] ?? null),
                'feels_like' => $this->fahrenheitToCelsius($data['feelsLike'] ?? null),
                'humidity' => $data['humidity'] ?? null,
                'dew_point' => $this->fahrenheitToCelsius($data['dewPoint'] ?? null),
            ],
            'indoor' => [
                'temperature' => $this->fahrenheitToCelsius($data['tempinf'] ?? null),
                'humidity' => $data['humidityin'] ?? null,
            ],
            'wind' => [
                'speed' => $this->mphToKmh($data['windspeedmph'] ?? null),
                'gust' => $this->mphToKmh($data['windgustmph'] ?? null),
                'direction' => $data['winddir'] ?? null,
                'direction_avg' => $data['winddir_avg10m'] ?? null,
            ],
            'pressure' => [
                'relative' => $this->inhgToHpa($data['baromrelin'] ?? null),
                'absolute' => $this->inhgToHpa($data['baromabsin'] ?? null),
            ],
            'rain' => [
                'rate' => $this->inchToMm($data['hourlyrainin'] ?? null),
                'hourly' => $this->inchToMm($data['hourlyrainin'] ?? null),
                'daily' => $this->inchToMm($data['dailyrainin'] ?? null),
                'weekly' => $this->inchToMm($data['weeklyrainin'] ?? null),
                'monthly' => $this->inchToMm($data['monthlyrainin'] ?? null),
                'yearly' => $this->inchToMm($data['yearlyrainin'] ?? null),
            ],
            'solar' => [
                'uv_index' => $data['uv'] ?? null,
                'solar_radiation' => $data['solarradiation'] ?? null,
            ],
            'battery' => [
                'outdoor' => $data['battout'] ?? null,
            ],
        ];
    }

    /**
     * Get historical data
     */
    public function getHistorical(string $date, int $limit = 288): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $mac = $this->macAddress ?: $this->getFirstDeviceMac();
        
        if (!$mac) {
            return null;
        }

        $endDate = strtotime($date . ' 23:59:59') * 1000;

        $cacheKey = "ambient_history_{$mac}_{$date}";

        return Cache::remember($cacheKey, 3600, function () use ($mac, $endDate, $limit) {
            try {
                $response = Http::timeout(15)->get("{$this->apiUrl}/{$mac}", [
                    'apiKey' => $this->apiKey,
                    'applicationKey' => $this->applicationKey,
                    'endDate' => $endDate,
                    'limit' => $limit,
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();
                
                return array_map(function ($record) {
                    return [
                        'timestamp' => isset($record['dateutc']) 
                            ? date('Y-m-d H:i:s', $record['dateutc'] / 1000)
                            : null,
                        'temperature' => $this->fahrenheitToCelsius($record['tempf'] ?? null),
                        'humidity' => $record['humidity'] ?? null,
                        'wind_speed' => $this->mphToKmh($record['windspeedmph'] ?? null),
                        'pressure' => $this->inhgToHpa($record['baromrelin'] ?? null),
                        'rain' => $this->inchToMm($record['dailyrainin'] ?? null),
                    ];
                }, $data);

            } catch (\Exception $e) {
                Log::error('Ambient Weather history exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get MAC address of first device
     */
    private function getFirstDeviceMac(): ?string
    {
        $devices = $this->getDevices();
        return $devices[0]['macAddress'] ?? null;
    }

    // Unit conversion helpers
    private function fahrenheitToCelsius(?float $f): ?float
    {
        return $f !== null ? round(($f - 32) * 5/9, 1) : null;
    }

    private function mphToKmh(?float $mph): ?float
    {
        return $mph !== null ? round($mph * 1.60934, 1) : null;
    }

    private function inchToMm(?float $inch): ?float
    {
        return $inch !== null ? round($inch * 25.4, 2) : null;
    }

    private function inhgToHpa(?float $inhg): ?float
    {
        return $inhg !== null ? round($inhg * 33.8639, 1) : null;
    }
}
