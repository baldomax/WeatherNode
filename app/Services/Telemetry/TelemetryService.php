<?php

namespace App\Services\Telemetry;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelemetryService
{
    /**
     * Collect station data for telemetry
     */
    public function collectStationData(): ?array
    {
        $enabled = Setting::getValue('telemetry.enabled', false);
        
        if (!$enabled) {
            return null;
        }

        try {
            $name = Setting::stationName();
            $hardware = Setting::getValue('station.hardware', '');
            $manufacturer = Setting::getValue('station.manufacturer', '');
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            $serverUrl = Setting::getValue('station.server_url', config('app.url', ''));
            
            // Generate unique station ID (hash of URL + name)
            $stationId = $this->generateStationId($serverUrl, $name);

            // Resolve country from real coordinates (before anonymization)
            $countryCode = $this->resolveCountryCode((float) $latitude, (float) $longitude);

            // Anonymize coordinates: random offset within ~100m radius
            [$anonLat, $anonLon] = $this->anonymizeCoordinates((float) $latitude, (float) $longitude);

            return [
                'id' => $stationId,
                'name' => $name,
                'hardware' => $hardware,
                'manufacturer' => $manufacturer,
                'latitude' => $anonLat,
                'longitude' => $anonLon,
                'country_code' => $countryCode,
                'url' => rtrim($serverUrl, '/'),
                'updated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to collect station telemetry data', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Format station data for GitHub JSON structure
     */
    public function formatForGitHub(array $stationData): array
    {
        return [
            'stations' => [$stationData],
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if telemetry data has changed since last update
     */
    public function shouldUpdate(): bool
    {
        $enabled = Setting::getValue('telemetry.enabled', false);
        
        if (!$enabled) {
            return false;
        }

        $lastUpdated = Setting::getValue('telemetry.last_updated', '');
        
        // Always update if never updated before
        if (empty($lastUpdated)) {
            return true;
        }

        // Get current data
        $currentData = $this->collectStationData();
        if (!$currentData) {
            return false;
        }

        // Get last saved data hash
        $lastDataHash = Setting::getValue('telemetry.last_data_hash', '');
        $currentDataHash = $this->hashStationData($currentData);

        // Update if data changed
        return $lastDataHash !== $currentDataHash;
    }

    /**
     * Resolve ISO 3166-1 alpha-2 country code from coordinates via Nominatim.
     * Results are cached in Settings to avoid repeat API calls.
     */
    private function resolveCountryCode(float $lat, float $lon): ?string
    {
        // Round to 2 decimals (~1km) for cache key stability
        $coordHash = md5(round($lat, 2) . ',' . round($lon, 2));
        $cachedCode = Setting::getValue('telemetry.cached_country_code', '');
        $cachedCoordHash = Setting::getValue('telemetry.cached_country_coords', '');

        if (!empty($cachedCode) && $cachedCoordHash === $coordHash) {
            return $cachedCode;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => UserAgentService::forExternalApi(),
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'json',
                    'zoom' => 3,
                    'addressdetails' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $cc = $data['address']['country_code'] ?? null;

                if ($cc) {
                    $cc = strtoupper($cc);
                    Setting::setValue('telemetry.cached_country_code', $cc, 'string', 'telemetry');
                    Setting::setValue('telemetry.cached_country_coords', $coordHash, 'string', 'telemetry');
                    return $cc;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Nominatim reverse geocoding failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $cachedCode ?: null;
    }

    /**
     * Anonymize coordinates by adding a random offset within ~100m radius.
     * Each call produces a different offset so the exact location is never stored.
     */
    private function anonymizeCoordinates(float $lat, float $lon): array
    {
        // ~100m in degrees latitude (1° lat ≈ 111 320 m)
        $maxOffsetLat = 100 / 111320;
        // ~100m in degrees longitude (varies with latitude)
        $cosLat = cos(deg2rad($lat));
        $maxOffsetLon = $cosLat > 0 ? 100 / (111320 * $cosLat) : $maxOffsetLat;

        // Random angle + random distance (uniform distribution within circle)
        $angle = mt_rand(0, 3600000) / 3600000 * 2 * M_PI;
        $distance = sqrt(mt_rand(0, 1000000) / 1000000); // sqrt for uniform area distribution

        $offsetLat = $distance * $maxOffsetLat * cos($angle);
        $offsetLon = $distance * $maxOffsetLon * sin($angle);

        return [
            round($lat + $offsetLat, 6),
            round($lon + $offsetLon, 6),
        ];
    }

    /**
     * Generate unique station ID from URL only.
     * Using only the URL ensures the ID stays stable when other
     * station details (e.g. name) are changed.
     */
    private function generateStationId(string $url, string $name): string
    {
        return substr(md5($url), 0, 16);
    }

    /**
     * Create hash of station data for change detection
     */
    private function hashStationData(array $data): string
    {
        // Exclude updated_at and anonymized coordinates from hash
        // (coordinates are randomized each call, so they'd always differ)
        $dataToHash = $data;
        unset($dataToHash['updated_at'], $dataToHash['latitude'], $dataToHash['longitude']);

        ksort($dataToHash);
        return md5(json_encode($dataToHash));
    }

    /**
     * Save last update timestamp and data hash
     */
    public function markAsUpdated(array $stationData): void
    {
        Setting::setValue('telemetry.last_updated', now()->toIso8601String(), 'string', 'telemetry');
        Setting::setValue('telemetry.last_data_hash', $this->hashStationData($stationData), 'string', 'telemetry');
    }

    /**
     * Get station ID for removal
     */
    public function getStationId(): ?string
    {
        $enabled = Setting::getValue('telemetry.enabled', false);
        if (!$enabled) {
            return null;
        }

        $data = $this->collectStationData();
        return $data['id'] ?? null;
    }
}
