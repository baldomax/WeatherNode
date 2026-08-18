<?php

namespace App\Services\Tide;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Open-Meteo Marine API tide source.
 *
 * Uses the Open-Meteo Marine API to retrieve sea level height forecasts.
 * This is a global model-based source — no API key required.
 * Accuracy is lower than national gauge networks but provides worldwide coverage.
 *
 * API docs: https://open-meteo.com/en/docs/marine-weather-api
 */
class OpenMeteoMarineSource extends AbstractTideSource
{
    private const API_BASE = 'https://marine-api.open-meteo.com/v1/marine';

    // ── Interface metadata ────────────────────────────────────────────────────

    public function getName(): string        { return 'Open-Meteo Marine'; }
    public function getRegion(): string      { return 'GLOBAL'; }
    public function getSourceKey(): string   { return 'open_meteo'; }
    public function isImplemented(): bool    { return true; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://open-meteo.com/en/docs/marine-weather-api'; }
    public function getCoverageArea(): string { return 'Global (model-based, lower accuracy than gauge networks)'; }
    public function isStationBased(): bool   { return false; }
    public function getStations(): array     { return []; }

    // ── Main fetch ────────────────────────────────────────────────────────────

    public function fetchTideData(string $stationCode = ''): array
    {
        $latitude  = Setting::marineLatitude();
        $longitude = Setting::marineLongitude();
        $now       = now();

        $response = Http::timeout(15)->get(self::API_BASE, [
            'latitude'     => $latitude,
            'longitude'    => $longitude,
            'hourly'       => 'sea_level_height_msl',
            'timezone'     => 'auto',
            'past_hours'   => 12,
            'forecast_days' => 4,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Open-Meteo Marine API returned HTTP ' . $response->status()
            );
        }

        $data = $response->json();

        $times  = $data['hourly']['time'] ?? [];
        $levels = $data['hourly']['sea_level_height_msl'] ?? [];

        if (empty($times) || empty($levels)) {
            throw new \RuntimeException(
                'Open-Meteo Marine API returned no sea_level_height_msl data for '
                . "{$latitude}, {$longitude}"
            );
        }

        $series = [];
        foreach ($times as $i => $timeStr) {
            $val = $levels[$i] ?? null;
            if ($val === null) {
                continue;
            }
            $ts       = Carbon::parse($timeStr);
            $series[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'value'          => round((float) $val * 100, 1), // metres → cm
            ];
        }

        if (empty($series)) {
            throw new \RuntimeException(
                'Open-Meteo Marine API returned only null values for sea_level_height_msl'
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
            'source'            => 'open_meteo',
            'updated_at'        => $now->toIso8601String(),
        ];
    }
}
