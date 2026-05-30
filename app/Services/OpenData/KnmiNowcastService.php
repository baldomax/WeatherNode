<?php

namespace App\Services\OpenData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KnmiNowcastService
{
    private const WMS_BASE_URL = 'https://anonymous.api.dataplatform.knmi.nl/wms/adaguc-server';
    private const DATASET = 'radar_forecast';
    private const LAYER = 'precipitationfc';
    private const CACHE_TTL = 600; // 10 minutes (data updates every 5 minutes)
    
    // Forecast range: 0 to 120 minutes (2 hours), 5-minute intervals = 25 time steps
    private const FORECAST_STEPS = 25;
    private const STEP_INTERVAL = 5; // minutes

    /**
     * Get available time steps for nowcast
     * 
     * Generates time steps for 2-hour forecast (0 to 120 minutes, 5-minute intervals)
     * Uses absolute ISO8601 timestamps (current time + forecast offset)
     * 
     * @return array Array of ISO8601 timestamp strings
     */
    public function getAvailableTimes(): array
    {
        $times = [];
        // Round current time to nearest 5-minute interval (forecast base time)
        $now = Carbon::now();
        $baseTime = $now->copy()->setSecond(0)->setMicrosecond(0);
        // Round down to nearest 5 minutes
        $baseTime->minute(floor($baseTime->minute / 5) * 5);
        
        // Generate forecast steps: 0, 5, 10, ..., 120 minutes from base time
        for ($step = 0; $step < self::FORECAST_STEPS; $step++) {
            $forecastTime = $baseTime->copy()->addMinutes($step * self::STEP_INTERVAL);
            // Format as ISO8601 for WMS TIME parameter
            $times[] = $forecastTime->toIso8601String();
        }
        
        return $times;
    }
    

    /**
     * Get WMS GetMap URL for a specific time step
     * 
     * @param string $time ISO8601 timestamp
     * @param array $bbox Bounding box [minx, miny, maxx, maxy] in EPSG:3857
     * @param int $width Image width
     * @param int $height Image height
     * @return string WMS GetMap URL
     */
    public function getWmsUrl(string $time, ?array $bbox = null, int $width = 512, int $height = 512): string
    {
        // Default bbox for Netherlands (EPSG:3857 Web Mercator)
        if ($bbox === null) {
            // Netherlands approximate bounds in Web Mercator
            $bbox = [300000, 6500000, 800000, 7200000];
        }
        
        $params = [
            'DATASET' => self::DATASET,
            'SERVICE' => 'WMS',
            'VERSION' => '1.3.0',
            'REQUEST' => 'GetMap',
            'LAYERS' => self::LAYER,
            'CRS' => 'EPSG:3857',
            'BBOX' => implode(',', $bbox),
            'WIDTH' => $width,
            'HEIGHT' => $height,
            'FORMAT' => 'image/png',
            // Keep non-precipitation pixels transparent so overlay blends with base map.
            'TRANSPARENT' => 'true',
        ];
        
        // Add TIME parameter for specific forecast step
        // Time should be ISO8601 format timestamp
        if ($time !== 'latest') {
            // Use time as-is (should be ISO8601 format from getAvailableTimes)
            $params['TIME'] = $time;
        }
        
        return self::WMS_BASE_URL . '?' . http_build_query($params);
    }

    /**
     * Get nowcast metadata (times, URLs, etc.)
     * 
     * @return array Array with 'times', 'urls', and metadata
     */
    public function getNowcastMetadata(): array
    {
        // Always generate fresh metadata to ensure correct time format
        // (Cache is handled by the poller, but we generate on-demand if needed)
        $times = $this->getAvailableTimes();
        $urls = [];
        
        foreach ($times as $time) {
            $urls[$time] = $this->getWmsUrl($time);
        }
        
        $metadata = [
            'times' => $times,
            'urls' => $urls,
            'step_interval' => self::STEP_INTERVAL,
            'forecast_hours' => 2,
            'total_steps' => count($times),
        ];
        
        return $metadata;
    }

    /**
     * Query GetCapabilities to get available time dimensions
     * This helps verify what time values the API actually supports
     * 
     * @return array|null Array with time dimension info, or null if query fails
     */
    public function getCapabilities(): ?array
    {
        $cacheKey = 'knmi_nowcast_capabilities';
        
        return Cache::remember($cacheKey, now()->addHours(1), function () {
            try {
                $url = self::WMS_BASE_URL . '?' . http_build_query([
                    'DATASET' => self::DATASET,
                    'SERVICE' => 'WMS',
                    'VERSION' => '1.3.0',
                    'REQUEST' => 'GetCapabilities',
                ]);
                
                $response = Http::timeout(10)->get($url);
                
                if ($response->successful()) {
                    $xml = $response->body();
                    // Parse XML to extract time dimension info
                    // This is a simplified parser - full implementation would use XML parser
                    if (preg_match('/<Dimension name="time"[^>]*>(.*?)<\/Dimension>/is', $xml, $matches)) {
                        $timeValues = trim($matches[1]);
                        return [
                            'time_values' => $timeValues,
                            'raw_xml' => $xml,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch KNMI nowcast GetCapabilities', ['error' => $e->getMessage()]);
            }
            
            return null;
        });
    }

    /**
     * Check if nowcast data is available
     */
    public function isAvailable(): bool
    {
        $metadata = $this->getNowcastMetadata();
        return !empty($metadata['times']);
    }
}
