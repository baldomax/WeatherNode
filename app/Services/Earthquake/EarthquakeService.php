<?php

namespace App\Services\Earthquake;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EarthquakeService
{
    private float $latitude;
    private float $longitude;
    private int $radiusKm;
    private float $minMagnitude;
    private string $apiUrl = 'https://www.seismicportal.eu/fdsnws/event/1/query';

    /** Mean length of a degree of latitude, for converting the configured radius. */
    private const KM_PER_DEGREE = 111.195;

    /** How far back a worldwide lookup reaches. */
    private const WORLDWIDE_WINDOW_DAYS = 7;
    
    // Magnitude classifications
    private array $magnitudeClasses = [
        ['max' => 4.0, 'class' => 'Minor', 'color' => '#4CAF50'],
        ['max' => 5.0, 'class' => 'Light', 'color' => '#8BC34A'],
        ['max' => 6.0, 'class' => 'Moderate', 'color' => '#FFEB3B'],
        ['max' => 7.0, 'class' => 'Strong', 'color' => '#FF9800'],
        ['max' => 8.0, 'class' => 'Major', 'color' => '#FF5722'],
        ['max' => PHP_FLOAT_MAX, 'class' => 'Great', 'color' => '#F44336'],
    ];

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
        $this->radiusKm = (int) Setting::getValue('earthquakes.radius_km', 500);
        $this->minMagnitude = (float) Setting::getValue('earthquakes.min_magnitude', 2.5);
    }

    /**
     * Fetch recent earthquakes from Seismic Portal
     */
    public function fetchEarthquakes(int $limit = 30, bool $withinRadius = true): ?array
    {
        $scope = $withinRadius ? 'nearby' : 'all';
        $minMag = str_replace('.', '_', (string) $this->minMagnitude);
        $cacheKey = "earthquakes_{$scope}_{$this->latitude}_{$this->longitude}_{$this->radiusKm}_{$minMag}_{$limit}";

        return Cache::remember($cacheKey, 120, function () use ($limit, $withinRadius) {
            try {
                // Ask the API for the area or period wanted, rather than taking
                // the last N events worldwide and filtering afterwards. At M2.5+
                // those N events span roughly an hour of global seismicity, so a
                // nearby quake almost never survived to be filtered.
                $params = [
                    'limit' => $limit,
                    'format' => 'json',
                    'minmag' => $this->minMagnitude,
                ];

                if ($withinRadius) {
                    // FDSN maxradius is in degrees, not kilometres.
                    $params['lat'] = $this->latitude;
                    $params['lon'] = $this->longitude;
                    $params['maxradius'] = round($this->radiusKm / self::KM_PER_DEGREE, 4);
                } else {
                    // Worldwide still needs a period, or it is again just the
                    // last few minutes of global activity.
                    $params['start'] = now()->subDays(self::WORLDWIDE_WINDOW_DAYS)->toIso8601String();
                }

                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => UserAgentService::forExternalApi(),
                    ])
                    ->get($this->apiUrl, $params);

                if (!$response->successful()) {
                    Log::error('Seismic Portal request failed', [
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                return $this->parseResponse($response->json(), $withinRadius);

            } catch (\Exception $e) {
                Log::error('Seismic Portal exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse Seismic Portal GeoJSON response
     */
    private function parseResponse(array $data, bool $withinRadius = true): array
    {
        $earthquakes = [];

        if (!isset($data['features']) || !is_array($data['features'])) {
            return [];
        }

        foreach ($data['features'] as $feature) {
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? [];
            $coordinates = $geometry['coordinates'] ?? [];

            if (empty($properties) || count($coordinates) < 2) {
                continue;
            }

            $magnitude = $properties['mag'] ?? null;
            $lat = $coordinates[1];
            $lon = $coordinates[0];
            $depth = $coordinates[2] ?? $properties['depth'] ?? null;
            
            // Calculate distance from station
            $distance = $this->calculateDistance($this->latitude, $this->longitude, $lat, $lon);
            
            // Filter by radius when fetching nearby activity.
            if ($withinRadius && $distance > $this->radiusKm) {
                continue;
            }

            $dateTime = $properties['time'] ?? null;
            $unix = $dateTime ? strtotime($dateTime) : null;
            // Prefer human-readable region name; fallback to flynn_region, place, title, or EventLocationName
            $location = $properties['region'] ?? $properties['flynn_region'] ?? $properties['place'] ?? $properties['title'] ?? ($properties['EventLocationName'] ?? 'Unknown');
            if ($location === '' || $location === null) {
                $location = 'Unknown';
            }
            
            $classification = $this->getMagnitudeClassification($magnitude);

            $distanceRounded = round($distance, 0);
            $earthquakes[] = [
                'id' => $feature['id'] ?? null,
                'magnitude' => $magnitude,
                'magnitude_class' => $classification['class'],
                'magnitude_color' => $classification['color'],
                'location' => $location,
                'place' => $location, // alias for dashboard widget
                'latitude' => $lat,
                'longitude' => $lon,
                'depth' => $depth,
                'depth_km' => $depth ? round($depth, 1) : null,
                'date_time' => $dateTime,
                'time' => $dateTime, // alias for dashboard widget
                'timestamp' => $unix,
                'time_ago' => $unix ? $this->timeAgo($unix) : null,
                'title' => "{$classification['class']} earthquake - {$location}",
                'distance_km' => $distanceRounded,
                'distance' => $distanceRounded, // alias for dashboard widget
                'link' => isset($feature['id'])
                    ? "https://seismicportal.eu/eventdetails.html?unid={$feature['id']}"
                    : null,
            ];
        }

        // Sort by timestamp (newest first)
        usort($earthquakes, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return $earthquakes;
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get magnitude classification
     */
    private function getMagnitudeClassification(?float $magnitude): array
    {
        if ($magnitude === null) {
            return ['class' => 'Unknown', 'color' => '#9E9E9E'];
        }

        foreach ($this->magnitudeClasses as $class) {
            if ($magnitude < $class['max']) {
                return $class;
            }
        }

        return ['class' => 'Great', 'color' => '#F44336'];
    }

    /**
     * Generate human-readable time ago string
     */
    private function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return "{$mins} minute" . ($mins > 1 ? 's' : '') . " ago";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "{$hours} hour" . ($hours > 1 ? 's' : '') . " ago";
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "{$days} day" . ($days > 1 ? 's' : '') . " ago";
        } else {
            return date('M j, Y', $timestamp);
        }
    }

    /**
     * Get earthquakes within configured radius
     */
    public function getNearbyEarthquakes(int $limit = 10): array
    {
        $fetchLimit = max($limit, 50);
        $earthquakes = $this->fetchEarthquakes($fetchLimit, true);
        
        if (!$earthquakes) {
            return [];
        }

        return array_slice($earthquakes, 0, $limit);
    }

    /**
     * Get recent earthquakes without radius filtering.
     */
    public function getAllRecentEarthquakes(int $limit = 100): array
    {
        $earthquakes = $this->fetchEarthquakes($limit, false);

        if (!$earthquakes) {
            return [];
        }

        return array_slice($earthquakes, 0, $limit);
    }

    /**
     * Get the most recent significant earthquake
     */
    public function getMostRecentSignificant(float $minMag = 4.0): ?array
    {
        $earthquakes = $this->fetchEarthquakes(50);
        
        if (!$earthquakes) {
            return null;
        }

        foreach ($earthquakes as $eq) {
            if (($eq['magnitude'] ?? 0) >= $minMag) {
                return $eq;
            }
        }

        return null;
    }

    /**
     * Get earthquake statistics
     */
    public function getStatistics(): array
    {
        $earthquakes = $this->fetchEarthquakes(100);
        
        if (!$earthquakes) {
            return [
                'total' => 0,
                'last_24h' => 0,
                'last_week' => 0,
                'max_magnitude' => null,
                'avg_magnitude' => null,
            ];
        }

        $now = time();
        $last24h = array_filter($earthquakes, fn($eq) => ($now - ($eq['timestamp'] ?? 0)) < 86400);
        $lastWeek = array_filter($earthquakes, fn($eq) => ($now - ($eq['timestamp'] ?? 0)) < 604800);
        
        $magnitudes = array_filter(array_column($earthquakes, 'magnitude'));

        return [
            'total' => count($earthquakes),
            'last_24h' => count($last24h),
            'last_week' => count($lastWeek),
            'max_magnitude' => !empty($magnitudes) ? max($magnitudes) : null,
            'avg_magnitude' => !empty($magnitudes) ? round(array_sum($magnitudes) / count($magnitudes), 1) : null,
        ];
    }
}
