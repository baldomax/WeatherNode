<?php

namespace App\Services\Tide;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * NOAA Tides & Currents — CO-OPS API.
 *
 * Provides observed water levels and tide predictions for US coastal stations.
 * No API key required; only a valid station ID is needed.
 * Observations (past 12 h) are fetched separately from hourly predictions and
 * hi/lo extremes (next 72 h).
 *
 * API docs: https://tidesandcurrents.noaa.gov/web_services_info.html
 */
class NoaaSource extends AbstractTideSource
{
    private const API_BASE = 'https://api.tidesandcurrents.noaa.gov/api/prod/datagetter';

    public const STATIONS = [
        '8518750' => ['name' => 'The Battery, New York, NY'],
        '8443970' => ['name' => 'Boston, MA'],
        '8574680' => ['name' => 'Baltimore, MD'],
        '8638863' => ['name' => 'Chesapeake Bay Bridge Tunnel, VA'],
        '8665530' => ['name' => 'Charleston, SC'],
        '8724580' => ['name' => 'Key West, FL'],
        '8726520' => ['name' => 'St. Petersburg, FL'],
        '8761724' => ['name' => 'Grand Isle, LA'],
        '8771341' => ['name' => 'Galveston Pier 21, TX'],
        '9410660' => ['name' => 'Los Angeles, CA'],
        '9414290' => ['name' => 'San Francisco, CA'],
        '9447130' => ['name' => 'Seattle, WA'],
        '9455920' => ['name' => 'Anchorage, AK'],
        '1619910' => ['name' => 'Honolulu, HI'],
    ];

    public const DEFAULT_STATION = '8518750'; // The Battery, New York

    // ── Interface metadata ────────────────────────────────────────────────────

    public function getName(): string        { return 'NOAA Tides & Currents'; }
    public function getRegion(): string      { return 'US'; }
    public function getSourceKey(): string   { return 'noaa'; }
    public function isImplemented(): bool    { return true; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://tidesandcurrents.noaa.gov/web_services_info.html'; }
    public function getCoverageArea(): string { return 'United States (including territories)'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return self::STATIONS; }

    // ── Main fetch ────────────────────────────────────────────────────────────

    public function fetchTideData(string $stationCode = ''): array
    {
        $stationCode = $stationCode ?: self::DEFAULT_STATION;
        $stationName = self::STATIONS[$stationCode]['name'] ?? $stationCode;
        $now         = now();

        $common = [
            'station'     => $stationCode,
            'datum'       => 'MSL',
            'units'       => 'metric',
            'time_zone'   => 'GMT',
            'application' => 'weathernode',
            'format'      => 'json',
        ];

        // Observed water levels — not available at all stations; don't throw on missing data
        $observations = $this->fetchProduct(
            array_merge($common, [
                'product'    => 'water_level',
                'begin_date' => $now->copy()->subHours(12)->format('Ymd H:i'),
                'end_date'   => $now->format('Ymd H:i'),
            ]),
            'data',
            required: false
        );

        // Hourly predictions for the next 72 h
        $predictions = $this->fetchProduct(
            array_merge($common, [
                'product'    => 'predictions',
                'interval'   => 'h',
                'begin_date' => $now->format('Ymd H:i'),
                'end_date'   => $now->copy()->addHours(72)->format('Ymd H:i'),
            ]),
            'predictions'
        );

        // Hi/Lo tide predictions for the next 72 h
        $hiloRaw = $this->fetchProduct(
            array_merge($common, [
                'product'    => 'predictions',
                'interval'   => 'hilo',
                'begin_date' => $now->format('Ymd H:i'),
                'end_date'   => $now->copy()->addHours(72)->format('Ymd H:i'),
            ]),
            'predictions'
        );

        $series = $this->mergeAndSort(array_merge(
            $this->parsePoints($observations),
            $this->parsePoints($predictions),
        ));

        if (empty($series)) {
            throw new \RuntimeException("NOAA CO-OPS API returned no data for station {$stationCode}");
        }

        $nowMs        = $now->timestamp * 1000;
        $currentLevel = null;
        $currentTs    = null;

        foreach ($series as $point) {
            if ($point['timestamp_unix'] <= $nowMs) {
                $currentLevel = $point['value'];
                $currentTs    = $point['timestamp'];
            }
        }

        // Build tide events from hi/lo predictions
        $tides = [];
        foreach ($hiloRaw as $item) {
            $ts      = Carbon::parse($item['t'] . ' UTC');
            $tides[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'type'           => ($item['type'] ?? 'L') === 'H' ? 'high' : 'low',
                'level_cm'       => round((float) ($item['v'] ?? 0) * 100, 1),
            ];
        }

        return [
            'station'           => $stationName,
            'station_code'      => $stationCode,
            'current_level_cm'  => $currentLevel,
            'current_timestamp' => $currentTs,
            'trend'             => $this->determineTrend($series, $nowMs),
            'tides'             => $tides,
            'series'            => $series,
            'source'            => 'noaa',
            'updated_at'        => $now->toIso8601String(),
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Call a single NOAA CO-OPS product endpoint and return the items array.
     * When $required is false, an error response returns [] instead of throwing.
     */
    private function fetchProduct(array $params, string $dataKey, bool $required = true): array
    {
        $response = Http::timeout(15)->get(self::API_BASE, $params);

        if (!$response->successful()) {
            if (!$required) {
                return [];
            }
            throw new \RuntimeException(
                'NOAA CO-OPS API returned HTTP ' . $response->status()
                . " for product={$params['product']}"
            );
        }

        $data = $response->json();

        // NOAA returns HTTP 200 with {"error": {"message": "..."}} for invalid params
        if (!empty($data['error'])) {
            if (!$required) {
                return [];
            }
            throw new \RuntimeException(
                'NOAA CO-OPS API error: ' . ($data['error']['message'] ?? 'Unknown error')
            );
        }

        return $data[$dataKey] ?? [];
    }

    /**
     * Convert NOAA items ({"t": "YYYY-MM-DD HH:mm", "v": "metres"}) to series points.
     * All timestamps arrive in GMT and are parsed as UTC.
     */
    private function parsePoints(array $items): array
    {
        $points = [];
        foreach ($items as $item) {
            $val = $item['v'] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $ts       = Carbon::parse($item['t'] . ' UTC');
            $points[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'value'          => round((float) $val * 100, 1), // metres → cm
            ];
        }
        return $points;
    }
}
