<?php

namespace App\Services\River;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and parses the live hydrometric station table published by the
 * Centro Funzionale Regionale (CFR) della Toscana — Italy's regional flood
 * monitoring authority for Tuscany.
 *
 * Unlike the RWS catalog (a JSON API separate from live readings), CFR
 * Toscana publishes ONE HTML page that already contains everything: station
 * identity, river, province, alert thresholds AND the current reading. So
 * this class both serves as the "catalog" (station identity, cached 24h,
 * used by the admin picker) and does the raw HTML parsing that
 * CfrToscanaRiverService reuses for live values (parsed fresh on every
 * call — the page itself is small and updates every few minutes).
 *
 * Source: https://www.cfr.toscana.it/monitoraggio/stazioni.php?type=idro
 * No official API/JSON is published for this network; the page is scraped
 * because it is the only public real-time source for Tuscan river gauges.
 */
class CfrToscanaStationCatalogService
{
    public const PAGE_URL   = 'https://www.cfr.toscana.it/monitoraggio/stazioni.php?type=idro';
    public const CACHE_KEY  = 'cfr_toscana_station_catalog_river';
    private const CACHE_HOURS = 24;

    // ── Public API (catalog contract — mirrors RwsStationCatalogService) ───────

    /**
     * Return the cached river station list.
     * Format: ['station-code' => ['name' => '…', 'river' => '…'], …]
     */
    public function getRiverStations(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHours(self::CACHE_HOURS),
            function () {
                $full = $this->fetchAndParse();
                return array_map(
                    fn ($row) => ['name' => $row['name'], 'river' => $row['river']],
                    $full
                );
            }
        );
    }

    /** Invalidate the cache and fetch a fresh catalog immediately. */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getRiverStations();
    }

    /** When the cached catalog was last fetched (null = not yet cached). */
    public function cachedAt(): ?\Carbon\Carbon
    {
        $ts = Cache::get(self::CACHE_KEY . '_fetched_at');
        return $ts ? \Carbon\Carbon::createFromTimestamp($ts) : null;
    }

    // ── Live data (used by CfrToscanaRiverService) ──────────────────────────────

    /**
     * Fetch and parse the full station table with LIVE readings included.
     * Not cached at this layer — callers (CfrToscanaRiverService,
     * PollExternalData) already sit behind the provider-level 15–30 min cache.
     *
     * Format per row:
     *   [
     *     'name'          => string,
     *     'river'         => string,   // "Fiume" column, e.g. "Arno", "Cecina", "Scolmatore"
     *     'province'      => string,   // e.g. "FI"
     *     'alert_zone'    => string,   // e.g. "A3"
     *     'threshold_1_m' => float|null,  // "liv.1" — soglia di attenzione
     *     'threshold_2_m' => float|null,  // "liv.2" — soglia di guardia/allerta (più severa)
     *     'level_m'       => float|null,  // current level, metres above the local hydrometric zero ("m szi")
     *     'discharge_m3s' => float|null,
     *     'delta_1h_m'    => float|null,
     *     'delta_3h_m'    => float|null,
     *     'delta_6h_m'    => float|null,
     *     'arrow'         => 'up'|'down'|null,  // trend glyph shown next to the level on the source page
     *     'updated_raw'   => string,   // e.g. "10/09 22.05" (site's own local-time label)
     *   ]
     */
    public function fetchAndParse(): array
    {
        Cache::put(self::CACHE_KEY . '_fetched_at', now()->timestamp, now()->addHours(self::CACHE_HOURS + 1));

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'WeatherNode/1.0 (+river-levels)'])
                ->get(self::PAGE_URL);

            if (!$response->successful()) {
                Log::warning('CFR Toscana catalog fetch failed', ['status' => $response->status()]);
                return [];
            }

            return $this->parseHtml($response->body());
        } catch (\Exception $e) {
            Log::warning('CFR Toscana catalog fetch exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Internal ───────────────────────────────────────────────────────────────

    private function parseHtml(string $html): array
    {
        // The live values are NOT in the static <table> markup (that's an
        // empty DataTables shell populated client-side by JS after load).
        // They're passed to a "show(_values)" function as a per-request
        // obfuscated array variable: "<varname>[N] = new Array(...)". The
        // variable name changes on every page load, so detect it dynamically
        // instead of hardcoding one.
        if (!preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)\[0\]\s*=\s*new Array\(/', $html, $varMatch)) {
            Log::warning('CFR Toscana catalog: obfuscated data variable not found in page');
            return [];
        }
        $varName = $varMatch[1];

        $pattern = '/' . preg_quote($varName, '/') . '\[(\d+)\]\s*=\s*new Array\(([^;]*)\);/';
        if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $stations = [];

        foreach ($matches as $match) {
            $fields = $this->parseArrayArgs($match[2]);
            if (count($fields) < 14) {
                continue;
            }

            $code = trim($fields[0] ?? '');
            if ($code === '') {
                continue;
            }

            $stations[$code] = [
                'name'          => trim($fields[2] ?? '') ?: $code,
                'river'         => trim($fields[1] ?? '') ?: '—',
                'province'      => trim($fields[3] ?? ''),
                'alert_zone'    => trim($fields[5] ?? ''),
                'threshold_2_m' => $this->toFloat($fields[6] ?? null),
                'threshold_1_m' => $this->toFloat($fields[7] ?? null),
                'level_m'       => $this->toFloat($fields[8] ?? null),
                'discharge_m3s' => $this->toFloat($fields[9] ?? null),
                'delta_1h_m'    => $this->toFloat($fields[10] ?? null),
                'delta_3h_m'    => $this->toFloat($fields[11] ?? null),
                'delta_6h_m'    => $this->toFloat($fields[12] ?? null),
                // The source shows status via a background colour, not an
                // arrow glyph — no reliable up/down indicator to parse here.
                // CfrToscanaRiverService falls back to the 1h delta instead.
                'arrow'         => null,
                'updated_raw'   => trim($fields[13] ?? ''),
            ];
        }

        return $stations;
    }

    /**
     * Parse the comma-separated, double-quoted argument list passed to
     * "new Array(...)" back into a plain string array. Arguments are always
     * double-quoted (numbers included, e.g. "2.50"), never escaped.
     */
    private function parseArrayArgs(string $argsRaw): array
    {
        preg_match_all('/"([^"]*)"/', $argsRaw, $m);
        return $m[1];
    }

    private function toFloat(?string $raw): ?float
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '-' || $raw === '--') {
            return null;
        }
        // Values use a dot decimal separator on this page (e.g. "1.45", "-6.21").
        if (!is_numeric($raw)) {
            return null;
        }
        return (float) $raw;
    }
}
