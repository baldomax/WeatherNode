<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Models\Setting;
use App\Services\AirQuality\CalculatesAirQualityIndex;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AirLink Local API Service
 * 
 * Provides integration with Davis AirLink devices via local HTTP API.
 * Retrieves air quality data directly from the device on the local network.
 * 
 * @see https://weatherlink.github.io/airlink-local-api
 */
class AirLinkLocalService
{
    use CalculatesAirQualityIndex;

    private ?string $ipAddress;
    private int $port;
    private string $baseUrl;
    private bool $enabled;

    /**
     * Constructor - Loads configuration from settings
     */
    public function __construct()
    {
        $this->enabled = (bool) Setting::getValue('davis_aq.enabled', false);
        $this->ipAddress = Setting::getValue('weatherlink.airlink_ip', '');
        $this->port = (int) Setting::getValue('weatherlink.airlink_port', 80);
        $this->baseUrl = $this->buildBaseUrl();
    }

    /**
     * Build the base URL for API requests
     * 
     * Validates IP address/hostname and constructs the HTTP URL.
     * 
     * @return string Base URL or empty string if not configured
     */
    private function buildBaseUrl(): string
    {
        if (empty($this->ipAddress)) {
            return '';
        }

        // Validate and sanitize IP/hostname
        $host = $this->sanitizeHostname($this->ipAddress);
        if (empty($host)) {
            Log::warning('AirLink Local: Invalid IP address or hostname', [
                'ip' => $this->ipAddress,
            ]);
            return '';
        }

        $port = ($this->port > 0 && $this->port <= 65535) ? $this->port : 80;
        
        return "http://{$host}:{$port}";
    }

    /**
     * Sanitize and validate hostname/IP address
     * 
     * Prevents SSRF attacks by validating the hostname/IP.
     * 
     * @param string $host Hostname or IP address
     * @return string|null Sanitized hostname or null if invalid
     */
    private function sanitizeHostname(string $host): ?string
    {
        // Remove any protocol prefix
        $host = preg_replace('#^https?://#', '', $host);
        
        // Remove port if present
        $host = preg_replace('#:\d+$#', '', $host);
        
        // Trim whitespace
        $host = trim($host);
        
        if (empty($host)) {
            return null;
        }

        // Validate IP address format
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Allow only private IP ranges to prevent SSRF
            $ipLong = ip2long($host);
            if ($ipLong === false) {
                return null;
            }
            
            // Private IP ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
            $isPrivate = (
                ($ipLong >= ip2long('10.0.0.0') && $ipLong <= ip2long('10.255.255.255')) ||
                ($ipLong >= ip2long('172.16.0.0') && $ipLong <= ip2long('172.31.255.255')) ||
                ($ipLong >= ip2long('192.168.0.0') && $ipLong <= ip2long('192.168.255.255'))
            );
            
            if (!$isPrivate && $host !== '127.0.0.1' && $host !== '::1') {
                Log::warning('AirLink Local: Rejected non-private IP address', ['ip' => $host]);
                return null;
            }
            
            return $host;
        }

