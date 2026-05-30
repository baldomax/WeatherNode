<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnvironmentCanadaService implements ForecastServiceInterface
{
    private float $latitude;
    private float $longitude;
    private string $baseUrl = 'https://weather.gc.ca/rss/city/';

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
    }

    /**
     * Fetch weather forecast from Environment Canada
     * Note: Environment Canada uses city codes, so we need to find the nearest city
     */
    public function fetchForecast(): ?array
    {
        $cacheKey = "ec_forecast_{$this->latitude}_{$this->longitude}";
        
        return Cache::remember($cacheKey, 3600, function () {
            try {
                // Environment Canada uses city codes, not lat/lon directly
                // We'll use the XML RSS feed which is publicly available
                // First, try to get forecast for a major city near the coordinates
                $cityCode = $this->findNearestCityCode();
                
                if (!$cityCode) {
                    Log::error('Environment Canada: Could not find city code for coordinates', [
                        'lat' => $this->latitude,
                        'lon' => $this->longitude,
                    ]);
                    return null;
                }

                $response = Http::get($this->baseUrl . $cityCode . '_e.xml');
                
                if ($response->successful()) {
                    return $this->parseForecast($response->body(), $cityCode);
                }

                Log::error('Environment Canada API request failed', [
                    'status' => $response->status(),
                    'city_code' => $cityCode,
                ]);

            } catch (\Exception $e) {
                Log::error('Environment Canada API exception', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * Find nearest Environment Canada city code
     * This is a simplified approach - in production you'd want a proper city code lookup
     */
    private function findNearestCityCode(): ?string
    {
        // Allow explicit override via settings
        $override = Setting::getValue('environment_canada.city_code', '');
        if (!empty($override)) {
            return $override;
        }

        // Common Canadian city codes (you may want to expand this)
        // Format: province-city (e.g., on-118 for Toronto)
        $cities = [
            ['code' => 'on-118', 'lat' => 43.6532, 'lon' => -79.3832], // Toronto
            ['code' => 'bc-74', 'lat' => 49.2827, 'lon' => -123.1207], // Vancouver
            ['code' => 'qc-147', 'lat' => 45.5017, 'lon' => -73.5673], // Montreal
            ['code' => 'ab-5', 'lat' => 51.0447, 'lon' => -114.0719], // Calgary
            ['code' => 'ab-50', 'lat' => 53.5461, 'lon' => -113.4938], // Edmonton
            ['code' => 'on-137', 'lat' => 45.4215, 'lon' => -75.6972], // Ottawa
        ];

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($cities as $city) {
            $distance = $this->calculateDistance($this->latitude, $this->longitude, $city['lat'], $city['lon']);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $city['code'];
            }
        }

        return $nearest;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
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
     * Parse Environment Canada RSS XML into simplified structure
     */
    private function parseForecast(string $xml, string $cityCode): array
    {
        $forecast = [];
        
        try {
            $xmlObj = simplexml_load_string($xml);
            if (!$xmlObj) {
                return ['updated_at' => now()->toIso8601String(), 'forecast' => []];
            }

            // Environment Canada RSS format
            $entries = $xmlObj->xpath('//item');
            
            foreach ($entries as $entry) {
                $title = (string)($entry->title ?? '');
                $description = (string)($entry->description ?? '');
                $pubDate = (string)($entry->pubDate ?? '');
                
                // Parse forecast from title and description
                $parsed = $this->parseForecastEntry($title, $description, $pubDate);
                if ($parsed) {
                    $forecast[] = $parsed;
                }
            }
        } catch (\Exception $e) {
            Log::error('Environment Canada XML parse error', ['error' => $e->getMessage()]);
        }

        return [
            'updated_at' => now()->toIso8601String(),
            'forecast' => $forecast,
        ];
    }

    /**
     * Parse a single forecast entry from Environment Canada RSS
     */
    private function parseForecastEntry(string $title, string $description, string $pubDate): ?array
    {
        // Extract date from title (e.g., "Today: Cloudy. High 15.")
        $time = $this->parseDateFromTitle($title, $pubDate);
        
        // Extract temperature
        $temperature = null;
        if (preg_match('/High\s+(-?\d+)|Low\s+(-?\d+)/i', $title . ' ' . $description, $matches)) {
            $temperature = (float)($matches[1] ?? $matches[2] ?? null);
        }

        // Extract wind
        $windSpeed = null;
        $windDirection = null;
        if (preg_match('/wind\s+(\d+)\s*km\/h/i', $description, $matches)) {
            $windSpeed = (float)$matches[1];
        }
        if (preg_match('/wind\s+([NSEW]{1,3})/i', $description, $matches)) {
            $windDirection = $this->cardinalToDegrees($matches[1]);
        }

        // Extract precipitation
        $precipitation = 0;
        if (preg_match('/(\d+(?:\.\d+)?)\s*mm/i', $description, $matches)) {
            $precipitation = (float)$matches[1];
        }

        // Determine symbol
        $symbol = $this->textToSymbol($title . ' ' . $description);

        return [
            'time' => $time,
            'temperature' => $temperature,
            'humidity' => null,
            'pressure' => null,
            'wind_speed' => $windSpeed,
            'wind_direction' => $windDirection,
            'cloud_cover' => null,
            'symbol' => $symbol,
            'precipitation_1h' => $precipitation / 24, // Daily estimate
            'precipitation_6h' => $precipitation / 4,  // Daily estimate
        ];
    }

    /**
     * Parse date from title or use pubDate
     */
    private function parseDateFromTitle(string $title, string $pubDate): string
    {
        $now = now();
        $title = strtolower($title);
        
        if (strpos($title, 'today') !== false) {
            return $now->setTime(12, 0)->toIso8601String();
        } elseif (strpos($title, 'tonight') !== false) {
            return $now->setTime(20, 0)->toIso8601String();
        } elseif (strpos($title, 'tomorrow') !== false) {
            return $now->addDay()->setTime(12, 0)->toIso8601String();
        } elseif (preg_match('/(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $title, $matches)) {
            $dayMap = [
                'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0,
            ];
            $targetDay = $dayMap[strtolower($matches[1])] ?? null;
            if ($targetDay !== null) {
                $daysUntil = ($targetDay - $now->dayOfWeek + 7) % 7;
                if ($daysUntil === 0) {
                    $daysUntil = 7;
                }
                return $now->addDays($daysUntil)->setTime(12, 0)->toIso8601String();
            }
        }
        
        // Try to parse pubDate
        if ($pubDate) {
            try {
                return date('c', strtotime($pubDate));
            } catch (\Exception $e) {
                // Fall through
            }
        }
        
        return $now->toIso8601String();
    }

    /**
     * Convert cardinal direction to degrees
     */
    private function cardinalToDegrees(string $cardinal): ?int
    {
        $cardinal = strtoupper(trim($cardinal));
        
        $directions = [
            'N' => 0, 'NNE' => 22, 'NE' => 45, 'ENE' => 67,
            'E' => 90, 'ESE' => 112, 'SE' => 135, 'SSE' => 157,
            'S' => 180, 'SSW' => 202, 'SW' => 225, 'WSW' => 247,
            'W' => 270, 'WNW' => 292, 'NW' => 315, 'NNW' => 337,
        ];

        return $directions[$cardinal] ?? null;
    }

    /**
     * Convert text description to symbol code
     */
    private function textToSymbol(string $text): string
    {
        $text = strtolower($text);
        
        if (preg_match('/clear|sunny/i', $text)) {
            return 'clearsky_day';
        } elseif (preg_match('/partly\s+cloudy|partly\s+sunny/i', $text)) {
            return 'partlycloudy_day';
        } elseif (preg_match('/cloudy|overcast/i', $text)) {
            return 'cloudy';
        } elseif (preg_match('/rain|shower/i', $text)) {
            if (preg_match('/heavy|thunder/i', $text)) {
                return 'heavyrainandthunder';
            } elseif (preg_match('/light/i', $text)) {
                return 'lightrain';
            }
            return 'rain';
        } elseif (preg_match('/snow/i', $text)) {
            if (preg_match('/heavy/i', $text)) {
                return 'heavysnow';
            }
            return 'snow';
        } elseif (preg_match('/sleet|freezing\s+rain/i', $text)) {
            return 'sleet';
        } elseif (preg_match('/fog|mist|haze/i', $text)) {
            return 'fog';
        } elseif (preg_match('/thunder/i', $text)) {
            return 'heavyrainandthunder';
        }
        
        return 'partlycloudy_day';
    }

    /**
     * Get hourly forecast for specified number of hours
     */
    public function getHourlyForecast(int $hours = 48): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        // Environment Canada provides daily forecasts, not hourly
        return array_slice($data['forecast'], 0, min($hours, count($data['forecast'])));
    }

    /**
     * Get daily forecast summary
     */
    public function getDailyForecast(int $days = 7): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        $forecast = $data['forecast'] ?? [];
        $daily = [];

        foreach ($forecast as $entry) {
            $date = substr($entry['time'], 0, 10);
            
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'temp_high' => $entry['temperature'],
                    'temp_low' => $entry['temperature'],
                    'temps' => [],
                    'symbols' => [],
                    'precipitation' => 0,
                    'wind_speeds' => [],
                    'wind_directions' => [],
                ];
            }

            if ($entry['temperature'] !== null) {
                $daily[$date]['temps'][] = $entry['temperature'];
                if ($entry['temperature'] > $daily[$date]['temp_high']) {
                    $daily[$date]['temp_high'] = $entry['temperature'];
                }
                if ($entry['temperature'] < $daily[$date]['temp_low']) {
                    $daily[$date]['temp_low'] = $entry['temperature'];
                }
            }
            
            if ($entry['symbol']) {
                $daily[$date]['symbols'][] = $entry['symbol'];
            }
            
            $daily[$date]['precipitation'] += ($entry['precipitation_1h'] ?? 0) * 24; // Convert hourly estimate back to daily
            
            // Collect wind data
            if (isset($entry['wind_speed']) && $entry['wind_speed'] !== null) {
                $daily[$date]['wind_speeds'][] = $entry['wind_speed'];
            }
            if (isset($entry['wind_direction']) && $entry['wind_direction'] !== null) {
                $daily[$date]['wind_directions'][] = $entry['wind_direction'];
            }
        }

        // Calculate dominant symbol and average wind for each day
        foreach ($daily as &$day) {
            $day['temp_avg'] = count($day['temps']) > 0 ? array_sum($day['temps']) / count($day['temps']) : null;
            $day['symbol'] = $this->getDominantSymbol($day['symbols']);
            
            // Calculate average wind speed
            $day['wind_speed'] = !empty($day['wind_speeds']) 
                ? array_sum($day['wind_speeds']) / count($day['wind_speeds']) 
                : null;
            
            // Calculate average wind direction (circular mean)
            $day['wind_direction'] = null;
            if (!empty($day['wind_directions'])) {
                $sinSum = 0;
                $cosSum = 0;
                foreach ($day['wind_directions'] as $dir) {
                    $rad = deg2rad($dir);
                    $sinSum += sin($rad);
                    $cosSum += cos($rad);
                }
                $avgRad = atan2($sinSum / count($day['wind_directions']), $cosSum / count($day['wind_directions']));
                $day['wind_direction'] = round(rad2deg($avgRad));
                // Normalize to 0-360
                if ($day['wind_direction'] < 0) {
                    $day['wind_direction'] += 360;
                }
            }
            
            unset($day['temps'], $day['symbols'], $day['wind_speeds'], $day['wind_directions']);
        }

        return array_slice(array_values($daily), 0, $days);
    }

    /**
     * Get most common weather symbol for a day
     */
    private function getDominantSymbol(array $symbols): ?string
    {
        if (empty($symbols)) {
            return null;
        }

        $counts = array_count_values($symbols);
        arsort($counts);
        return array_key_first($counts);
    }
}
