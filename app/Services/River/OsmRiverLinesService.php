<?php

namespace App\Services\River;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches river-line geometry (for drawing the actual river course on the
 * hydrometric map, not just the station markers) from OpenStreetMap via the
 * public Overpass API, filtered to only the river names we actually care
 * about (the "river" field already attached to each CFR Toscana station).
 *
 * This mirrors what meteogruppo.eu does on https://www.meteogruppo.eu/livello-fiumi/
 * (confirmed by inspecting their fiumi-linee.js -- they fetch OSM waterway
 * geometry server-side too and label the popup "Tracciato OpenStreetMap").
 *
 * Results are cached indefinitely per unique set of river names, and also
 * persisted to a local JSON file, since river courses essentially never
 * change and Overpass is a shared public service that should not be hit
 * more than strictly necessary.
 *
 * Tuscany/CFR-Toscana-specific -- fork only, not proposed upstream.
 */
class OsmRiverLinesService
{
    public const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    // Tuscany bounding box: south, west, north, east.
    private const TUSCANY_BBOX = '42.2,9.7,44.5,12.1';

    private const CACHE_KEY_PREFIX = 'osm_river_lines_';

    /**
     * Return a GeoJSON FeatureCollection of LineStrings for the given river
     * names, within Tuscany. Empty/placeholder names ("--") are ignored.
     */
    public function getRiverLines(array $riverNames): array
    {
        $riverNames = $this->normalizeNames($riverNames);
        if (empty($riverNames)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $hash      = md5(implode(',', $riverNames));
        $cacheKey  = self::CACHE_KEY_PREFIX . $hash;
        $cacheFile = "osm_river_lines_{$hash}.json";

        return Cache::rememberForever($cacheKey, function () use ($riverNames, $cacheFile) {
            if (Storage::exists($cacheFile)) {
                $decoded = json_decode(Storage::get($cacheFile), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            $geojson = $this->fetchFromOverpass($riverNames);
            if (!empty($geojson['features'])) {
                Storage::put($cacheFile, json_encode($geojson));
            }

            return $geojson;
        });
    }

    // ── Internal ─────────────────────────────────────────────────────────

    private function normalizeNames(array $names): array
    {
        $names = array_values(array_unique(array_filter(
            $names,
            fn ($n) => is_string($n) && $n !== '' && $n !== '—' && $n !== '--'
        )));
        sort($names);
        return $names;
    }

    /**
     * CFR Toscana's own "river" field uses short names (e.g. "Cecina",
     * "Era", "Scolmatore"), but OpenStreetMap tags watercourses with the
     * full local name (e.g. "Fiume Cecina", "Fiume Era", "Canale
     * Scolmatore" / "Scolmatore dell'Arno"). Build one anchored,
     * case-insensitive alternative per plausible OSM spelling so we match
     * the actual watercourse and not an unrelated tributary that merely
     * contains the same word (e.g. "Torrente Era Viva" must NOT match "Era").
     */
    private function fetchFromOverpass(array $riverNames): array
    {
        $patterns = [];
        foreach ($riverNames as $name) {
            $n = preg_quote($name, '/');
            $patterns[] = "^{$n}$";
            $patterns[] = "^Fiume {$n}$";
            $patterns[] = "^Torrente {$n}$";
            $patterns[] = "^Canale {$n}$";
            $patterns[] = "^{$n} dell'Arno$";
            $patterns[] = "^{$n} d'Arno$";
        }
        $nameRegex = implode('|', $patterns);
        $bbox      = self::TUSCANY_BBOX;

        $query = '[out:json][timeout:25];'
            . '(way["waterway"~"river|canal|stream"]["name"~"' . $nameRegex . '",i](' . $bbox . ');'
            . ');'
            . 'out geom;';

        try {
            $response = Http::timeout(60)
                ->withHeaders(['User-Agent' => 'WeatherNode/1.0 (+river-levels; meteo.htt.it)'])
                ->asForm()
                ->post(self::OVERPASS_URL, ['data' => $query]);

            if (!$response->successful()) {
                Log::warning('OSM river lines fetch failed', ['status' => $response->status()]);
                return ['type' => 'FeatureCollection', 'features' => []];
            }

            return $this->toGeoJson($response->json() ?? []);
        } catch (\Exception $e) {
            Log::warning('OSM river lines fetch exception', ['error' => $e->getMessage()]);
            return ['type' => 'FeatureCollection', 'features' => []];
        }
    }

    private function toGeoJson(array $overpassData): array
    {
        $features = [];

        foreach ($overpassData['elements'] ?? [] as $el) {
            if (($el['type'] ?? null) !== 'way' || empty($el['geometry'])) {
                continue;
            }

            $coords = [];
            foreach ($el['geometry'] as $pt) {
                if (isset($pt['lon'], $pt['lat'])) {
                    $coords[] = [$pt['lon'], $pt['lat']];
                }
            }

            if (count($coords) < 2) {
                continue;
            }

            $features[] = [
                'type'       => 'Feature',
                'properties' => ['name' => $el['tags']['name'] ?? null],
                'geometry'   => ['type' => 'LineString', 'coordinates' => $coords],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
