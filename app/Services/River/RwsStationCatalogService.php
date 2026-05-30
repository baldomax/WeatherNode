<?php

namespace App\Services\River;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the live RWS station catalog from waterinfo.rws.nl and filters
 * it to river water-level gauge stations. Cached 24 h.
 *
 * Use this instead of the hardcoded STATIONS constant so that new RWS
 * stations become available in the admin without a code deployment.
 */
class RwsStationCatalogService
{
    private const CATALOG_URL  = 'https://waterinfo.rws.nl/api/locationslug/getall';
    public  const CACHE_KEY    = 'rws_station_catalog_river';
    private const CACHE_HOURS  = 24;

    /**
     * Keywords in a station code OR slug that suggest it is a river gauge.
     * City names are included for well-known stations whose codes do not
     * contain a river name (e.g. "deventer" → IJssel).
     */
    private const INCLUDE_KEYWORDS = [
        // River / waterway names
        'waal', 'maas', 'rijn', 'ijssel', 'lek', 'merwede', 'amstel', 'hollandsche',
        // Rhine entry & bifurcation points
        'lobith', 'tolkamer', 'pannerden',
        // Neder-Rijn / Waal corridor
        'arnhem', 'nijmegen', 'tiel', 'zaltbommel', 'andel', 'herwijnen', 'gorinchem',
        'dodewaard', 'driel',
        // Benedenmerwede / delta
        'hardinxveld', 'sliedrecht', 'papendrecht', 'dordrecht',
        // Nieuwe Maas / IJ
        'brienenoordbrug', 'schellingwouderbrug',
        // Maas — Limburg → Brabant
        'maastricht', 'borgharen', 'eijsden', 'elsloo', 'roermond', 'venlo',
        'heel', 'gennep', 'grave', 'lith',
        // IJssel corridor
        'deventer', 'dieren', 'doesburg', 'zwolle', 'kampen', 'genemuiden',
        // Lek
        'culemborg', 'hagestein',
        // Hollandsche IJssel
        'gouda',
        // Major port cities with river gauges
        'rotterdam', 'amsterdam',
    ];

    /**
     * Substrings in a code that indicate it is NOT a free-flowing river gauge
     * (lock chambers, canals, recreational lakes, drinking-water intakes).
     */
    private const EXCLUDE_PATTERNS = [
        '.kanaal', '.sluis.', '.plas', '.baggergat', '.drinkwater',
        'julianakanaal', 'beatrixkanaal',
    ];

    /**
     * Keyword → river label (checked in order — most specific first).
     */
    private const RIVER_MAP = [
        'hollandscheijssel'    => 'Hollandsche IJssel',
        'bovenrijn'            => 'Rijn',
        'bovenryn'             => 'Rijn',
        'nederrijn'            => 'Neder-Rijn',
        'merwede'              => 'Merwede',
        'brienenoordbrug'      => 'Nieuwe Maas',
        'schellingwouderbrug'  => 'IJ',
        'ijssel'               => 'IJssel',
        'waal'                 => 'Waal',
        'maas'                 => 'Maas',
        'amstel'               => 'Amstel',
        'lek'                  => 'Lek',
        'rijn'                 => 'Rijn',
    ];

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Return the cached river station list.
     * Format: ['station-code' => ['name' => '…', 'river' => '…'], …]
     */
    public function getRiverStations(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHours(self::CACHE_HOURS),
            fn () => $this->fetchAndFilter()
        );
    }

    /**
     * Invalidate the cache and fetch a fresh catalog immediately.
     */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getRiverStations();
    }

    /** When the cached catalog was last fetched (null = not yet cached). */
    public function cachedAt(): ?\Carbon\Carbon
    {
        // Laravel cache doesn't expose TTL timestamps directly — we store one ourselves
        $ts = Cache::get(self::CACHE_KEY . '_fetched_at');
        return $ts ? \Carbon\Carbon::createFromTimestamp($ts) : null;
    }

    // ── Internal ───────────────────────────────────────────────────────────────

    private function fetchAndFilter(): array
    {
        // Record fetch timestamp for display in admin
        Cache::put(self::CACHE_KEY . '_fetched_at', now()->timestamp, now()->addHours(self::CACHE_HOURS + 1));

        try {
            $response = Http::timeout(15)->get(self::CATALOG_URL);

            if (!$response->successful()) {
                Log::warning('RWS catalog fetch failed', ['status' => $response->status()]);
                return $this->hardcodedFallback();
            }

            $entries = $response->json();
            if (!is_array($entries) || empty($entries)) {
                return $this->hardcodedFallback();
            }

            $stations = [];

            foreach ($entries as $entry) {
                $code = strtolower(trim($entry['code'] ?? ''));
                $slug = $entry['slug'] ?? '';

                if (!$code) {
                    continue;
                }

                // Exclude lock chambers, canals, lakes
                foreach (self::EXCLUDE_PATTERNS as $excl) {
                    if (str_contains($code, $excl)) {
                        continue 2;
                    }
                }

                // Must match at least one river keyword
                $haystack = strtolower($code . ' ' . $slug);
                $matched  = false;
                foreach (self::INCLUDE_KEYWORDS as $kw) {
                    if (str_contains($haystack, $kw)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }

                $stations[$code] = [
                    'name'  => $this->parseName($slug) ?: $code,
                    'river' => $this->detectRiver($haystack),
                ];
            }

            // Always include the hardcoded verified stations (fills any gaps from catalog)
            $stations = array_merge($this->hardcodedFallback(), $stations);

            if (empty($stations)) {
                return $this->hardcodedFallback();
            }

            uasort($stations, fn ($a, $b) =>
                ($a['river'] <=> $b['river']) ?: ($a['name'] <=> $b['name'])
            );

            return $stations;

        } catch (\Exception $e) {
            Log::warning('RWS catalog fetch exception', ['error' => $e->getMessage()]);
            return $this->hardcodedFallback();
        }
    }

    /**
     * Strip all parenthetical abbreviations from a slug.
     * "Lobith(LBTH)"                    → "Lobith"
     * "Schellingwouderbrug(SCHWB)-2"     → "Schellingwouderbrug-2"
     * "Amsterdam-Schellingwouderbrug(X)" → "Amsterdam-Schellingwouderbrug"
     */
    private function parseName(string $slug): string
    {
        return trim(preg_replace('/\s*\([^)]+\)/', '', $slug));
    }

    private function detectRiver(string $haystack): string
    {
        foreach (self::RIVER_MAP as $kw => $label) {
            if (str_contains($haystack, $kw)) {
                return $label;
            }
        }
        return 'Overig';
    }

    /** Fall back to the hardcoded STATIONS constant when the catalog is unavailable. */
    private function hardcodedFallback(): array
    {
        return array_map(
            fn ($meta) => ['name' => $meta['name'], 'river' => $meta['river']],
            RijkswaterstaatRiverService::STATIONS
        );
    }
}
