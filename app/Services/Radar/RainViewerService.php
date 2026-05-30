<?php

namespace App\Services\Radar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RainViewerService
{
    private const API_URL = 'https://api.rainviewer.com/public/weather-maps.json';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Fetch available radar frames from RainViewer API.
     *
     * @param bool $bypassCache When true, always fetch from the API (used by scheduler poll so cache actually updates)
     */
    public function getRadarFrames(bool $bypassCache = false): ?array
    {
        $fetch = function (): ?array {
            try {
                $response = Http::timeout(10)->get(self::API_URL);

                if ($response->successful()) {
                    $data = $response->json();

                    // Return past frames (forecast/nowcast discontinued as of 2026)
                    return [
                        'version' => $data['version'] ?? '2.0',
                        'generated' => $data['generated'] ?? time(),
                        'host' => $data['host'] ?? '',
                        'radar' => [
                            'past' => $data['radar']['past'] ?? [],
                            // 'nowcast' removed - discontinued as of Jan 1, 2026
                        ],
                        'satellite' => [
                            'infrared' => [], // Discontinued as of Jan 1, 2026
                        ],
                    ];
                }

                Log::warning('RainViewer API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('RainViewer API error', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        };

        if ($bypassCache) {
            return $fetch();
        }

        return Cache::remember('rainviewer_frames', self::CACHE_TTL, $fetch);
    }

    /**
     * Get tile URL for a specific frame
     * 
     * @param string $path Frame path from API
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param int $zoom Zoom level (max 7 as of 2026)
     * @param int $size Tile size (256 or 512)
     * @param int $color Color scheme (limited options as of 2026)
     * @param bool $smooth Enable smoothing
     * @param bool $snow Show snow overlay
     */
    public function getTileUrl(
        string $path,
        float $lat,
        float $lon,
        int $zoom = 7,
        int $size = 256,
        int $color = 1,
        bool $smooth = true,
        bool $snow = false
    ): string {
        // Clamp zoom to max 7 (2026 limitation)
        $zoom = min(max($zoom, 1), 7);
        
        $options = ($smooth ? '1' : '0') . '_' . ($snow ? '1' : '0');
        
        // Use lat/lon format for centering
        return "{$path}/{$size}/{$zoom}/{$lat}/{$lon}/{$color}/{$options}.png";
    }

    /**
     * Build iframe embed URL
     */
    public function getIframeUrl(float $lat, float $lon, int $zoom = 7): string
    {
        // Clamp zoom to max 7 (2026 limitation)
        $zoom = min(max($zoom, 1), 7);
        
        return "https://www.rainviewer.com/map.html?loc={$lat},{$lon},{$zoom}&oC=true&oCS=1&c=3&o=83&lm=1&layer=radar&sm=1&sn=1";
    }
}
