<?php

namespace App\Services\River;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches geographic coordinates for CFR Toscana hydrometric stations from
 * the Tuscany Region's own GIS service (SIR Toscana — Servizio Idrologico
 * Regionale, a sister portal to cfr.toscana.it). CFR Toscana's own station
 * page (scraped by CfrToscanaStationCatalogService) carries no coordinates
 * at all, so this is a separate, purely-geographic data source joined to
 * our existing station codes.
 *
 * Source: SIR Toscana GeoServer WFS, layer "cf_idrometri".
 * https://geo.sir.toscana.it/geoserver/geo/ows
 *
 * Station locations essentially never change, so this is cached to a local
 * JSON file (not just the request cache) and only re-fetched when the
 * `rivers:sync-cfr-toscana-coordinates` command is run manually or on a
 * schedule — not on every page load.
 *
 * NOTE: this is a Tuscany/CFR-Toscana-specific feature and is kept on the
 * fork only — not proposed upstream, same as the rest of the CFR Toscana
 * integration.
 */
class CfrToscanaStationCoordinatesService
{
    public const WFS_URL = 'https://geo.sir.toscana.it/geoserver/geo/ows'
        . '?service=WFS&version=1.1.0&request=GetFeature&typeName=geo:cf_idrometri'
        . '&outputFormat=application/json&srsName=EPSG:4326';

    private const CACHE_FILE = 'cfr_toscana_station_coordinates.json';
    private const CACHE_KEY  = 'cfr_toscana_station_coordinates';

    /**
     * Return coordinates keyed by station code.
     * Format: ['station-code' => ['lat' => float, 'lon' => float, 'nome' => string,
     *                              'comune' => string, 'zero_idrometrico' => float|null,
     *                              'quota' => float|null], …]
     */
    public function getCoordinates(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (Storage::exists(self::CACHE_FILE)) {
                $decoded = json_decode(Storage::get(self::CACHE_FILE), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            return $this->refresh();
        });
    }

    /** Force a fresh fetch from the WFS endpoint and overwrite the local cache file. */
    public function refresh(): array
    {
        $coordinates = $this->fetchAndParse();

        if (!empty($coordinates)) {
            Storage::put(self::CACHE_FILE, json_encode($coordinates));
            Cache::forget(self::CACHE_KEY);
            Cache::forever(self::CACHE_KEY, $coordinates);
        }

        return $coordinates;
    }

    // ── Internal ───────────────────────────────────────────────────────────

    private function fetchAndParse(): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'WeatherNode/1.0 (+river-levels)'])
                ->get(self::WFS_URL);

            if (!$response->successful()) {
                Log::warning('CFR Toscana station coordinates fetch failed', ['status' => $response->status()]);
                return [];
            }

            $data     = $response->json();
            $features = $data['features'] ?? [];

            $coordinates = [];
            foreach ($features as $feature) {
                $code = $feature['properties']['id_stazione'] ?? null;
                $lon  = $feature['geometry']['coordinates'][0] ?? null;
                $lat  = $feature['geometry']['coordinates'][1] ?? null;

                if (!$code || $lat === null || $lon === null) {
                    continue;
                }

                $coordinates[$code] = [
                    'lat'              => (float) $lat,
                    'lon'              => (float) $lon,
                    'nome'             => $feature['properties']['nome'] ?? $code,
                    'comune'           => $feature['properties']['comune'] ?? '',
                    'zero_idrometrico' => isset($feature['properties']['zero_idrometrico'])
                        ? (float) $feature['properties']['zero_idrometrico'] : null,
                    'quota'            => isset($feature['properties']['quota'])
                        ? (float) $feature['properties']['quota'] : null,
                ];
            }

            return $coordinates;
        } catch (\Exception $e) {
            Log::warning('CFR Toscana station coordinates fetch exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
