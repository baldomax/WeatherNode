<?php

namespace App\Services\River;

use Illuminate\Support\Facades\Log;

/**
 * Centro Funzionale Regionale (CFR) della Toscana — Tuscan river gauge service.
 *
 * Tuscany's regional flood-monitoring authority does not publish a JSON/REST
 * API for its hydrometric network (unlike Rijkswaterstaat's WaterWebservices).
 * The only public real-time source is an HTML page listing all ~190 gauge
 * stations across the region. CfrToscanaStationCatalogService fetches and
 * parses that page; this class turns the parsed rows into the shape the
 * Rivers tab expects.
 *
 * Levels are reported in metres above each station's own "zero idrometrico"
 * (local hydrometric zero) — there is no single regional datum equivalent to
 * NAP, so levels from different stations are NOT directly comparable to each
 * other. Values are converted to centimetres here only to match the
 * provider-agnostic 'level_cm' contract; the datum_label field tells the view
 * this is a local zero, not NAP.
 *
 * Unlike RWS (trend/status inferred from a raw observation series), CFR
 * Toscana publishes official alert thresholds (soglia 1 = attenzione,
 * soglia 2 = guardia/allerta) and hourly deltas (Δh1/Δh3/Δh6) directly, so
 * both trend and status are derived from that authoritative data instead.
 *
 * Source: https://www.cfr.toscana.it/monitoraggio/stazioni.php?type=idro
 */
class CfrToscanaRiverService
{
    /**
     * A handful of well-known stations to pre-select on first enable, one
     * per river the admin is most likely to care about. The full catalog
     * (~190 stations across every Tuscan river/canal) is searchable in the
     * admin station picker — see CfrToscanaStationCatalogService.
     */
    public const DEFAULT_STATIONS = [
        'TOS01004679', // Arno — Firenze Uffizi
        'TOS03005435', // Cecina — Cecina SP39 (near the river mouth)
        'TOS01005342', // Scolmatore — Stagno
    ];

    public function __construct(
        private readonly CfrToscanaStationCatalogService $catalog = new CfrToscanaStationCatalogService()
    ) {
    }

    /**
     * @param  string[]  $stationCodes  CFR station codes (e.g. "TOS01004679")
     * @param  array     $extraMeta     Metadata for custom codes not in the catalog
     * @return array<string, array>     Keyed by station code
     */
    public function fetch(array $stationCodes, array $extraMeta = []): array
    {
        $results = [];

        try {
            $liveRows = $this->catalog->fetchAndParse();
        } catch (\Exception $e) {
            Log::warning('CFR Toscana River: page fetch failed', ['error' => $e->getMessage()]);
            $liveRows = [];
        }

        foreach ($stationCodes as $code) {
            $row = $liveRows[$code] ?? null;

            if ($row === null) {
                // Not in this fetch (site hiccup, or a custom/renamed code) —
                // fall back to whatever name/river metadata we have so the
                // card still shows something instead of vanishing entirely.
                $meta = $extraMeta[$code] ?? null;
                $results[$code] = [
                    'name'         => $meta['name'] ?? $code,
                    'river'        => $meta['river'] ?? '—',
                    'station_code' => $code,
                    'level_cm'     => null,
                    'trend'        => 'steady',
                    'status'       => 'normal',
                    'series'       => [],
                    'updated_at'   => now()->toIso8601String(),
                    'datum_label'  => 'cm s.z.i.',
                ];
                continue;
            }

            $levelM = $row['level_m'];

            $results[$code] = [
                'name'         => $row['name'],
                'river'        => $row['river'],
                'station_code' => $code,
                'level_cm'     => $levelM !== null ? round($levelM * 100, 0) : null,
                'trend'        => $this->determineTrend($row),
                'status'       => $this->determineStatus($row),
                'series'       => $this->buildApproxSeries($row),
                'updated_at'   => now()->toIso8601String(),
                // Local hydrometric zero — NOT comparable across stations, unlike NAP.
                'datum_label'  => 'cm s.z.i.',
                'meta'         => [
                    'province'      => $row['province'],
                    'alert_zone'    => $row['alert_zone'],
                    'threshold_1_m' => $row['threshold_1_m'],
                    'threshold_2_m' => $row['threshold_2_m'],
                    'discharge_m3s' => $row['discharge_m3s'],
                    'source_updated' => $row['updated_raw'],
                ],
            ];
        }

        return $results;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /** Prefer the source's own trend arrow; fall back to the 1h delta. */
    private function determineTrend(array $row): string
    {
        if ($row['arrow'] === 'up') {
            return 'rising';
        }
        if ($row['arrow'] === 'down') {
            return 'falling';
        }

        $delta = $row['delta_1h_m'];
        if ($delta === null) {
            return 'steady';
        }
        if ($delta > 0.02) {
            return 'rising';
        }
        if ($delta < -0.02) {
            return 'falling';
        }

        return 'steady';
    }

    /** Compare the live level against CFR's own published alert thresholds. */
    private function determineStatus(array $row): string
    {
        $level = $row['level_m'];
        $t1    = $row['threshold_1_m']; // soglia 1 — attenzione
        $t2    = $row['threshold_2_m']; // soglia 2 — guardia/allerta (più severa)

        if ($level === null || ($t1 === null && $t2 === null)) {
            return 'normal';
        }

        if ($t2 !== null && $level >= $t2) {
            return 'warning';
        }
        if ($t1 !== null && $level >= $t1) {
            return 'watch';
        }

        return 'normal';
    }

    /**
     * The source page has no history endpoint, but it does publish 1h/3h/6h
     * deltas — enough to sketch an approximate recent series for a sparkline,
     * anchored on the current reading.
     */
    private function buildApproxSeries(array $row): array
    {
        $level = $row['level_m'];
        if ($level === null) {
            return [];
        }

        $now     = now();
        $offsets = [
            6 => $row['delta_6h_m'],
            3 => $row['delta_3h_m'],
            1 => $row['delta_1h_m'],
        ];

        $points = [];
        foreach ($offsets as $hoursAgo => $delta) {
            if ($delta === null) {
                continue;
            }
            $ts       = $now->copy()->subHours($hoursAgo);
            $points[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'value'          => round(($level - $delta) * 100, 0),
            ];
        }

        $points[] = [
            'timestamp'      => $now->toIso8601String(),
            'timestamp_unix' => $now->timestamp * 1000,
            'value'          => round($level * 100, 0),
        ];

        usort($points, fn ($a, $b) => $a['timestamp_unix'] <=> $b['timestamp_unix']);

        return $points;
    }
}
