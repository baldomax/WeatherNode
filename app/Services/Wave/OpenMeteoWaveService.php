<?php

namespace App\Services\Wave;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Open-Meteo Marine API wave + sea surface temperature source.
 *
 * Free, no API key, global model-based coverage.
 * Uses the same endpoint as OpenMeteoMarineSource (tide) but fetches
 * wave-specific and SST variables in one request.
 *
 * API docs: https://open-meteo.com/en/docs/marine-weather-api
 */
class OpenMeteoWaveService
{
    private const API_BASE = 'https://marine-api.open-meteo.com/v1/marine';

    private const BEAUFORT_THRESHOLDS = [
        [0.1,  0, 'wave_beaufort_0'],   // Calm (glassy)
        [0.5,  1, 'wave_beaufort_1'],   // Calm (rippled)
        [1.25, 2, 'wave_beaufort_2'],   // Smooth
        [2.5,  3, 'wave_beaufort_3'],   // Slight sea
        [4.0,  4, 'wave_beaufort_4'],   // Moderate sea
        [6.0,  5, 'wave_beaufort_5'],   // Rough sea
        [9.0,  6, 'wave_beaufort_6'],   // Very rough sea
        [INF,  7, 'wave_beaufort_7'],   // High sea
    ];

    public function fetch(): array
    {
        $latitude  = Setting::marineLatitude();
        $longitude = Setting::marineLongitude();
        $now       = now();

        $response = Http::timeout(15)->get(self::API_BASE, [
            'latitude'     => $latitude,
            'longitude'    => $longitude,
            'hourly'       => implode(',', [
                'wave_height',
                'wave_direction',
                'wave_period',
                'wind_wave_height',
                'swell_wave_height',
                'swell_wave_direction',
                'swell_wave_period',
                'sea_surface_temperature',
            ]),
            'timezone'      => 'auto',
            'past_hours'    => 12,
            'forecast_days' => 5,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Open-Meteo Marine Wave API returned HTTP ' . $response->status()
            );
        }

        $data   = $response->json();
        $hourly = $data['hourly'] ?? [];
        $times  = $hourly['time'] ?? [];

        if (empty($times)) {
            throw new \RuntimeException('Open-Meteo Marine Wave API returned no hourly data');
        }

        $nowMs = $now->timestamp * 1000;

        // Current values: pick the most recent point at or before now
        $current = $this->extractCurrentValues($times, $hourly, $nowMs);

        // Build time series for charts
        $waveSeries = $this->buildSeries($times, $hourly['wave_height'] ?? []);
        $sstSeries  = $this->buildSeries($times, $hourly['sea_surface_temperature'] ?? []);

        $beaufort = $this->computeBeaufort($current['wave_height_m'] ?? 0.0);

        return [
            'current_wave_height_m'       => $current['wave_height_m'],
            'current_wave_direction_deg'  => $current['wave_direction_deg'],
            'current_wave_period_s'       => $current['wave_period_s'],
            'current_wind_wave_height_m'  => $current['wind_wave_height_m'],
            'current_swell_height_m'      => $current['swell_height_m'],
            'current_swell_direction_deg' => $current['swell_direction_deg'],
            'current_swell_period_s'      => $current['swell_period_s'],
            'current_sst_c'               => $current['sst_c'],
            'beaufort_sea_state'          => $beaufort['state'],
            'beaufort_label_key'          => $beaufort['label_key'],
            'wave_series'                 => $waveSeries,
            'sst_series'                  => $sstSeries,
            'location'                    => number_format($latitude, 2) . '°N, ' . number_format($longitude, 2) . '°E',
            'updated_at'                  => $now->toIso8601String(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function extractCurrentValues(array $times, array $hourly, int $nowMs): array
    {
        $fields = [
            'wave_height'           => 'wave_height_m',
            'wave_direction'        => 'wave_direction_deg',
            'wave_period'           => 'wave_period_s',
            'wind_wave_height'      => 'wind_wave_height_m',
            'swell_wave_height'     => 'swell_height_m',
            'swell_wave_direction'  => 'swell_direction_deg',
            'swell_wave_period'     => 'swell_period_s',
            'sea_surface_temperature' => 'sst_c',
        ];

        $current = array_fill_keys(array_values($fields), null);

        foreach ($times as $i => $timeStr) {
            $tsMs = Carbon::parse($timeStr)->timestamp * 1000;
            if ($tsMs > $nowMs) {
                break;
            }
            foreach ($fields as $apiKey => $resultKey) {
                $val = $hourly[$apiKey][$i] ?? null;
                if ($val !== null) {
                    $current[$resultKey] = (float) $val;
                }
            }
        }

        return $current;
    }

    private function buildSeries(array $times, array $values): array
    {
        $series = [];
        foreach ($times as $i => $timeStr) {
            $val = $values[$i] ?? null;
            if ($val === null) {
                continue;
            }
            $ts       = Carbon::parse($timeStr);
            $series[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'value'          => round((float) $val, 2),
            ];
        }

        return $series;
    }

    private function computeBeaufort(?float $heightM): array
    {
        $h = $heightM ?? 0.0;

        foreach (self::BEAUFORT_THRESHOLDS as [$threshold, $state, $labelKey]) {
            if ($h < $threshold) {
                return ['state' => $state, 'label_key' => $labelKey];
            }
        }

        return ['state' => 7, 'label_key' => 'wave_beaufort_7'];
    }

    // ── Static helpers for view ───────────────────────────────────────────────

    /**
     * Convert degrees to cardinal direction label (N, NE, E, …, NW, NNW, etc.)
     */
    public static function degreesToCardinal(?float $deg): string
    {
        if ($deg === null) {
            return '--';
        }

        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
                       'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = (int) round($deg / 22.5) % 16;

        return $directions[$index];
    }
}