        // Validate hostname format (allow .local for mDNS)
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.?(local)?$/', $host)) {
            return $host;
        }

        Log::warning('AirLink Local: Invalid hostname format', ['host' => $host]);
        return null;
    }

    /**
     * Check if service is enabled
     *
     * @return bool True if Davis AQ is enabled in settings
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if service is configured
     *
     * @return bool True if enabled and IP address is configured
     */
    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->ipAddress) && !empty($this->baseUrl);
    }

    /**
     * Get current conditions from AirLink device
     * 
     * Retrieves current air quality and temperature/humidity data.
     * Results are cached for 60 seconds.
     * 
     * @return array|null Parsed sensor data, or null on failure
     */
    public function getCurrentConditions(): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        if (empty($this->ipAddress) || empty($this->baseUrl)) {
            Log::warning('AirLink Local API not configured: IP address missing');
            return null;
        }

        $cacheKey = "airlink_local_current_{$this->ipAddress}";

        return Cache::remember($cacheKey, 60, function () {
            try {
                $response = Http::timeout(5)
                    ->get("{$this->baseUrl}/v1/current_conditions");

                if (!$response->successful()) {
                    Log::error('AirLink Local API request failed', [
                        'ip' => $this->ipAddress,
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                $data = $response->json();
                
                if (!isset($data['data']) || !isset($data['data']['conditions']) || empty($data['data']['conditions'])) {
                    Log::warning('AirLink Local API response invalid', [
                        'ip' => $this->ipAddress,
                    ]);
                    return null;
                }

                return $this->parseSensorData($data['data']['conditions']);

            } catch (\Exception $e) {
                Log::error('AirLink Local API exception', [
                    'error' => $e->getMessage(),
                    'ip' => $this->ipAddress,
                ]);
                return null;
            }
        });
    }

    /**
     * Parse sensor data from AirLink API response
     * 
     * Maps AirLink sensor data to standardized format.
     * Supports data structure types 5 and 6 (combined AQ/Temp/Humidity).
     * 
     * @param array $conditions Array of condition objects from API response
     * @return array Parsed weather data with standardized keys
     */
    private function parseSensorData(array $conditions): array
    {
        $data = [
            'temperature' => null,
            'humidity' => null,
            'dew_point' => null,
            'pm_1' => null,
            'pm_2p5' => null,
            'pm_10' => null,
            'pm_2p5_1h' => null,
            'pm_2p5_24h' => null,
            'pm_10_1h' => null,
            'pm_10_24h' => null,
        ];

        // AirLink typically returns one condition record
        $condition = $conditions[0] ?? [];
        $dataStructureType = $condition['data_structure_type'] ?? 0;

        // Data structure type 6 (current) or 5 (older firmware)
        if ($dataStructureType === 6 || $dataStructureType === 5) {
            $data['temperature'] = $this->fahrenheitToCelsius($condition['temp'] ?? null);
            $data['humidity'] = $condition['hum'] ?? null;
            $data['dew_point'] = $this->fahrenheitToCelsius($condition['dew_point'] ?? null);

            // PM data - use _last fields for most recent, or averaged fields
            $data['pm_1'] = $condition['pm_1'] ?? $condition['pm_1_last'] ?? null;
            $data['pm_2p5'] = $condition['pm_2p5'] ?? $condition['pm_2p5_last'] ?? null;
            $data['pm_10'] = $condition['pm_10'] ?? ($condition['pm_10p0'] ?? null) ?? $condition['pm_10_last'] ?? null;

            // Hourly averages
            $data['pm_2p5_1h'] = $condition['pm_2p5_last_1_hour'] ?? null;
            $data['pm_2p5_24h'] = $condition['pm_2p5_last_24_hours'] ?? null;
            $data['pm_10_1h'] = $condition['pm_10_last_1_hour'] ?? ($condition['pm_10p0_last_1_hour'] ?? null);
            $data['pm_10_24h'] = $condition['pm_10_last_24_hours'] ?? ($condition['pm_10p0_last_24_hours'] ?? null);

            // Calculate AQI using the configured index type (US EPA, EEA, or UK DAQI)
            $indexType = $this->getConfiguredIndexType();
            $data['aqi'] = $this->calculateAqi([
                'pm1' => $data['pm_1'],
                'pm25' => $data['pm_2p5'],
                'pm10' => $data['pm_10'],
            ], $indexType);
            $data['index_type'] = $indexType;
        }

        return array_filter($data, fn($v) => $v !== null);
    }

    /**
     * Convert Fahrenheit to Celsius
     * 
     * @param float|null $f Temperature in Fahrenheit
     * @return float|null Temperature in Celsius, rounded to 1 decimal place
     */
    private function fahrenheitToCelsius(?float $f): ?float
    {
        return $f !== null ? round(($f - 32) * 5/9, 1) : null;
    }
}
