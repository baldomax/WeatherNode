<?php

namespace App\Services\Pollen;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Pollen API v1 (Maps Platform).
 * Requires a Google API key with the Pollen API enabled.
 * Returns UPI (Universal Pollen Index) 0–5 per category + plant-level breakdown.
 * Global coverage, 5-day forecast.
 */
class GooglePollenService
{
    private const BASE_URL = 'https://pollen.googleapis.com/v1/forecast:lookup';

    /**
     * Map Google UPI 0–5 to our normalised risk index 0–4.
     * UPI 0 = None, 1 = Very Low, 2 = Low, 3 = Moderate, 4 = High, 5 = Very High
     */
    private const UPI_MAP = [
        0 => ['index' => 0, 'risk' => 'None'],
        1 => ['index' => 1, 'risk' => 'Low'],
        2 => ['index' => 1, 'risk' => 'Low'],
        3 => ['index' => 2, 'risk' => 'Moderate'],
        4 => ['index' => 3, 'risk' => 'High'],
        5 => ['index' => 4, 'risk' => 'Very High'],
    ];

    /**
     * Google pollen type codes → our 3 categories.
     */
    private const TYPE_MAP = [
        'GRASS' => 'grass',
        'TREE'  => 'tree',
        'WEED'  => 'weed',
    ];

    public function fetch(float $latitude, float $longitude, string $apiKey, int $days = 5): ?array
    {
        if (empty($apiKey)) {
            return null;
        }

        try {
            $http = Http::timeout(15);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->get(self::BASE_URL, [
                'key'                => $apiKey,
                'location.latitude'  => $latitude,
                'location.longitude' => $longitude,
                'days'               => min($days, 5),
                'languageCode'       => 'en',
            ]);

            if ($response->status() === 400) {
                Log::warning('Google Pollen API bad request — check API key and location', [
                    'body' => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            if ($response->status() === 403) {
                Log::warning('Google Pollen API forbidden — check API key permissions');
                return null;
            }

            if (!$response->successful()) {
                Log::warning('Google Pollen API failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();

            if (empty($data['dailyInfo'])) {
                return null;
            }

            return $this->parse($data);
        } catch (\Exception $e) {
            Log::error('Google Pollen API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parse(array $data): array
    {
        $forecast = [];

        foreach ($data['dailyInfo'] as $day) {
            $d = $day['date'] ?? null;
            if (!$d) {
                continue;
            }
            $date = sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);

            $categories = [];
            $species    = ['tree' => [], 'grass' => [], 'weed' => []];
            $healthRecs = [];

            // Pollen type info (GRASS, TREE, WEED)
            foreach ($day['pollenTypeInfo'] ?? [] as $typeInfo) {
                $code = $typeInfo['code'] ?? '';
                $cat  = self::TYPE_MAP[$code] ?? null;
                if (!$cat) {
                    continue;
                }

                $upi      = $typeInfo['indexInfo']['value'] ?? 0;
                $mapped   = self::UPI_MAP[$upi] ?? self::UPI_MAP[0];
                $inSeason = (bool) ($typeInfo['inSeason'] ?? false);

                $categories[$cat] = [
                    'risk_index' => $mapped['index'],
                    'risk'       => $mapped['risk'],
                    'upi'        => $upi,
                    'in_season'  => $inSeason,
                ];

                foreach ($typeInfo['healthRecommendations'] ?? [] as $rec) {
                    if (!in_array($rec, $healthRecs, true)) {
                        $healthRecs[] = $rec;
                    }
                }
            }

            // Plant-level breakdown
            foreach ($day['plantInfo'] ?? [] as $plant) {
                $code     = $plant['code']        ?? '';
                $name     = $plant['displayName']  ?? $code;
                $upi      = $plant['indexInfo']['value'] ?? 0;
                $mapped   = self::UPI_MAP[$upi] ?? self::UPI_MAP[0];
                $inSeason = (bool) ($plant['inSeason'] ?? false);

                // Determine parent category based on known plant codes
                $cat = $this->plantCategory($code);

                if (isset($species[$cat])) {
                    $species[$cat][$name] = [
                        'risk_index' => $mapped['index'],
                        'risk'       => $mapped['risk'],
                        'in_season'  => $inSeason,
                    ];
                }
            }

            $forecast[] = [
                'date'               => $date,
                'grass'              => $categories['grass'] ?? null,
                'tree'               => $categories['tree']  ?? null,
                'weed'               => $categories['weed']  ?? null,
                'species'            => $species,
                'health_recs'        => $healthRecs,
            ];
        }

        return [
            'source'      => 'google',
            'region_code' => $data['regionCode'] ?? null,
            'forecast'    => $forecast,
            'updated_at'  => now()->utc()->toIso8601String(),
        ];
    }

    private function plantCategory(string $code): string
    {
        $trees = ['ALDER', 'ASH', 'BIRCH', 'COTTONWOOD', 'ELM', 'MAPLE', 'OLIVE', 'JUNIPER',
                  'OAK', 'PINE', 'CYPRESS_PINE', 'HAZEL'];
        $grasses = ['GRASS', 'GRAMINALES', 'BERMUDA_GRASS', 'BLUE_GRASS', 'JOHNSON_GRASS', 'RYE_GRASS',
                    'SWEET_VERNAL_GRASS', 'TIMOTHY_GRASS'];
        $weeds = ['MUGWORT', 'RAGWEED', 'NETTLE', 'CHENOPOD', 'PLANTAIN', 'PELLITORY',
                  'WEED'];

        if (in_array($code, $trees, true)) {
            return 'tree';
        }
        if (in_array($code, $grasses, true)) {
            return 'grass';
        }
        return 'weed';
    }
}
