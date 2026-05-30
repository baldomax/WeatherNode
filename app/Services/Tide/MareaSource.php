<?php

namespace App\Services\Tide;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Marea API tide source.
 *
 * Model-based global tide predictions, closest free alternative to WorldTides.
 * A free API key (registration required) unlocks higher rate limits.
 *
 * API docs: https://api.marea.ooo/doc
 */
class MareaSource extends AbstractTideSource
{
    private const API_BASE = 'https://api.marea.ooo/v2/tides';

    /** Total window in minutes: 12 h past + 72 h future = 84 h = 5040 min */
    private const DURATION_MINUTES = 5040;

    /** Interval in minutes between tide heights */
    private const INTERVAL_MINUTES = 10;

    // ── Interface metadata ────────────────────────────────────────────────────

    public function getName(): string        { return 'Marea'; }
    public function getRegion(): string      { return 'GLOBAL'; }
    public function getSourceKey(): string   { return 'marea'; }
    public function isImplemented(): bool    { return true; }
    public function requiresApiKey(): bool   { return true; }
    public function getApiDocUrl(): ?string  { return 'https://api.marea.ooo/doc'; }
    public function getCoverageArea(): string { return 'Global (harmonic tide model)'; }
    public function isStationBased(): bool   { return false; }
    public function getStations(): array     { return []; }

    // ── Main fetch ────────────────────────────────────────────────────────────

    public function fetchTideData(string $stationCode = ''): array
    {
        $apiKey    = Setting::getValue('tide.marea_api_key', '');
        $latitude  = (float) Setting::latitude();
        $longitude = (float) Setting::longitude();
        $now       = now();

        $params = [
            'duration'  => self::DURATION_MINUTES,
            'interval'  => self::INTERVAL_MINUTES,
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'datum'     => 'MSL',
            // Start 12 h in the past so the chart has history
            'timestamp' => $now->copy()->subHours(12)->timestamp,
        ];

        $request = Http::timeout(15);

        // Auth: x-marea-api-token header (required — 100 free requests on free plan)
        if (!empty($apiKey)) {
            $request = $request->withHeaders(['x-marea-api-token' => $apiKey]);
        }

        $response = $request->get(self::API_BASE, $params);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Marea API returned HTTP ' . $response->status()
            );
        }

        $data = $response->json();

        // Response uses 'heights' for the time series and 'extremes' for high/low events
        $heights  = $data['heights']  ?? [];
        $extremes = $data['extremes'] ?? [];

        if (empty($heights)) {
            throw new \RuntimeException(
                "Marea API returned no heights data for {$latitude}, {$longitude}"
            );
        }

        // Build series from heights (timestamp is Unix seconds, height is metres)
        $series = [];
        foreach ($heights as $item) {
            $ts       = Carbon::createFromTimestamp((int) $item['timestamp']);
            $series[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'value'          => round((float) $item['height'] * 100, 1), // metres → cm
            ];
        }

        $series = $this->mergeAndSort($series);

        if (empty($series)) {
            throw new \RuntimeException('Marea API: no valid data points after processing');
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

        // Use the extremes from the API directly instead of our own peak-detection
        $tides = [];
        foreach ($extremes as $item) {
            $ts      = Carbon::createFromTimestamp((int) $item['timestamp']);
            $tides[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'type'           => str_contains(strtoupper($item['state'] ?? ''), 'HIGH') ? 'high' : 'low',
                'level_cm'       => round((float) $item['height'] * 100, 1),
            ];
        }

        $locationName = number_format($latitude, 2) . '°N, ' . number_format($longitude, 2) . '°E';

        return [
            'station'           => $locationName,
            'station_code'      => "{$latitude},{$longitude}",
            'current_level_cm'  => $currentLevel,
            'current_timestamp' => $currentTs,
            'trend'             => $this->determineTrend($series, $nowMs),
            'tides'             => $tides,
            'series'            => $series,
            'source'            => 'marea',
            'updated_at'        => $now->toIso8601String(),
        ];
    }
}
