<?php

namespace App\Services\Solar;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolcastSolarService implements SolarForecastContract
{
    /** World radiation forecasts - GHI in W/m² */
    private const BASE_URL = 'https://api.solcast.com.au/world_radiation/forecasts';

    public function getSolarForecast(int $hours = 24): ?array
    {
        $hours = max(1, min($hours, 48));
        $apiKey = Setting::getValue('solar_forecast.solcast_api_key', '');
        if ($apiKey === '') {
            Log::warning('Solcast API key not configured');
            return null;
        }

        try {
            $lat = (float) Setting::latitude();
            $lon = (float) Setting::longitude();

            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get(self::BASE_URL, [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'hours' => min($hours, 168),
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                Log::warning('Solcast request failed', ['status' => $response->status()]);
                return null;
            }

            $body = $response->json();
            $forecasts = $body['forecasts'] ?? [];
            if (!is_array($forecasts) || empty($forecasts)) {
                return null;
            }

            $now = Carbon::now('UTC');
            $cutoff = $now->copy()->addHours($hours);
            $resultTimes = [];
            $resultValues = [];

            foreach ($forecasts as $row) {
                $iso = $row['period_end'] ?? $row['period_start'] ?? null;
                $ghi = $row['ghi'] ?? $row['ghi_instant'] ?? null;
                if ($iso === null) {
                    continue;
                }
                try {
                    $t = Carbon::parse($iso, 'UTC');
                } catch (\Throwable $e) {
                    continue;
                }
                if ($t->gte($now) && $t->lte($cutoff)) {
                    $resultTimes[] = $t->format('Y-m-d\TH:i:s\Z');
                    $resultValues[] = is_numeric($ghi) ? (float) $ghi : null;
                }
                if (count($resultTimes) >= $hours + 1) {
                    break;
                }
            }

            if (empty($resultTimes)) {
                return null;
            }

            $nonNull = count(array_filter($resultValues, static fn ($v) => $v !== null));

            return [
                'times' => $resultTimes,
                'values' => $resultValues,
                'unit' => 'W/m²',
                'forecast_hours' => $hours,
                'source' => 'solcast',
                'available_points' => $nonNull,
                'total_points' => count($resultTimes),
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Solcast solar forecast error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
