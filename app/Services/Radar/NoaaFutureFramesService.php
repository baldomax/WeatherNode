<?php

namespace App\Services\Radar;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NoaaFutureFramesService
{
    private const CAPABILITIES_CACHE_MINUTES = 30;
    private const MAX_FUTURE_FRAMES = 12;

    /**
     * Region metadata for NWS NDFD WMS endpoints.
     * bounds = [ [southLat, westLon], [northLat, eastLon] ]
     */
    private const REGIONS = [
        'conus' => [
            'endpoint' => 'https://digital.weather.gov/ndfd.conus/wms',
            'bounds' => [[24.0, -125.5], [50.0, -66.0]],
            'station_bounds' => [[24.0, -125.5], [50.0, -66.0]],
        ],
        'alaska' => [
            'endpoint' => 'https://digital.weather.gov/ndfd.alaska/wms',
            'bounds' => [[50.0, -171.0], [72.0, -129.0]],
            'station_bounds' => [[50.0, -171.0], [72.0, -129.0]],
        ],
        'hawaii' => [
            'endpoint' => 'https://digital.weather.gov/ndfd.hawaii/wms',
            'bounds' => [[18.0, -161.0], [23.0, -154.0]],
            'station_bounds' => [[18.0, -161.0], [23.0, -154.0]],
        ],
        'puertori' => [
            'endpoint' => 'https://digital.weather.gov/ndfd.puertori/wms',
            'bounds' => [[17.0, -68.5], [19.5, -64.0]],
            'station_bounds' => [[17.0, -68.5], [19.5, -64.0]],
        ],
    ];

    /**
     * Preferred precipitation-related layers in order.
     */
    private const LAYER_CANDIDATES = ['qpf', 'pop12', 'pop', 'wx'];

    public function getFutureFrames(float $stationLat, float $stationLon): array
    {
        $regionKey = $this->detectRegion($stationLat, $stationLon);
        if (!$regionKey) {
            return [
                'success' => false,
                'provider' => 'noaa',
                'frames' => [],
                'message' => 'Station is outside NOAA NDFD regional coverage',
            ];
        }

        $region = self::REGIONS[$regionKey];
        $capabilities = $this->loadCapabilities($regionKey);
        if (!$capabilities['success']) {
            return [
                'success' => false,
                'provider' => 'noaa',
                'frames' => [],
                'message' => $capabilities['message'] ?? 'NOAA capabilities unavailable',
            ];
        }

        $layer = $this->pickLayer($regionKey, $capabilities['layers']);
        if (!$layer) {
            return [
                'success' => false,
                'provider' => 'noaa',
                'frames' => [],
                'message' => 'No suitable NOAA precipitation layer found',
            ];
        }

        $layerMeta = $capabilities['layers'][$layer] ?? [];
        $times = $this->extractFutureTimes($layerMeta['times'] ?? []);
        if (count($times) === 0) {
            return [
                'success' => false,
                'provider' => 'noaa',
                'frames' => [],
                'message' => 'No future NOAA valid times available',
            ];
        }

        // Keep requested WMS extent equal to rendered overlay extent.
        // Using layer-provided bbox (often full-domain) can make precipitation appear tiny/misaligned.
        $bbox = $this->latLonBoundsToWebMercatorBounds($region['bounds']);
        $bboxString = implode(',', [
            $bbox[0],
            $bbox[1],
            $bbox[2],
            $bbox[3],
        ]);

        $frames = [];
        foreach ($times as $time) {
            $frames[] = [
                'provider' => 'noaa',
                'kind' => 'image_overlay',
                'time' => $time,
                'timestamp' => $this->parseTimestamp($time),
                'url' => $this->buildGetMapUrl($region['endpoint'], $layer, $time, $bboxString),
                'requires_proxy' => true,
                'bounds' => $region['bounds'],
                'attribution' => 'NOAA NWS NDFD',
                'opacity' => 0.7,
            ];
        }

        return [
            'success' => true,
            'provider' => 'noaa',
            'frames' => $frames,
            'message' => null,
        ];
    }

