<?php

namespace App\Services\Pollen;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Open-Meteo Air Quality API — pollen component.
 * Free, no API key required, global coverage.
 * Provides hourly pollen counts (grains/m³) for 6 plant types, 5-day forecast.
 */
class OpenMeteoPollenService
{
    private const BASE_URL = 'https://air-quality-api.open-meteo.com/v1/air-quality';

    private const HOURLY_VARS = [
        'alder_pollen',
        'birch_pollen',
        'grass_pollen',
        'mugwort_pollen',
        'olive_pollen',
        'ragweed_pollen',
    ];

    /**
     * Pollen risk thresholds per category (grains/m³).
     * Based on ECAC/EAN (European) guidelines.
     */
    private const THRESHOLDS = [
        'grass' => [
            ['max' => 1,   'index' => 0, 'risk' => 'None'],
            ['max' => 10,  'index' => 1, 'risk' => 'Low'],
            ['max' => 50,  'index' => 2, 'risk' => 'Moderate'],
            ['max' => 200, 'index' => 3, 'risk' => 'High'],
            ['max' => INF, 'index' => 4, 'risk' => 'Very High'],
        ],
        'tree' => [
            ['max' => 1,    'index' => 0, 'risk' => 'None'],
            ['max' => 15,   'index' => 1, 'risk' => 'Low'],
            ['max' => 90,   'index' => 2, 'risk' => 'Moderate'],
            ['max' => 1500, 'index' => 3, 'risk' => 'High'],
            ['max' => INF,  'index' => 4, 'risk' => 'Very High'],
        ],
        'weed' => [
            ['max' => 1,   'index' => 0, 'risk' => 'None'],
            ['max' => 10,  'index' => 1, 'risk' => 'Low'],
            ['max' => 50,  'index' => 2, 'risk' => 'Moderate'],
            ['max' => 200, 'index' => 3, 'risk' => 'High'],
            ['max' => INF, 'index' => 4, 'risk' => 'Very High'],
        ],
    ];

    public function fetch(float $latitude, float $longitude, int $days = 5): ?array
    {
        try {
            $http = Http::timeout(15);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->get(self::BASE_URL, [
                'latitude'     => $latitude,
                'longitude'    => $longitude,
                'hourly'       => implode(',', self::HOURLY_VARS),
                'forecast_days' => min($days, 5),
                'timezone'     => 'UTC',
            ]);

            if (!$response->successful()) {
                Log::warning('Open-Meteo pollen request failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();

            if (empty($data['hourly']['time'])) {
                return null;
            }

            return $this->parse($data, $days);
        } catch (\Exception $e) {
            Log::error('Open-Meteo pollen exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parse(array $data, int $days): array
    {
        $hourly    = $data['hourly'];
        $times     = $hourly['time'];
        $totalDays = min($days, 5);

        // Group hourly values by date (UTC date string YYYY-MM-DD)
        $byDate = [];
        foreach ($times as $i => $iso) {
            $date = substr($iso, 0, 10);
            foreach (self::HOURLY_VARS as $var) {
                $val = $hourly[$var][$i] ?? null;
                if ($val !== null) {
                    $byDate[$date][$var][] = (float) $val;
                }
            }
        }

        $dates = array_slice(array_keys($byDate), 0, $totalDays);
        $forecast = [];

        foreach ($dates as $date) {
            $dayData = $byDate[$date];

            // Category counts: grass, tree (alder+birch+olive), weed (mugwort+ragweed)
            $grassCount = $this->dailyMax($dayData['grass_pollen'] ?? []);
            $treeCount  = max(
                $this->dailyMax($dayData['alder_pollen'] ?? []),
                $this->dailyMax($dayData['birch_pollen'] ?? []),
                $this->dailyMax($dayData['olive_pollen'] ?? [])
            );
            $weedCount  = max(
                $this->dailyMax($dayData['mugwort_pollen'] ?? []),
                $this->dailyMax($dayData['ragweed_pollen'] ?? [])
            );

            $grassRisk = $this->classify($grassCount, 'grass');
            $treeRisk  = $this->classify($treeCount,  'tree');
            $weedRisk  = $this->classify($weedCount,  'weed');

            $forecast[] = [
                'date'  => $date,
                'grass' => array_merge($grassRisk, ['count' => $grassCount]),
                'tree'  => array_merge($treeRisk,  ['count' => $treeCount]),
                'weed'  => array_merge($weedRisk,  ['count' => $weedCount]),
                // Species-level daily maxima (for reference/blending)
                'species_counts' => [
                    'alder'   => $this->dailyMax($dayData['alder_pollen']   ?? []),
                    'birch'   => $this->dailyMax($dayData['birch_pollen']   ?? []),
                    'grass'   => $grassCount,
                    'mugwort' => $this->dailyMax($dayData['mugwort_pollen'] ?? []),
                    'olive'   => $this->dailyMax($dayData['olive_pollen']   ?? []),
                    'ragweed' => $this->dailyMax($dayData['ragweed_pollen'] ?? []),
                ],
            ];
        }

        return [
            'source'     => 'openmeteo',
            'forecast'   => $forecast,
            'updated_at' => now()->utc()->toIso8601String(),
        ];
    }

    private function dailyMax(array $values): float
    {
        return count($values) ? max($values) : 0.0;
    }

    private function classify(float $count, string $category): array
    {
        $thresholds = self::THRESHOLDS[$category] ?? self::THRESHOLDS['grass'];
        foreach ($thresholds as $level) {
            if ($count < $level['max']) {
                return ['risk_index' => $level['index'], 'risk' => $level['risk']];
            }
        }
        return ['risk_index' => 4, 'risk' => 'Very High'];
    }
}
