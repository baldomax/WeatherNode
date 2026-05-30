<?php

namespace App\Services\Astronomy;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class ISSService
{
    private const ISS_LOCATION_URL = 'http://api.open-notify.org/iss-now.json';
    private const ISS_PASS_URL = 'http://api.open-notify.org/iss-pass.json';
    private const ASTRO_URL = 'http://api.open-notify.org/astros.json'; // Fallback
    private const ASTRO_URL_CORQUAID = 'https://corquaid.github.io/international-space-station-APIs/JSON/people-in-space.json';
    private const N2YO_BASE_URL = 'https://api.n2yo.com/rest/v1/satellite';
    
    // NORAD IDs
    private const ISS_NORAD_ID = 25544;
    private const TIANGONG_NORAD_ID = 48274; // Tiangong Space Station
    
    private float $latitude;
    private float $longitude;

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
    }

    /**
     * Get current ISS location
     */
    public function getCurrentLocation(): array
    {
        return $this->getStationLocation(self::ISS_NORAD_ID, 'iss_location');
    }

    /**
     * Get current Tiangong location
     */
    public function getTiangongLocation(): array
    {
        return $this->getStationLocation(self::TIANGONG_NORAD_ID, 'tiangong_location');
    }

    /**
     * Get station location by NORAD ID
     */
    private function getStationLocation(int $noradId, string $cacheKey): array
    {
        return Cache::remember($cacheKey, 30, function () use ($noradId) {
            try {
                // Try N2YO API first if configured
                $n2yoApiKey = Setting::getValue('iss.n2yo_api_key', '');
                if ($n2yoApiKey) {
                    // Use N2YO for more accurate position (works for both ISS and Tiangong)
                    $response = Http::timeout(10)->get(self::N2YO_BASE_URL . "/positions/{$noradId}/{$this->latitude}/{$this->longitude}/0/1", [
                        'apiKey' => $n2yoApiKey,
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['positions'][0])) {
                            $pos = $data['positions'][0];
                            $defaultAltitude = $noradId === self::TIANGONG_NORAD_ID ? 380 : 408;
                            return [
                                'success' => true,
                                'latitude' => (float) ($pos['satlatitude'] ?? 0),
                                'longitude' => (float) ($pos['satlongitude'] ?? 0),
                                'altitude' => (float) ($pos['sataltitude'] ?? $defaultAltitude),
                                'timestamp' => $pos['timestamp'] ?? time(),
                                'formatted_time' => date('H:i:s', $pos['timestamp'] ?? time()),
                                'source' => 'n2yo',
                            ];
                        }
                    }
                }

                // Fallback to Open Notify for ISS only
                if ($noradId === self::ISS_NORAD_ID) {
                    $response = Http::timeout(10)->get(self::ISS_LOCATION_URL);

                    if ($response->successful()) {
                        $data = $response->json();

                        if (($data['message'] ?? '') === 'success') {
                            $position = $data['iss_position'] ?? [];

                            return [
                                'success' => true,
                                'latitude' => (float) ($position['latitude'] ?? 0),
                                'longitude' => (float) ($position['longitude'] ?? 0),
                                'altitude' => 408, // Default ISS altitude
                                'timestamp' => $data['timestamp'] ?? time(),
                                'formatted_time' => date('H:i:s', $data['timestamp'] ?? time()),
                                'source' => 'open-notify',
                            ];
                        }
                    }
                }

                return $this->getDefaultLocationResponse();

            } catch (\Exception $e) {
                Log::error('Failed to fetch station location', [
                    'norad_id' => $noradId,
                    'error' => $e->getMessage(),
                ]);
                return $this->getDefaultLocationResponse();
            }
        });
    }

    /**
     * Get upcoming ISS passes for the configured location
     * Note: Open Notify ISS pass API is often unavailable, so we use alternative calculation
     */
    public function getUpcomingPasses(): array
    {
        return $this->getStationPasses(self::ISS_NORAD_ID, "iss_passes_{$this->latitude}_{$this->longitude}");
    }

    /**
     * Get upcoming Tiangong passes for the configured location
     */
    public function getTiangongPasses(): array
    {
        return $this->getStationPasses(self::TIANGONG_NORAD_ID, "tiangong_passes_{$this->latitude}_{$this->longitude}");
    }

    /**
     * Get station passes by NORAD ID
     */
    private function getStationPasses(int $noradId, string $cacheKey): array
    {
        return Cache::remember($cacheKey, 3600, function () use ($noradId) {
            // Try N2YO API first if configured
            $n2yoApiKey = Setting::getValue('iss.n2yo_api_key', '');
            if ($n2yoApiKey) {
                $passes = $this->fetchPassesFromN2YO($noradId);
                if (!empty($passes)) {
                    return $passes;
                }
            }

            // Try Open Notify API for ISS only
            if ($noradId === self::ISS_NORAD_ID) {
                $passes = $this->fetchPassesFromOpenNotify();
                if (!empty($passes)) {
                    return $passes;
                }
            }

            // Fallback: Calculate approximate passes
            return $this->calculateApproximatePasses($noradId);
        });
    }

    /**
     * Fetch passes from N2YO API
     */
    private function fetchPassesFromN2YO(int $noradId): array
    {
        try {
            $n2yoApiKey = Setting::getValue('iss.n2yo_api_key', '');
            if (!$n2yoApiKey) {
                return [];
            }

            $observerAlt = 0; // Sea level
            $days = 2;
            $minVisibility = 60; // Minimum 60 seconds visible

            $response = Http::timeout(10)->get(self::N2YO_BASE_URL . "/visualpasses/{$noradId}/{$this->latitude}/{$this->longitude}/{$observerAlt}/{$days}/{$minVisibility}", [
                'apiKey' => $n2yoApiKey,
            ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            if (!isset($data['passes']) || !is_array($data['passes'])) {
                return [];
            }

            $passes = [];
            foreach ($data['passes'] as $pass) {
                $passes[] = [
                    'rise_time' => $pass['startUTC'] ?? 0,
                    'rise_time_formatted' => date('d M H:i', $pass['startUTC'] ?? 0),
                    'duration_seconds' => $pass['duration'] ?? 0,
                    'duration_formatted' => gmdate('i:s', $pass['duration'] ?? 0),
                    'visible' => true, // N2YO only returns visible passes
                    'max_elevation' => $pass['maxEl'] ?? 0,
                    'magnitude' => $pass['mag'] ?? null,
                    'source' => 'n2yo',
                ];
            }

            return [
                'success' => true,
                'passes' => $passes,
                'source' => 'n2yo',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch passes from N2YO', [
                'norad_id' => $noradId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch passes from Open Notify API
     */
    private function fetchPassesFromOpenNotify(): array
    {
        try {
            $response = Http::timeout(10)->get(self::ISS_PASS_URL, [
                'lat' => $this->latitude,
                'lon' => $this->longitude,
                'n' => 5,
            ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            if (($data['message'] ?? '') !== 'success') {
                return [];
            }

            $passes = [];
            foreach (($data['response'] ?? []) as $pass) {
                $risetime = $pass['risetime'] ?? 0;
                $duration = $pass['duration'] ?? 0;
                
                $passes[] = [
                    'rise_time' => $risetime,
                    'rise_time_formatted' => date('d M H:i', $risetime),
                    'duration_seconds' => $duration,
                    'duration_formatted' => gmdate('i:s', $duration),
                    'visible' => $this->isLikelyVisible($risetime),
                ];
            }

            return [
                'success' => true,
                'passes' => $passes,
                'source' => 'open-notify',
            ];

        } catch (\Exception $e) {
            Log::warning('Open Notify ISS pass API unavailable', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Calculate approximate station passes
     * Both ISS and Tiangong orbit ~16 times per day at ~90 minute intervals
     */
    private function calculateApproximatePasses(int $noradId = self::ISS_NORAD_ID): array
    {
        $passes = [];
        $now = time();
        $orbitPeriod = 92 * 60; // ~92 minutes per orbit
        
        // Generate approximate future passes
        // Visibility depends on many factors, this is a rough estimate
        for ($i = 0; $i < 5; $i++) {
            // Random offset within next few days (passes are irregular)
            $nextPass = $now + ($orbitPeriod * ($i + rand(1, 4)));
            
            // Skip daytime passes (not visible)
            $hour = (int) date('G', $nextPass);
            if ($hour >= 10 && $hour <= 17) {
                $nextPass += 6 * 3600; // Skip to evening
            }
            
            $duration = rand(240, 420); // 4-7 minutes typical
            
            $passes[] = [
                'rise_time' => $nextPass,
                'rise_time_formatted' => date('d M H:i', $nextPass),
                'duration_seconds' => $duration,
                'duration_formatted' => gmdate('i:s', $duration),
                'visible' => $this->isLikelyVisible($nextPass),
                'approximate' => true,
            ];
        }

        // Sort by time
        usort($passes, fn($a, $b) => $a['rise_time'] - $b['rise_time']);

        $stationName = $noradId === self::TIANGONG_NORAD_ID ? 'Tiangong' : 'ISS';
        $note = $noradId === self::ISS_NORAD_ID 
            ? 'Geschatte tijden. Controleer spotthestation.nasa.gov voor exacte tijden.'
            : 'Geschatte tijden.';

        return [
            'success' => true,
            'passes' => $passes,
            'source' => 'calculated',
            'note' => $note,
        ];
    }

    /**
     * Check if ISS is likely visible (dark sky, not too late)
     */
    private function isLikelyVisible(int $timestamp): bool
    {
        $hour = (int) date('G', $timestamp);
        
        // Best visibility: dusk (18-22) or dawn (4-7)
        // ISS is visible when sun is below horizon but ISS is still lit
        return ($hour >= 18 && $hour <= 23) || ($hour >= 4 && $hour <= 7);
    }

    /**
     * Get people currently in space
     * Uses configured API source (corquaid, open-notify, or n2yo)
     */
    public function getPeopleInSpace(): array
    {
        $apiSource = Setting::getValue('iss.astronauts_api_source', 'corquaid');
        $cacheKey = "astros_in_space_{$apiSource}";
        $cacheDuration = Setting::getValue('iss.astronauts_poll_frequency', 60) * 60; // Convert minutes to seconds
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($apiSource) {
            try {
                switch ($apiSource) {
                    case 'n2yo':
                        return $this->fetchFromN2YO();
                    case 'open-notify':
                        return $this->fetchAstronautsFromOpenNotify();
                    case 'corquaid':
                    default:
                        return $this->fetchFromCorquaid();
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch astronauts in space', [
                    'source' => $apiSource,
                    'error' => $e->getMessage(),
                ]);
                return $this->getDefaultAstrosResponse();
            }
        });
    }

    /**
     * Fetch from corquaid.github.io API
     */
    private function fetchFromCorquaid(): array
    {
        $response = Http::timeout(10)->get(self::ASTRO_URL_CORQUAID);

        if (!$response->successful()) {
            return $this->getDefaultAstrosResponse();
        }

        $data = $response->json();
        
        if (!isset($data['people']) || !is_array($data['people'])) {
            return $this->getDefaultAstrosResponse();
        }

        // Filter ISS astronauts (iss: true)
        $issPeople = array_filter($data['people'], function($person) {
            return ($person['iss'] ?? false) === true;
        });
        
        // Filter Tiangong astronauts (iss: false, spacecraft contains Shenzhou)
        $tiangongPeople = array_filter($data['people'], function($person) {
            return ($person['iss'] ?? false) === false && 
                   isset($person['spacecraft']) && 
                   strpos($person['spacecraft'], 'Shenzhou') !== false;
        });
        
        // Format people for display
        $issPeopleFormatted = array_map(function($person) {
            return [
                'name' => $person['name'] ?? '',
                'craft' => 'ISS',
            ];
        }, array_values($issPeople));
        
        return [
            'success' => true,
            'number' => count($issPeople), // Only ISS count
            'people' => $issPeopleFormatted,
            'total_in_space' => $data['number'] ?? count($data['people']),
            'breakdown' => [
                'iss' => count($issPeople),
                'tiangong' => count($tiangongPeople),
            ],
            'source' => 'corquaid-api',
            'expedition' => $data['iss_expedition'] ?? null,
        ];
    }

    /**
     * Fetch astronauts from Open Notify API
     */
    private function fetchAstronautsFromOpenNotify(): array
    {
        $response = Http::timeout(10)->get(self::ASTRO_URL);

        if (!$response->successful()) {
            return $this->getDefaultAstrosResponse();
        }

        $data = $response->json();

        if (($data['message'] ?? '') !== 'success') {
            return $this->getDefaultAstrosResponse();
        }

        // Count astronauts by craft
        $people = $data['people'] ?? [];
        $issPeople = array_filter($people, function($person) {
            return ($person['craft'] ?? '') === 'ISS';
        });
        $tiangongPeople = array_filter($people, function($person) {
            return ($person['craft'] ?? '') === 'Tiangong';
        });
        
        return [
            'success' => true,
            'number' => count($issPeople), // Only ISS count
            'people' => array_values($issPeople), // Only ISS people
            'total_in_space' => $data['number'] ?? 0, // Total from API
            'breakdown' => [
                'iss' => count($issPeople),
                'tiangong' => count($tiangongPeople),
            ],
            'source' => 'open-notify',
            'note' => 'Data van Open Notify (kan verouderd zijn)',
        ];
    }

    /**
     * Fetch from N2YO.com API
     * Note: N2YO doesn't provide astronaut data directly, so we fall back to corquaid
     */
    private function fetchFromN2YO(): array
    {
        // N2YO doesn't provide astronaut data, so we fall back to corquaid
        // But we could use N2YO for satellite position/pass data
        return $this->fetchFromCorquaid();
    }

    /**
     * Get ISS info summary
     */
    public function getIssSummary(): array
    {
        $location = $this->getCurrentLocation();
        $passes = $this->getUpcomingPasses();
        $astros = $this->getPeopleInSpace();

        // Calculate distance from station
        $distance = null;
        if ($location['success']) {
            $distance = $this->calculateDistance(
                $this->latitude,
                $this->longitude,
                $location['latitude'],
                $location['longitude']
            );
        }

        // Get next visible pass
        $nextVisiblePass = null;
        if ($passes['success'] && !empty($passes['passes'])) {
            foreach ($passes['passes'] as $pass) {
                if ($pass['visible'] ?? false) {
                    $nextVisiblePass = $pass;
                    break;
                }
            }
            // If no visible pass found, use first pass
            if (!$nextVisiblePass) {
                $nextVisiblePass = $passes['passes'][0] ?? null;
            }
        }

        return [
            'location' => $location,
            'next_pass' => $nextVisiblePass,
            'all_passes' => $passes['passes'] ?? [],
            'pass_source' => $passes['source'] ?? 'unknown',
            'pass_note' => $passes['note'] ?? null,
            'astronauts' => $astros,
            'distance_km' => $distance ? round($distance) : null,
            'altitude_km' => 408, // Average ISS altitude
            'speed_kmh' => 27600, // Average orbital speed
        ];
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

    private function getDefaultLocationResponse(): array
    {
        return [
            'success' => false,
            'latitude' => null,
            'longitude' => null,
            'timestamp' => null,
        ];
    }

    private function getDefaultAstrosResponse(): array
    {
        return [
            'success' => false,
            'number' => null,
            'people' => [],
        ];
    }
}
