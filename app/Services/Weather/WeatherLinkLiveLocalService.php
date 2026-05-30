<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * WeatherLink Live Local API Service
 * 
 * Provides integration with WeatherLink Live devices via local HTTP API.
 * Supports current conditions via HTTP and real-time UDP broadcast.
 * 
 * @see https://weatherlink.github.io/weatherlink-live-local-api
 */
class WeatherLinkLiveLocalService
{
    private ?string $ipAddress;
    private int $port;
    private bool $udpEnabled;
    private int $udpPort;
    private int $udpDuration;
    private string $baseUrl;

    /**
     * Constructor - Loads configuration from settings
     */
    public function __construct()
    {
        $this->ipAddress = Setting::getValue('weatherlink.wll_ip', '');
        $this->port = (int) Setting::getValue('weatherlink.wll_port', 80);
        $this->udpEnabled = (bool) Setting::getValue('weatherlink.wll_udp_enabled', false);
        $this->udpPort = (int) Setting::getValue('weatherlink.wll_udp_port', 22222);
        $this->udpDuration = (int) Setting::getValue('weatherlink.wll_udp_duration', 1200);
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

        // Validate and sanitize IP/hostname (same validation as AirLink)
        $host = $this->sanitizeHostname($this->ipAddress);
        if (empty($host)) {
            Log::warning('WeatherLink Live Local: Invalid IP address or hostname', [
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
     * Same implementation as AirLink service.
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
                Log::warning('WeatherLink Live Local: Rejected non-private IP address', ['ip' => $host]);
                return null;
            }
            
            return $host;
        }

        // Validate hostname format (allow .local for mDNS)
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.?(local)?$/', $host)) {
            return $host;
        }

        Log::warning('WeatherLink Live Local: Invalid hostname format', ['host' => $host]);
        return null;
    }

