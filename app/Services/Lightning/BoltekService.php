<?php

namespace App\Services\Lightning;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BoltekService
{
    private string $dataFile;
    private float $latitude;
    private float $longitude;

    public function __construct()
    {
        $this->dataFile = Setting::getValue('lightning.boltek_file', 'demodata/NSRealtime.txt');
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->dataFile) && file_exists(public_path($this->dataFile));
    }

    /**
     * Fetch lightning data from Boltek file
     */
    public function fetchLightningData(): ?array
    {
        if (!$this->isConfigured()) {
            Log::info('Boltek data file not found or not configured');
            return null;
        }

        $cacheKey = "boltek_lightning";

        return Cache::remember($cacheKey, 30, function () {
            try {
                $filePath = public_path($this->dataFile);
                $content = file_get_contents($filePath);
                
                if (!$content) {
                    return null;
                }

                return $this->parseNexstormData($content);

            } catch (\Exception $e) {
                Log::error('Boltek data parse exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse NexStorm realtime data format
     * Format varies but typically includes: timestamp, distance, bearing, type, etc.
     */
    private function parseNexstormData(string $content): array
    {
        $lines = explode("\n", trim($content));
        $strikes = [];
        $summary = [
            'total_strikes' => 0,
            'close_strikes' => 0, // within 20km
            'last_strike' => null,
            'closest_strike' => null,
            'activity_level' => 'none',
        ];

        $closestDistance = PHP_FLOAT_MAX;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Try to parse different formats
            $strike = $this->parseLine($line);
            
            if ($strike) {
                $strikes[] = $strike;
                $summary['total_strikes']++;
                
                if ($strike['distance'] < 20) {
                    $summary['close_strikes']++;
                }
                
                if ($strike['distance'] < $closestDistance) {
                    $closestDistance = $strike['distance'];
                    $summary['closest_strike'] = $strike;
                }
                
                // Last strike by timestamp
                if (!$summary['last_strike'] || 
                    ($strike['timestamp'] ?? 0) > ($summary['last_strike']['timestamp'] ?? 0)) {
                    $summary['last_strike'] = $strike;
                }
            }
        }

        // Determine activity level
        $summary['activity_level'] = $this->getActivityLevel($summary['total_strikes'], $summary['close_strikes']);

        // Sort by distance
        usort($strikes, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return [
            'strikes' => array_slice($strikes, 0, 50), // Limit to 50 most recent/close
            'summary' => $summary,
            'source' => 'boltek',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse a single line of Boltek data
     * Supports multiple formats
     */
    private function parseLine(string $line): ?array
    {
        $parts = preg_split('/[\s,;|]+/', $line);

        if (count($parts) < 3) {
            return null;
        }

        // Try common formats:
        // Format 1: timestamp distance bearing [type]
        // Format 2: date time distance bearing [type]
        // Format 3: lat lon timestamp type

        $strike = [
            'timestamp' => null,
            'distance' => null,
            'bearing' => null,
            'type' => 'CG', // Cloud-to-Ground default
            'latitude' => null,
            'longitude' => null,
        ];

        // Try to extract timestamp
        if (is_numeric($parts[0]) && strlen($parts[0]) > 8) {
            // Unix timestamp
            $strike['timestamp'] = (int) $parts[0];
        } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $parts[0])) {
            // Date format
            $dateTime = $parts[0] . ' ' . ($parts[1] ?? '00:00:00');
            $strike['timestamp'] = strtotime($dateTime);
        }

        // Try to find distance and bearing
        foreach ($parts as $i => $part) {
            if (is_numeric($part)) {
                $num = (float) $part;
                
                // Distance is typically 0-500 km
                if ($num >= 0 && $num <= 500 && $strike['distance'] === null) {
                    $strike['distance'] = $num;
                }
                // Bearing is 0-360 degrees
                elseif ($num >= 0 && $num <= 360 && $strike['bearing'] === null) {
                    $strike['bearing'] = $num;
                }
                // Latitude
                elseif ($num >= -90 && $num <= 90 && $strike['latitude'] === null) {
                    $strike['latitude'] = $num;
                }
                // Longitude
                elseif ($num >= -180 && $num <= 180 && $strike['longitude'] === null) {
                    $strike['longitude'] = $num;
                }
            }
            
            // Strike type
            if (in_array(strtoupper($part), ['CG', 'IC', 'CC'])) {
                $strike['type'] = strtoupper($part);
            }
        }

        // Calculate lat/lon from distance and bearing if not provided
        if ($strike['distance'] !== null && $strike['bearing'] !== null && 
            $strike['latitude'] === null) {
            $pos = $this->calculatePosition(
                $this->latitude, 
                $this->longitude, 
                $strike['distance'], 
                $strike['bearing']
            );
            $strike['latitude'] = $pos['lat'];
            $strike['longitude'] = $pos['lon'];
        }

        // Need at least distance
        if ($strike['distance'] === null) {
            return null;
        }

        // Add human-readable info
        $strike['direction_text'] = $this->bearingToCardinal($strike['bearing'] ?? 0);
        $strike['time_ago'] = $this->timeAgo($strike['timestamp'] ?? time());

        return $strike;
    }

    /**
     * Calculate position from distance and bearing
     */
    private function calculatePosition(float $lat, float $lon, float $distanceKm, float $bearing): array
    {
        $earthRadius = 6371; // km
        
        $lat1 = deg2rad($lat);
        $lon1 = deg2rad($lon);
        $brng = deg2rad($bearing);
        $d = $distanceKm / $earthRadius;

        $lat2 = asin(
            sin($lat1) * cos($d) + 
            cos($lat1) * sin($d) * cos($brng)
        );
        
        $lon2 = $lon1 + atan2(
            sin($brng) * sin($d) * cos($lat1),
            cos($d) - sin($lat1) * sin($lat2)
        );

        return [
            'lat' => round(rad2deg($lat2), 4),
            'lon' => round(rad2deg($lon2), 4),
        ];
    }

    /**
     * Convert bearing to cardinal direction
     */
    private function bearingToCardinal(float $bearing): string
    {
        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 
                       'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = round($bearing / 22.5) % 16;
        return $directions[$index];
    }

    /**
     * Generate time ago string
     */
    private function timeAgo(?int $timestamp): string
    {
        if (!$timestamp) return 'unknown';
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return date('M j H:i', $timestamp);
    }

    /**
     * Determine activity level
     */
    private function getActivityLevel(int $total, int $close): string
    {
        if ($close >= 10) return 'severe';
        if ($close >= 5) return 'high';
        if ($total >= 10) return 'moderate';
        if ($total >= 1) return 'low';
        return 'none';
    }

    /**
     * Get current lightning summary
     */
    public function getSummary(): ?array
    {
        $data = $this->fetchLightningData();
        return $data['summary'] ?? null;
    }

    /**
     * Get recent strikes
     */
    public function getRecentStrikes(int $limit = 20): array
    {
        $data = $this->fetchLightningData();
        
        if (!$data || empty($data['strikes'])) {
            return [];
        }

        return array_slice($data['strikes'], 0, $limit);
    }
}