    public function isStationInCoverage(float $stationLat, float $stationLon): bool
    {
        return $this->detectRegion($stationLat, $stationLon) !== null;
    }

    private function detectRegion(float $lat, float $lon): ?string
    {
        foreach (self::REGIONS as $regionKey => $region) {
            [$sw, $ne] = $region['station_bounds'];
            if ($lat >= $sw[0] && $lat <= $ne[0] && $lon >= $sw[1] && $lon <= $ne[1]) {
                return $regionKey;
            }
        }

        return null;
    }

    private function loadCapabilities(string $regionKey): array
    {
        $cacheKey = "noaa_ndfd_capabilities_{$regionKey}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['layers'])) {
            return [
                'success' => true,
                'layers' => $cached['layers'],
                'message' => null,
            ];
        }

        $endpoint = self::REGIONS[$regionKey]['endpoint'] ?? null;
        if (!is_string($endpoint) || $endpoint === '') {
            return [
                'success' => false,
                'layers' => [],
                'message' => 'Unknown NOAA region endpoint',
            ];
        }

        try {
            $response = Http::timeout(12)->retry(1, 300)->get($endpoint, [
                'REQUEST' => 'GetCapabilities',
                'SERVICE' => 'WMS',
            ]);

            if (!$response->ok()) {
                return [
                    'success' => false,
                    'layers' => [],
                    'message' => "NOAA capabilities HTTP {$response->status()}",
                ];
            }

            $layers = $this->parseCapabilitiesLayers($response->body());
            Cache::put($cacheKey, ['layers' => $layers], now()->addMinutes(self::CAPABILITIES_CACHE_MINUTES));

            return [
                'success' => true,
                'layers' => $layers,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('NOAA future frames capabilities fetch failed', [
                'region' => $regionKey,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'layers' => [],
                'message' => 'Failed to fetch NOAA capabilities',
            ];
        }
    }

    /**
     * @return array<string, array{times: array<int, string>, bbox_3857: array<int, float>|null}>
     */
    private function parseCapabilitiesLayers(string $xml): array
    {
        $layers = [];
        if (trim($xml) === '') {
            return $layers;
        }

        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);
        if (!$root) {
            libxml_clear_errors();
            return $layers;
        }

        $capability = $root->Capability ?? null;
        if (!$capability || !$capability->Layer) {
            libxml_clear_errors();
            return $layers;
        }

        foreach ($capability->Layer as $layerNode) {
            $this->collectLayers($layerNode, $layers);
        }

        libxml_clear_errors();
        return $layers;
    }

