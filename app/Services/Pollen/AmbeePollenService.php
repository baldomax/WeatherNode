<?php

namespace App\Services\Pollen;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ambee Pollen API.
 * Paid service, requires API key.
 * Returns count + risk level (Low/Moderate/High/Very High) per category + species breakdown.
 * 120-hour (5-day) forecast available.
 * Global coverage (except S. America, polar regions, ocean, small islands).
 */
class AmbeePollenService
{
    private const BASE_FORECAST = 'https://api.ambeedata.com/forecast/v2/pollen/120hr/by-lat-lng';
    private const BASE_LATEST   = 'https://api.ambeedata.com/latest/pollen/by-lat-lng';

    private const RISK_MAP = [
        'Low'       => ['index' => 1, 'risk' => 'Low'],
        'Moderate'  => ['index' => 2, 'risk' => 'Moderate'],
        'High'      => ['index' => 3, 'risk' => 'High'],
        'Very High' => ['index' => 4, 'risk' => 'Very High'],
    ];

    public function fetchForecast(float $latitude, float $longitude, string $apiKey): ?array
    {
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = $this->makeRequest(self::BASE_FORECAST, $latitude, $longitude, $apiKey, true);
            if (!$response) {
                return null;
            }
            return $this->parseForecast($response['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Ambee Pollen forecast exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function fetchLatest(float $latitude, float $longitude, string $apiKey): ?array
    {
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = $this->makeRequest(self::BASE_LATEST, $latitude, $longitude, $apiKey, false);
            if (!$response) {
                return null;
            }
            return $this->parseLatest($response['data'][0] ?? []);
        } catch (\Exception $e) {
            Log::error('Ambee Pollen latest exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function makeRequest(string $url, float $lat, float $lng, string $key, bool $speciesRisk): ?array
    {
        $http = Http::timeout(15)
            ->withHeaders([
                'x-api-key'    => $key,
                'Content-type' => 'application/json',
            ]);

        if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->get($url, [
            'lat'         => $lat,
            'lng'         => $lng,
            'speciesRisk' => $speciesRisk ? 'true' : 'false',
        ]);

        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning('Ambee Pollen API auth failed — check API key');
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Ambee Pollen API failed', ['status' => $response->status()]);
            return null;
        }

        $body = $response->json();
        if (($body['message'] ?? '') !== 'success') {
            Log::warning('Ambee Pollen API non-success', ['msg' => $body['message'] ?? 'unknown']);
            return null;
        }

        return $body;
    }

    private function parseForecast(array $items): array
    {
        // Group by UTC date (YYYY-MM-DD); use daily max risk index per category
        $byDate = [];

        foreach ($items as $item) {
            $ts   = $item['time'] ?? null;
            if (!$ts) {
                continue;
            }
            $date = date('Y-m-d', (int) $ts);

            $risk = $item['Risk'] ?? [];
            $count = $item['Count'] ?? [];

            foreach (['grass' => 'grass_pollen', 'tree' => 'tree_pollen', 'weed' => 'weed_pollen'] as $cat => $key) {
                $riskLabel = $risk[$key] ?? 'Low';
                $mapped    = self::RISK_MAP[$riskLabel] ?? self::RISK_MAP['Low'];
                $cnt       = (float) ($count[$key] ?? 0);

                if (!isset($byDate[$date][$cat]) || $mapped['index'] > $byDate[$date][$cat]['risk_index']) {
                    $byDate[$date][$cat] = array_merge($mapped, ['count' => $cnt]);
                }
            }
        }

        $forecast = [];
        foreach (array_slice($byDate, 0, 5, true) as $date => $cats) {
            $forecast[] = [
                'date'  => $date,
                'grass' => $cats['grass'] ?? null,
                'tree'  => $cats['tree']  ?? null,
                'weed'  => $cats['weed']  ?? null,
            ];
        }

        return [
            'source'   => 'ambee',
            'forecast' => $forecast,
            'updated_at' => now()->utc()->toIso8601String(),
        ];
    }

    private function parseLatest(array $item): ?array
    {
        if (empty($item)) {
            return null;
        }

        $risk    = $item['Risk']    ?? [];
        $count   = $item['Count']   ?? [];
        $species = $item['Species'] ?? [];
        $speciesRisk = $item['SpeciesRisk'] ?? [];

        $buildCat = function (string $key) use ($risk, $count): array {
            $label  = $risk[$key] ?? 'Low';
            $mapped = self::RISK_MAP[$label] ?? self::RISK_MAP['Low'];
            return array_merge($mapped, ['count' => (float) ($count[$key] ?? 0)]);
        };

        // Build species breakdown with risk labels
        $speciesOut = [];
        foreach (['Tree' => 'tree', 'Grass' => 'grass', 'Weed' => 'weed'] as $ambeeGroup => $cat) {
            $speciesOut[$cat] = [];
            foreach ($species[$ambeeGroup] ?? [] as $plant => $cnt) {
                $riskLabel = $speciesRisk[$plant] ?? 'Low';
                $mapped    = self::RISK_MAP[$riskLabel] ?? self::RISK_MAP['Low'];
                $speciesOut[$cat][$plant] = array_merge($mapped, ['count' => (float) $cnt]);
            }
        }

        return [
            'source'     => 'ambee',
            'grass'      => $buildCat('grass_pollen'),
            'tree'       => $buildCat('tree_pollen'),
            'weed'       => $buildCat('weed_pollen'),
            'species'    => $speciesOut,
            'updated_at' => isset($item['createdAt']) ? $item['createdAt'] : now()->utc()->toIso8601String(),
        ];
    }
}
