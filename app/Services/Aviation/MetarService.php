<?php

namespace App\Services\Aviation;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetarService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.checkwx.com/metar/';
    private array $defaultStations = ['EHAM']; // Schiphol as default

    public function __construct()
    {
        $this->apiKey = Setting::getValue('metar.api_key', '') ?? '';
    }

    /**
     * Fetch METAR data for stations
     */
    public function fetchMetar(?array $stations = null): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('CheckWX API key not configured');
            return null;
        }

        $stations = $stations ?? $this->defaultStations;
        $stationList = implode(',', $stations);
        $cacheKey = "metar_{$stationList}";

        // Try to fetch fresh data; fall back to poller-populated cache on failures.
        try {
            $http = Http::timeout(15);
            // Never disable TLS verification in production.
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                ])->get($this->baseUrl . $stationList . '/decoded');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    $result = $this->parseMetarData($data['data']);
                    // Don't cache here - let the poller command handle caching with proper TTL
                    // This prevents conflicts between service cache (300 min) and poller cache (120 min)
                    return $result;
                }
            }

            // Use warning (not error) since we fall back to cached data - this is non-fatal.
            Log::warning('CheckWX API request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);

        } catch (\Exception $e) {
            Log::error('CheckWX API exception', ['error' => $e->getMessage()]);
        }

        // Return cached data if available (cached by poller command)
        return Cache::get($cacheKey);
    }

    /**
     * Parse METAR data into simplified structure
     */
    private function parseMetarData(array $data): array
    {
        $result = [];

        foreach ($data as $metar) {
            $speedKts = $metar['wind']['speed_kts'] ?? null;

            $result[] = [
                'icao' => $metar['icao'] ?? null,
                'name' => $metar['station']['name'] ?? null,
                'latitude' => $metar['station']['geometry']['coordinates'][1] ?? $metar['latitude'] ?? null,
                'longitude' => $metar['station']['geometry']['coordinates'][0] ?? $metar['longitude'] ?? null,
                'raw' => $metar['raw_text'] ?? null,
                'observed' => $metar['observed'] ?? null,
                'temperature' => $metar['temperature']['celsius'] ?? null,
                'dewpoint' => $metar['dewpoint']['celsius'] ?? null,
                'humidity' => $metar['humidity']['percent'] ?? $metar['humidity'] ?? null,
                'pressure' => $metar['barometer']['hpa'] ?? null,
                'wind' => [
                    'speed_kts' => $speedKts,
                    'speed_kmh' => $speedKts === null ? null : ($speedKts * 1.852),
                    'direction' => $metar['wind']['degrees'] ?? null,
                    'gust_kts' => $metar['wind']['gust_kts'] ?? null,
                ],
                'visibility' => [
                    'meters' => $metar['visibility']['meters_float'] ?? $metar['visibility']['meters'] ?? null,
                    'miles' => $metar['visibility']['miles_float'] ?? $metar['visibility']['miles'] ?? null,
                ],
                'clouds' => $this->parseClouds($metar['clouds'] ?? []),
                'conditions' => $this->parseConditions($metar['conditions'] ?? []),
                'flight_category' => $metar['flight_category'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Parse cloud layers
     */
    private function parseClouds(array $clouds): array
    {
        return array_map(function ($cloud) {
            $baseFeet = $cloud['base_feet_agl'] ?? $cloud['feet'] ?? null;
            return [
                'code' => $cloud['code'] ?? null,
                'text' => $cloud['text'] ?? null,
                'base_feet' => $baseFeet,
                'base_meters' => $baseFeet === null ? null : ($baseFeet * 0.3048),
            ];
        }, $clouds);
    }

    /**
     * Parse weather conditions
     */
    private function parseConditions(array $conditions): array
    {
        return array_map(function ($condition) {
            return [
                'code' => $condition['code'] ?? null,
                'text' => $condition['text'] ?? null,
            ];
        }, $conditions);
    }

    /**
     * Get METAR for Schiphol (EHAM)
     */
    public function getSchipholMetar(): ?array
    {
        $data = $this->fetchMetar(['EHAM']);
        return $data[0] ?? null;
    }

    /**
     * Get flight category color
     */
    public function getFlightCategoryColor(string $category): string
    {
        return match ($category) {
            'VFR' => '#00ff00',  // Green - Visual Flight Rules
            'MVFR' => '#0000ff', // Blue - Marginal VFR
            'IFR' => '#ff0000',  // Red - Instrument Flight Rules
            'LIFR' => '#ff00ff', // Magenta - Low IFR
            default => '#808080', // Gray - Unknown
        };
    }
}