    /**
     * @param array<string, array{times: array<int, string>, bbox_3857: array<int, float>|null}> $layers
     */
    private function collectLayers(\SimpleXMLElement $layerNode, array &$layers): void
    {
        $name = trim((string) ($layerNode->Name ?? ''));
        if ($name !== '') {
            $layers[$name] = [
                'times' => $this->extractVtitDimensionValues($layerNode),
                'bbox_3857' => $this->extractBbox3857($layerNode),
            ];
        }

        foreach ($layerNode->Layer as $childLayer) {
            $this->collectLayers($childLayer, $layers);
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractVtitDimensionValues(\SimpleXMLElement $layerNode): array
    {
        $values = [];
        foreach ($layerNode->Dimension as $dimension) {
            $name = strtolower(trim((string) ($dimension['name'] ?? '')));
            if ($name !== 'vtit') {
                continue;
            }

            $raw = trim((string) $dimension);
            if ($raw === '') {
                continue;
            }

            $parts = explode(',', $raw);
            foreach ($parts as $part) {
                $candidate = trim($part);
                if ($candidate !== '') {
                    $values[] = $candidate;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return array<int, float>|null
     */
    private function extractBbox3857(\SimpleXMLElement $layerNode): ?array
    {
        foreach ($layerNode->BoundingBox as $bboxNode) {
            $srs = strtoupper((string) ($bboxNode['SRS'] ?? $bboxNode['CRS'] ?? ''));
            if ($srs !== 'EPSG:3857' && $srs !== 'EPSG:900913') {
                continue;
            }

            $minX = (float) ($bboxNode['minx'] ?? 0.0);
            $minY = (float) ($bboxNode['miny'] ?? 0.0);
            $maxX = (float) ($bboxNode['maxx'] ?? 0.0);
            $maxY = (float) ($bboxNode['maxy'] ?? 0.0);
            if ($minX === 0.0 && $maxX === 0.0) {
                continue;
            }

            return [$minX, $minY, $maxX, $maxY];
        }

        return null;
    }

    /**
     * @param array<string, array{times: array<int, string>, bbox_3857: array<int, float>|null}> $layers
     */
    private function pickLayer(string $regionKey, array $layers): ?string
    {
        foreach (self::LAYER_CANDIDATES as $suffix) {
            $candidate = "ndfd.{$regionKey}.{$suffix}";
            if (isset($layers[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $times
     * @return array<int, string>
     */
    private function extractFutureTimes(array $times): array
    {
        $nowTs = now()->subMinutes(5)->timestamp;
        $rows = [];

        foreach ($times as $time) {
            $ts = $this->parseTimestamp($time);
            if (!is_int($ts)) {
                continue;
            }
            if ($ts < $nowTs) {
                continue;
            }
            $rows[] = [
                'time' => $time,
                'ts' => $ts,
            ];
        }

        usort($rows, static fn (array $a, array $b) => $a['ts'] <=> $b['ts']);
        $rows = array_slice($rows, 0, self::MAX_FUTURE_FRAMES);

        return array_values(array_map(static fn (array $row) => $row['time'], $rows));
    }

    /**
     * @param array<int, array<int, float>> $latLonBounds
     * @return array<int, float>
     */
    private function latLonBoundsToWebMercatorBounds(array $latLonBounds): array
    {
        $sw = $latLonBounds[0] ?? [0.0, 0.0];
        $ne = $latLonBounds[1] ?? [0.0, 0.0];

        [$swX, $swY] = $this->latLonToWebMercator((float) ($sw[0] ?? 0.0), (float) ($sw[1] ?? 0.0));
        [$neX, $neY] = $this->latLonToWebMercator((float) ($ne[0] ?? 0.0), (float) ($ne[1] ?? 0.0));

        return [$swX, $swY, $neX, $neY];
    }

    /**
     * Returns [x, y] in EPSG:3857 from lat/lon.
     */
    private function latLonToWebMercator(float $lat, float $lon): array
    {
        $originShift = 20037508.34;
        $x = $lon * $originShift / 180.0;
        $y = log(tan((90.0 + $lat) * M_PI / 360.0)) / (M_PI / 180.0);
        $y = $y * $originShift / 180.0;

        return [$x, $y];
    }

    private function buildGetMapUrl(string $endpoint, string $layerName, string $vtit, string $bbox): string
    {
        $query = http_build_query([
            'LAYERS' => $layerName,
            'FORMAT' => 'image/png',
            'TRANSPARENT' => 'TRUE',
            'VERSION' => '1.3.0',
            'VTIT' => $vtit,
            'EXCEPTIONS' => 'INIMAGE',
            'SERVICE' => 'WMS',
            'REQUEST' => 'GetMap',
            'STYLES' => '',
            'CRS' => 'EPSG:3857',
            'WIDTH' => 1024,
            'HEIGHT' => 1024,
            'BBOX' => $bbox,
        ], '', '&', PHP_QUERY_RFC3986);

        return "{$endpoint}?{$query}";
    }

    private function parseTimestamp(string $time): ?int
    {
        try {
            return Carbon::parse($time)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
