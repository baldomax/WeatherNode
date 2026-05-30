<?php

namespace App\Services\AirQuality;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class PurpleAirService
{
    use CalculatesAirQualityIndex;

    private ?string $apiKey;
    private ?int $sensorId;
    private string $apiUrl = 'https://api.purpleair.com/v1/sensors/';

    public function __construct()
    {
        $this->apiKey = $this->getDecryptedApiKey();
        $this->sensorId = (int) Setting::getValue('purpleair.sensor_id', 0) ?: null;
    }

    /**
     * Get decrypted API key
     */
    private function getDecryptedApiKey(): ?string
    {
        $encrypted = Setting::getValue('purpleair.api_key', '');
        
        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            // Might already be decrypted or invalid
            return $encrypted;
        }
    }

    /**
     * Fetch sensor data from PurpleAir API
     */
    public function fetchSensorData(): ?array
    {
        if (empty($this->apiKey) || empty($this->sensorId)) {
            Log::warning('PurpleAir API key or sensor ID not configured');
            return null;
        }

        $cacheKey = "purpleair_{$this->sensorId}";

        return Cache::remember($cacheKey, 120, function () {
            try {
                $fields = implode(',', [
                    'name', 'latitude', 'longitude', 'altitude',
                    'pm1.0', 'pm2.5', 'pm10.0',
                    'temperature', 'humidity', 'pressure',
                    'last_seen', 'model', 'hardware',
                ]);

                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-API-Key' => $this->apiKey,
                    ])
                    ->get($this->apiUrl . $this->sensorId, [
                        'fields' => $fields,
                    ]);

                if (!$response->successful()) {
                    Log::error('PurpleAir API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $this->parseResponse($response->json());

            } catch (\Exception $e) {
                Log::error('PurpleAir exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse PurpleAir API response
     */
    private function parseResponse(array $data): array
    {
        $sensor = $data['sensor'] ?? [];

        if (empty($sensor)) {
            return [];
        }

        $pm1 = $sensor['pm1.0'] ?? null;
        $pm25 = $sensor['pm2.5'] ?? null;
        $pm10 = $sensor['pm10.0'] ?? null;

        // Calculate AQI using the configured index type (US EPA, EEA, or UK DAQI)
        $indexType = $this->getConfiguredIndexType();
        $aqi = $this->calculateAqi([
            'pm1' => $pm1,
            'pm25' => $pm25,
            'pm10' => $pm10,
        ], $indexType);

        // Convert temperature from F to C if present
        $tempF = $sensor['temperature'] ?? null;
        $tempC = $tempF !== null ? round(($tempF - 32) * 5/9, 1) : null;

        return [
            'sensor_id' => $this->sensorId,
            'name' => $sensor['name'] ?? 'PurpleAir Sensor',
            'model' => $sensor['model'] ?? null,
            'hardware' => $sensor['hardware'] ?? null,
            'location' => [
                'latitude' => $sensor['latitude'] ?? null,
                'longitude' => $sensor['longitude'] ?? null,
                'altitude' => $sensor['altitude'] ?? null,
            ],
            'readings' => [
                'pm1' => [
                    'value' => $pm1,
                    'unit' => 'µg/m³',
                    'label' => 'PM1.0',
                ],
                'pm25' => [
                    'value' => $pm25,
                    'unit' => 'µg/m³',
                    'label' => 'PM2.5',
                ],
                'pm10' => [
                    'value' => $pm10,
                    'unit' => 'µg/m³',
                    'label' => 'PM10',
                ],
                'temperature' => [
                    'value' => $tempC,
                    'unit' => '°C',
                    'label' => 'Temperature',
                ],
                'humidity' => [
                    'value' => $sensor['humidity'] ?? null,
                    'unit' => '%',
                    'label' => 'Humidity',
                ],
                'pressure' => [
                    'value' => $sensor['pressure'] ?? null,
                    'unit' => 'mbar',
                    'label' => 'Pressure',
                ],
            ],
            'aqi' => $aqi,
            'index_type' => $indexType,
            'last_seen' => isset($sensor['last_seen'])
                ? date('Y-m-d H:i:s', $sensor['last_seen'])
                : null,
        ];
    }

    /**
     * Get current air quality data
     */
    public function getCurrentAirQuality(): ?array
    {
        return $this->fetchSensorData();
    }
}