    /**
     * Check if service is configured
     * 
     * @return bool True if IP address is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->ipAddress) && !empty($this->baseUrl);
    }

    /**
     * Get current conditions from WeatherLink Live device
     * 
     * Retrieves current weather data from all transmitters.
     * Results are cached for 10 seconds (WLL updates every 2.5 seconds).
     * 
     * @return array|null Parsed sensor data, or null on failure
     */
    public function getCurrentConditions(): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('WeatherLink Live Local API not configured');
            return null;
        }

        $cacheKey = "wll_local_current_{$this->ipAddress}";

        return Cache::remember($cacheKey, 10, function () {
            try {
                $response = Http::timeout(5)
                    ->get("{$this->baseUrl}/v1/current_conditions");

                if (!$response->successful()) {
                    Log::error('WeatherLink Live Local API request failed', [
                        'ip' => $this->ipAddress,
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                $data = $response->json();
                
                if (!isset($data['data']) || !isset($data['data']['conditions']) || empty($data['data']['conditions'])) {
                    Log::warning('WeatherLink Live Local API response invalid', [
                        'ip' => $this->ipAddress,
                    ]);
                    return null;
                }

                return $this->parseSensorData($data['data']['conditions']);

            } catch (\Exception $e) {
                Log::error('WeatherLink Live Local API exception', [
                    'error' => $e->getMessage(),
                    'ip' => $this->ipAddress,
                ]);
                return null;
            }
        });
    }

    /**
     * Parse sensor data from WeatherLink Live API response
     * 
     * Maps WLL sensor data to standardized format.
     * Handles multiple sensor types: ISS (type 1), Leaf/Soil (type 2), LSS BAR (type 3), LSS Temp/Hum (type 4).
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
            'wind_chill' => null,
            'heat_index' => null,
            'pressure_rel' => null,
            'wind_speed' => null,
            'wind_gust' => null,
            'wind_direction' => null,
            'rain_rate' => null,
            'rain_daily' => null,
            'rain_monthly' => null,
            'rain_yearly' => null,
            'uv_index' => null,
            'solar_radiation' => null,
            'indoor_temperature' => null,
            'indoor_humidity' => null,
        ];

        foreach ($conditions as $condition) {
            $dataStructureType = $condition['data_structure_type'] ?? 0;

            // Type 1: ISS Current Conditions
            if ($dataStructureType === 1) {
                $data['temperature'] = $this->fahrenheitToCelsius($condition['temp'] ?? null);
                $data['humidity'] = $condition['hum'] ?? null;
                $data['dew_point'] = $this->fahrenheitToCelsius($condition['dew_point'] ?? null);
                $data['wind_chill'] = $this->fahrenheitToCelsius($condition['wind_chill'] ?? null);
                $data['heat_index'] = $this->fahrenheitToCelsius($condition['heat_index'] ?? null);
                $data['wind_speed'] = $this->mphToKmh($condition['wind_speed_last'] ?? null);
                $data['wind_gust'] = $this->mphToKmh($condition['wind_speed_hi_last_10_min'] ?? null);
                $data['wind_direction'] = $condition['wind_dir_last'] ?? null;
                
                // Rain data - convert from counts to mm based on rain_size
                $rainSize = $condition['rain_size'] ?? 2; // 2 = 0.2mm per count
                $rainMultiplier = $this->getRainMultiplier($rainSize);
                $data['rain_rate'] = $this->countsToMm($condition['rain_rate_last'] ?? null, $rainMultiplier);
                $data['rain_daily'] = $this->countsToMm($condition['rainfall_daily'] ?? null, $rainMultiplier);
                $data['rain_monthly'] = $this->countsToMm($condition['rainfall_monthly'] ?? null, $rainMultiplier);
                $data['rain_yearly'] = $this->countsToMm($condition['rainfall_year'] ?? null, $rainMultiplier);
                
                $data['uv_index'] = $condition['uv_index'] ?? null;
                $data['solar_radiation'] = $condition['solar_rad'] ?? null;
            }

            // Type 3: LSS BAR (Barometer)
            if ($dataStructureType === 3) {
                $data['pressure_rel'] = $this->inhgToHpa($condition['bar_sea_level'] ?? null);
            }

            // Type 4: LSS Temp/Hum (Indoor)
            if ($dataStructureType === 4) {
                $data['indoor_temperature'] = $this->fahrenheitToCelsius($condition['temp_in'] ?? null);
                $data['indoor_humidity'] = $condition['hum_in'] ?? null;
            }
        }

        return array_filter($data, fn($v) => $v !== null);
    }

    /**
     * Get rain multiplier based on rain collector size
     * 
     * @param int $rainSize Rain collector type (0: Reserved, 1: 0.01", 2: 0.2mm, 3: 0.1mm, 4: 0.001")
     * @return float Multiplier to convert counts to mm
     */
    private function getRainMultiplier(int $rainSize): float
    {
        return match($rainSize) {
            1 => 0.254,  // 0.01" = 0.254mm
            2 => 0.2,    // 0.2mm per count
            3 => 0.1,    // 0.1mm per count
            4 => 0.0254, // 0.001" = 0.0254mm
            default => 0.2, // Default to 0.2mm
        };
    }

    /**
     * Convert rain counts to millimeters
     * 
     * @param int|null $counts Rain count
     * @param float $multiplier Multiplier (mm per count)
     * @return float|null Rain in mm
     */
    private function countsToMm(?int $counts, float $multiplier): ?float
    {
        return $counts !== null ? round($counts * $multiplier, 2) : null;
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

    /**
     * Convert miles per hour to kilometers per hour
     * 
     * @param float|null $mph Speed in mph
     * @return float|null Speed in km/h, rounded to 1 decimal place
     */
    private function mphToKmh(?float $mph): ?float
    {
        return $mph !== null ? round($mph * 1.60934, 1) : null;
    }

    /**
     * Convert inches of mercury to hectopascals
     * 
     * @param float|null $inhg Pressure in inHg
     * @return float|null Pressure in hPa, rounded to 1 decimal place
     */
    private function inhgToHpa(?float $inhg): ?float
    {
        return $inhg !== null ? round($inhg * 33.8639, 1) : null;
    }

    /**
     * Start UDP broadcast for real-time data
     * 
     * Requests the WeatherLink Live device to start broadcasting UDP packets.
     * Note: This requires a UDP listener to be implemented separately.
     * 
     * @return bool True if broadcast was started successfully
     */
    public function startUdpBroadcast(): bool
    {
        if (!$this->isConfigured() || !$this->udpEnabled) {
            return false;
        }

        try {
            $duration = min($this->udpDuration, 86400); // Max 24 hours
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/v1/real_time", [
                    'duration' => $duration,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('WeatherLink Live UDP broadcast started', [
                    'ip' => $this->ipAddress,
                    'port' => $data['data']['broadcast_port'] ?? $this->udpPort,
                    'duration' => $data['data']['duration'] ?? $duration,
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('WeatherLink Live UDP broadcast failed', [
                'error' => $e->getMessage(),
                'ip' => $this->ipAddress,
            ]);
            return false;
        }
    }
}
