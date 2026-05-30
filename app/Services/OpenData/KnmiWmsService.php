<?php

namespace App\Services\OpenData;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KnmiWmsService
{
    private const WMS_BASE_URL = 'https://anonymous.api.dataplatform.knmi.nl/wms/adaguc-server';
    private const DATASET = 'msg_cpp_products';
    private const CACHE_TTL = 3600; // 1 hour (GetCapabilities doesn't change often)

    /**
     * Available layers from KNMI WMS
     */
    private const LAYERS = [
        'height_at_cloud_top' => [
            'name' => 'Cloud Top Height',
            'description' => 'Cloud altitude in meters',
            'unit' => 'm',
            'styles' => ['default'],
        ],
        'cloud_area_fraction' => [
            'name' => 'Cloud Cover',
            'description' => 'Cloud coverage flag',
            'unit' => '',
            'styles' => ['default'],
        ],
        'atmosphere_optical_thickness_due_to_cloud' => [
            'name' => 'Cloud Optical Thickness',
            'description' => 'Cloud density',
            'unit' => '',
            'styles' => ['default'],
        ],
        'air_temperature_at_cloud_top' => [
            'name' => 'Cloud Top Temperature',
            'description' => 'Temperature in Kelvin',
            'unit' => 'K',
            'styles' => ['default'],
        ],
        'lwe_precipitation_rate' => [
            'name' => 'Precipitation Rate',
            'description' => 'Precipitation rate in mm/hr',
            'unit' => 'mm/hr',
            'styles' => ['precip/nearest', 'precip-rainbow/nearest', 'precip-transparent/nearest', 'precip-gray/nearest', 'precip-blue/nearest', 'precip-pseudoradar/nearest'],
        ],
        'lwe_precipitation_rate_ir' => [
            'name' => 'Precipitation Rate IR',
            'description' => 'Infrared-based precipitation',
            'unit' => 'mm/hr',
            'styles' => ['default'],
        ],
        'surface_downwelling_shortwave_flux_in_air' => [
            'name' => 'Solar Radiation',
            'description' => 'Solar radiation in W/m²',
            'unit' => 'W/m²',
            'styles' => ['default'],
        ],
        'thermodynamic_phase_of_cloud_water_particles_at_cloud_top_defined_by_near_infrared_radiance' => [
            'name' => 'Cloud Phase',
            'description' => 'Ice/liquid/mixed cloud phase',
            'unit' => '',
            'styles' => ['CloudPhase/nearest', 'CloudPhase_showice/nearest', 'CloudPhase_showliquid/nearest', 'CloudPhase_showmixed/nearest'],
        ],
        'effective_radius_of_cloud_condensed_water_particles_at_cloud_top' => [
            'name' => 'Cloud Droplet Radius',
            'description' => 'Cloud droplet radius in micrometers',
            'unit' => 'μm',
            'styles' => ['default'],
        ],
        'atmosphere_cloud_condensed_water_content' => [
            'name' => 'Cloud Water Content',
            'description' => 'Cloud water content in g/m²',
            'unit' => 'g/m²',
            'styles' => ['default'],
        ],
    ];

    /**
     * Get available layers
     */
    public function getAvailableLayers(): array
    {
        return self::LAYERS;
    }

    /**
     * Get WMS GetMap URL for a specific layer and time
     * 
     * @param string $layer Layer name
     * @param string $style Style name
     * @param string $time ISO8601 timestamp (or 'latest' for most recent)
     * @param array $bbox Bounding box [minx, miny, maxx, maxy] in EPSG:3857
     * @param int $width Image width
     * @param int $height Image height
     * @param float $opacity Opacity (0.0 to 1.0)
     * @return string WMS GetMap URL
     */
    public function getWmsUrl(
        string $layer,
        string $style = 'default',
        string $time = 'latest',
        ?array $bbox = null,
        int $width = 512,
        int $height = 512,
        float $opacity = 1.0
    ): string {
        // Default bbox for Netherlands (EPSG:3857 Web Mercator)
        if ($bbox === null) {
            $bbox = [300000, 6500000, 800000, 7200000];
        }

        $params = [
            'DATASET' => self::DATASET,
            'SERVICE' => 'WMS',
            'VERSION' => '1.3.0',
            'REQUEST' => 'GetMap',
            'LAYERS' => $layer,
            'CRS' => 'EPSG:3857',
            'BBOX' => implode(',', $bbox),
            'WIDTH' => $width,
            'HEIGHT' => $height,
            'FORMAT' => 'image/png',
        ];
        
        // Don't add TIME or STYLES parameters - use KNMI defaults

        return self::WMS_BASE_URL . '?' . http_build_query($params);
    }

    /**
     * Get WMS GetLegendGraphic URL for a layer/style combination
     */
    public function getLegendUrl(string $layer, string $style = 'default'): string
    {
        $params = [
            'DATASET' => self::DATASET,
            'SERVICE' => 'WMS',
            'VERSION' => '1.3.0',
            'REQUEST' => 'GetLegendGraphic',
            'LAYER' => $layer,
            'STYLE' => $style,
            'FORMAT' => 'image/png',
        ];

        return self::WMS_BASE_URL . '?' . http_build_query($params);
    }

    /**
     * Get available time steps (15-minute intervals, ~7 days history)
     * 
     * @param int $days Number of days to include
     * @return array Array of ISO8601 timestamps
     */
    public function getAvailableTimes(int $days = 7): array
    {
        $times = [];
        $now = Carbon::now()->setTimezone('UTC');
        $start = $now->copy()->subDays($days);

        // Generate 15-minute intervals
        $current = $start->copy();
        while ($current <= $now) {
            // WMS TIME parameter format: ISO8601 with Z for UTC
            $times[] = $current->format('Y-m-d\TH:i:s\Z');
            $current->addMinutes(15);
        }

        return $times;
    }

    /**
     * Get latest available time (rounded to last 15-minute interval)
     */
    public function getLatestAvailableTime(): string
    {
        $now = Carbon::now()->setTimezone('UTC');
        // Round down to last 15-minute interval
        $minutes = floor($now->minute / 15) * 15;
        $latest = $now->copy()->setTime($now->hour, $minutes, 0);
        // WMS TIME parameter format: ISO8601 with Z for UTC
        return $latest->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Fetch WMS GetCapabilities to get actual layer information
     * (Cached for 1 hour)
     */
    public function getCapabilities(): ?array
    {
        return Cache::remember('knmi_wms_capabilities', self::CACHE_TTL, function () {
            try {
                $params = [
                    'SERVICE' => 'WMS',
                    'VERSION' => '1.3.0',
                    'REQUEST' => 'GetCapabilities',
                    'DATASET' => self::DATASET,
                ];

                $url = self::WMS_BASE_URL . '?' . http_build_query($params);
                $response = Http::timeout(30)->get($url);

                if ($response->successful()) {
                    // Parse XML response
                    $xml = simplexml_load_string($response->body());
                    if ($xml) {
                        // Extract layer information
                        // This is a simplified version - full parsing would extract all layers, styles, time dimensions
                        return [
                            'success' => true,
                            'layers' => self::LAYERS, // Use predefined for now
                        ];
                    }
                }

                Log::warning('KNMI WMS GetCapabilities failed', [
                    'status' => $response->status(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('KNMI WMS GetCapabilities error', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }
}
