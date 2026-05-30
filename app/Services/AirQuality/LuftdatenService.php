<?php

namespace App\Services\AirQuality;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LuftdatenService
{
    use CalculatesAirQualityIndex;

    private string $sensorId;
    private string $apiUrl = 'https://data.sensor.community/airrohr/v1/sensor/';
    
    public function __construct()
    {
        $this->sensorId = Setting::getValue('luftdaten.sensor_id', '69616');
    }

    /**
     * Fetch sensor data from Sensor.Community (Luftdaten)
     */
    public function fetchSensorData(): ?array
    {
        return $this->fetchBySensorId($this->sensorId);
    }

    /**
     * Fetch sensor data for a specific sensor ID (no caching; caller caches).
     * Used for the configured PM sensor (cache key luftdaten_{id}) or noise sensor (luftdaten_noise_{id}).
     */
    public function fetchBySensorId(string $sensorId): ?array
    {
        if (empty(trim($sensorId))) {
            return null;
        }

        try {
            $http = Http::timeout(10);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders([
                    'User-Agent' => UserAgentService::forExternalApi(),
                ])
                ->get($this->apiUrl . $sensorId . '/');

            if (!$response->successful()) {
                Log::error('Luftdaten API request failed', [
                    'status' => $response->status(),
                    'sensor_id' => $sensorId,
                ]);
                return null;
            }

            $data = $this->parseResponse($response->json());
            if (empty($data)) {
                return null;
            }

            // Add noise level description for noise sensors
            if (($data['category'] ?? '') === 'noise' && isset($data['values']['noise_LAeq'])) {
                $data['noise_level'] = $this->getNoiseDescription((float) $data['values']['noise_LAeq']);
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Luftdaten exception', ['error' => $e->getMessage(), 'sensor_id' => $sensorId]);
            return null;
        }
    }

    /**
     * Parse Sensor.Community API response
     */
    private function parseResponse(array $data): array
    {
        if (empty($data) || !is_array($data)) {
            return [];
        }

        // Get the most recent reading
        $latest = $data[0] ?? null;
        
        if (!$latest) {
            return [];
        }

        $sensorInfo = $latest['sensor'] ?? [];
        $sensorType = $sensorInfo['sensor_type'] ?? [];
        $location = $latest['location'] ?? [];
        $values = $latest['sensordatavalues'] ?? [];

        // Parse sensor values into key-value pairs
        $parsedValues = [];
        foreach ($values as $value) {
            $type = $value['value_type'] ?? null;
            $val = $value['value'] ?? null;
            if ($type && $val !== null) {
                $parsedValues[$type] = (float) $val;
            }
        }

        // Determine sensor category (PM, noise, temperature, etc.)
        $sensorCategory = $this->determineSensorCategory($sensorType['name'] ?? '');

        return [
            'sensor_id' => $this->sensorId,
            'sensor_name' => $sensorType['name'] ?? 'Unknown',
            'manufacturer' => $sensorType['manufacturer'] ?? 'Unknown',
            'timestamp' => $latest['timestamp'] ?? null,
            'location' => [
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'altitude' => $location['altitude'] ?? null,
                'country' => $location['country'] ?? null,
                'indoor' => (bool) ($location['indoor'] ?? false),
            ],
            'category' => $sensorCategory,
            'values' => $parsedValues,
            'formatted' => $this->formatValues($parsedValues, $sensorCategory),
        ];
    }

    /**
     * Determine sensor category from sensor type name
     */
    private function determineSensorCategory(string $sensorName): string
    {
        $name = strtolower($sensorName);
        
        if (str_contains($name, 'dnms') || str_contains($name, 'laerm') || str_contains($name, 'noise')) {
            return 'noise';
        }
        if (str_contains($name, 'sds') || str_contains($name, 'pms') || str_contains($name, 'sps')) {
            return 'particulate';
        }
        if (str_contains($name, 'bme') || str_contains($name, 'dht')) {
            return 'climate';
        }
        
        return 'unknown';
    }

    /**
     * Format values for display
     */
    private function formatValues(array $values, string $category): array
    {
        $formatted = [];

        switch ($category) {
            case 'noise':
                $formatted = [
                    'noise_avg' => [
                        'value' => $values['noise_LAeq'] ?? null,
                        'unit' => 'dB(A)',
                        'label' => 'Average Noise Level',
                    ],
                    'noise_min' => [
                        'value' => $values['noise_LA_min'] ?? null,
                        'unit' => 'dB(A)',
                        'label' => 'Minimum',
                    ],
                    'noise_max' => [
                        'value' => $values['noise_LA_max'] ?? null,
                        'unit' => 'dB(A)',
                        'label' => 'Maximum',
                    ],
                ];
                break;

            case 'particulate':
                $pm25 = $values['P2'] ?? $values['pm25'] ?? null;
                $pm10 = $values['P1'] ?? $values['pm10'] ?? null;
                $pm1 = $values['P0'] ?? $values['pm1'] ?? null;

                // Calculate AQI using the configured index type
                $indexType = $this->getConfiguredIndexType();
                $aqi = $this->calculateAqi([
                    'pm1' => $pm1,
                    'pm25' => $pm25,
                    'pm10' => $pm10,
                ], $indexType);

                $formatted = [
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
                    'aqi' => $aqi,
                    'index_type' => $indexType,
                ];
                break;

            case 'climate':
                $formatted = [
                    'temperature' => [
                        'value' => $values['temperature'] ?? null,
                        'unit' => '°C',
                        'label' => 'Temperature',
                    ],
                    'humidity' => [
                        'value' => $values['humidity'] ?? null,
                        'unit' => '%',
                        'label' => 'Humidity',
                    ],
                    'pressure' => [
                        'value' => $values['pressure'] ?? null,
                        'unit' => 'hPa',
                        'label' => 'Pressure',
                    ],
                ];
                break;

            default:
                foreach ($values as $key => $value) {
                    $formatted[$key] = [
                        'value' => $value,
                        'unit' => '',
                        'label' => ucfirst(str_replace('_', ' ', $key)),
                    ];
                }
        }

        return $formatted;
    }

    /**
     * Get noise level description (level + concrete example as description).
     */
    public function getNoiseDescription(float $db): array
    {
        return match (true) {
            $db < 30 => ['level' => 'Very Quiet', 'description' => 'Whisper, rural area', 'color' => '#4CAF50'],
            $db < 40 => ['level' => 'Quiet', 'description' => 'Library, quiet office', 'color' => '#8BC34A'],
            $db < 50 => ['level' => 'Moderate', 'description' => 'Moderate rainfall', 'color' => '#CDDC39'],
            $db < 60 => ['level' => 'Noticeable', 'description' => 'Normal conversation', 'color' => '#FFEB3B'],
            $db < 70 => ['level' => 'Loud', 'description' => 'Busy traffic, vacuum cleaner', 'color' => '#FFC107'],
            $db < 80 => ['level' => 'Very Loud', 'description' => 'Busy street, alarm clock', 'color' => '#FF9800'],
            $db < 90 => ['level' => 'Extremely Loud', 'description' => 'Heavy traffic, lawn mower', 'color' => '#FF5722'],
            default => ['level' => 'Harmful', 'description' => 'Risk of hearing damage', 'color' => '#F44336'],
        };
    }

    /**
     * Get current readings with human-readable descriptions
     */
    public function getCurrentReadings(): ?array
    {
        $data = $this->fetchSensorData();
        
        if (!$data || empty($data['values'])) {
            return null;
        }

        // Add noise level description if this is a noise sensor
        if ($data['category'] === 'noise' && isset($data['values']['noise_LAeq'])) {
            $data['noise_level'] = $this->getNoiseDescription($data['values']['noise_LAeq']);
        }

        return $data;
    }
}
