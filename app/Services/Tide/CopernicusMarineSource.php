<?php

namespace App\Services\Tide;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Copernicus Marine Service (CMEMS) tide source.
 *
 * Fetches sea surface height (zos, metres MSL) from the CMEMS NRT global
 * physics analysis/forecast product via the THREDDS OPeNDAP ASCII endpoint.
 *
 * Dataset : cmems_mod_glo_phy_anfc_0.083deg_PT1H-m
 * Variable: zos (sea surface height above geoid, metres)
 * Grid    : 1/12° (~8.3 km), global, hourly
 * Auth    : HTTP Basic (free account at https://marine.copernicus.eu)
 *
 * Two HTTP calls are made per poll:
 *   1. Fetch time[0] to determine the dataset's time epoch offset.
 *   2. Fetch zos + time for the 84-h window at the configured coordinates.
 *
 * API docs: https://marine.copernicus.eu/access-data
 */
class CopernicusMarineSource extends AbstractTideSource
{
    /** OPeNDAP ASCII base (no extension — .ascii is appended per request). */
    private const OPENDAP_BASE =
        'https://nrt.cmems-du.eu/thredds/dodsC/cmems_mod_glo_phy_anfc_0.083deg_PT1H-m';

    /** Unix timestamp of 1950-01-01T00:00:00Z (CMEMS time epoch). */
    private const EPOCH_1950_UNIX = -631152000;

    /**
     * Grid parameters for the 1/12° global product.
     *
     * lat: 2041 points from -80.000 to +90.000, step = 1/12
     * lon: 4320 points from -180.000 to +179.917, step = 1/12
     */
    private const LAT_MIN   = -80.0;
    private const LON_MIN   = -180.0;
    private const GRID_STEP = 1.0 / 12.0; // ≈ 0.08333°
    private const LAT_MAX_IDX = 2040;
    private const LON_MAX_IDX = 4319;

    /** NetCDF fill value threshold — values larger than this are masked/missing. */
    private const FILL_THRESHOLD = 1.0e+10;

    // ── Interface metadata ────────────────────────────────────────────────────

    public function getName(): string        { return 'Copernicus Marine (CMEMS)'; }
    public function getRegion(): string      { return 'EU'; }
    public function getSourceKey(): string   { return 'copernicus'; }
    public function isImplemented(): bool    { return true; }
    public function requiresApiKey(): bool   { return true; }
    public function getApiDocUrl(): ?string  { return 'https://marine.copernicus.eu/access-data'; }
    public function getCoverageArea(): string { return 'Europe + Global (model-based, 1/12° hourly)'; }
    public function isStationBased(): bool   { return false; }
    public function getStations(): array     { return []; }

    // ── Main fetch ────────────────────────────────────────────────────────────

    public function fetchTideData(string $stationCode = ''): array
    {
        $username  = Setting::getValue('tide.copernicus_username', '');
        $password  = Setting::getValue('tide.copernicus_password', '');
        $latitude  = Setting::marineLatitude();
        $longitude = Setting::marineLongitude();
        $now       = now();

        if (empty($username) || empty($password)) {
            throw new \RuntimeException(
                'Copernicus Marine credentials not configured. '
                . 'Register a free account at https://marine.copernicus.eu and enter '
                . 'your username and password in Settings → Tides.'
            );
        }

        // ── Step 1: get time[0] to compute the dataset's time index base ──────
        $time0 = $this->fetchTime0($username, $password);

        // ── Step 2: compute grid indices ──────────────────────────────────────
        $latIdx = (int) round(($latitude  - self::LAT_MIN) / self::GRID_STEP);
        $lonIdx = (int) round(($longitude - self::LON_MIN) / self::GRID_STEP);
        $latIdx = max(0, min(self::LAT_MAX_IDX, $latIdx));
        $lonIdx = max(0, min(self::LON_MAX_IDX, $lonIdx));

        // ── Step 3: compute time indices for -12 h … +72 h ───────────────────
        // CMEMS time is "hours since 1950-01-01T00:00:00Z" and index == offset in hours
        $nowHours   = ($now->timestamp - self::EPOCH_1950_UNIX) / 3600.0;
        $idxNow     = (int) round($nowHours - $time0);
        $idxStart   = max(0, $idxNow - 12);
        $idxEnd     = $idxNow + 72;

        // ── Step 4: fetch zos + time slice ────────────────────────────────────
        $url  = self::OPENDAP_BASE . ".ascii?zos[{$idxStart}:{$idxEnd}][{$latIdx}][{$lonIdx}]"
              . ",time[{$idxStart}:{$idxEnd}]";
        $body = $this->opendapGet($url, $username, $password);

        ['zos' => $zosValues, 'time' => $timeValues] = $this->parseDataResponse($body);

        if (empty($zosValues) || empty($timeValues)) {
            throw new \RuntimeException(
                "Copernicus Marine returned no zos data for {$latitude}, {$longitude}"
            );
        }

        // ── Build series ──────────────────────────────────────────────────────
        $series = [];
        $count  = min(count($zosValues), count($timeValues));

        for ($i = 0; $i < $count; $i++) {
            $zos = $zosValues[$i];

            if (abs($zos) > self::FILL_THRESHOLD) {
                continue; // masked / missing ocean cell
            }

            $unix     = $this->hoursToUnix($timeValues[$i]);
            $ts       = Carbon::createFromTimestamp($unix, 'UTC');
            $series[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $unix * 1000,
                'value'          => round($zos * 100, 1), // metres → cm
            ];
        }

        $series = $this->mergeAndSort($series);

        if (empty($series)) {
            throw new \RuntimeException(
                'Copernicus Marine: no valid data points after processing '
                . "(lat={$latitude}, lon={$longitude} may be on land or outside model domain)"
            );
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

        $locationName = number_format($latitude, 2) . '°N, ' . number_format($longitude, 2) . '°E';

        return [
            'station'           => $locationName,
            'station_code'      => "{$latitude},{$longitude}",
            'current_level_cm'  => $currentLevel,
            'current_timestamp' => $currentTs,
            'trend'             => $this->determineTrend($series, $nowMs),
            'tides'             => $this->detectTideEvents($series, $nowMs),
            'series'            => $series,
            'source'            => 'copernicus',
            'updated_at'        => $now->toIso8601String(),
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Fetch time[0] from the OPeNDAP endpoint and return it as a float
     * (hours since 1950-01-01T00:00:00Z).
     */
    private function fetchTime0(string $user, string $pass): float
    {
        $body = $this->opendapGet(self::OPENDAP_BASE . '.ascii?time[0]', $user, $pass);

        // Response after the separator looks like:
        //   time[1]
        //   [0], 657936.0
        if (preg_match('/\[0\],\s*([\d.e+\-]+)/i', $body, $m)) {
            return (float) $m[1];
        }

        throw new \RuntimeException(
            'Copernicus Marine: could not parse time[0] from OPeNDAP response'
        );
    }

    /**
     * Parse the OPeNDAP ASCII data response into parallel zos and time arrays.
     *
     * Expected format (after the "---" separator):
     *   zos.zos[N][1][1]
     *   [0][0][0], 0.1234
     *   [1][0][0], 0.2345
     *   ...
     *   zos.time[N]
     *   [0], 657936.0
     *   [1], 657937.0
     */
    private function parseDataResponse(string $body): array
    {
        $parts = preg_split('/^-{3,}$/m', $body, 2);
        $data  = $parts[1] ?? $body;

        // zos values: "[i][0][0], value"
        $zos = [];
        if (preg_match_all('/\[\d+\]\[0\]\[0\],\s*([\d.eE+\-]+)/', $data, $m)) {
            foreach ($m[1] as $v) {
                $zos[] = (float) $v;
            }
        }

        // time values: in the "zos.time[N]" or "time[N]" section
        $time = [];
        // Grab everything from "zos.time[" or standalone "time[" to end of string
        if (preg_match('/(?:zos\.)?time\[\d+\](.*)/s', $data, $tMatch)) {
            if (preg_match_all('/\[\d+\],\s*([\d.eE+\-]+)/', $tMatch[1], $tm)) {
                foreach ($tm[2] as $v) {
                    $time[] = (float) $v;
                }
            }
        }

        return ['zos' => $zos, 'time' => $time];
    }

    /** Convert CMEMS "hours since 1950-01-01" to a Unix timestamp (seconds). */
    private function hoursToUnix(float $hours): int
    {
        return (int) round($hours * 3600) + self::EPOCH_1950_UNIX;
    }

    /** Make a GET request to an OPeNDAP ASCII endpoint with Basic Auth. */
    private function opendapGet(string $url, string $user, string $pass): string
    {
        $response = Http::withBasicAuth($user, $pass)
            ->timeout(30)
            ->get($url);

        if ($response->status() === 401) {
            throw new \RuntimeException(
                'Copernicus Marine: authentication failed. '
                . 'Check your username and password in Settings → Tides.'
            );
        }

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Copernicus Marine OPeNDAP returned HTTP ' . $response->status()
            );
        }

        return $response->body();
    }
}
