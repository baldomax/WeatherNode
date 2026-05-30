<?php

namespace App\Services\Solar;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenMeteoSolarService implements SolarForecastContract
{
    private const BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    public function getSolarForecast(int $hours = 24): ?array
    {
        $hours = max(1, min($hours, 48));
        $days = (int) ceil($hours / 24) + 1;

        try {
            $lat = (float) Setting::latitude();
            $lon = (float) Setting::longitude();

            $response = Http::timeout(20)->get(self::BASE_URL, [
                'latitude' => $lat,
                'longitude' => $lon,
                'hourly' => 'shortwave_radiation',
                'forecast_days' => min($days, 16),
                'timezone' => 'UTC',
            ]);

            if (!$response->successful()) {
                Log::warning('Open-Meteo solar forecast request failed', ['status' => $response->status()]);
                return null;
            }

            $body = $response->json();
            $times = $body['hourly']['time'] ?? [];
            $values = $body['hourly']['shortwave_radiation'] ?? [];

            if (empty($times) || empty($values) || count($times) !== count($values)) {
                return null;
            }

            $now = Carbon::now('UTC');
            $cutoff = $now->copy()->addHours($hours);
            $resultTimes = [];
            $resultValues = [];

            for ($i = 0; $i < count($times); $i++) {
                $t = Carbon::parse($times[$i], 'UTC');
                if ($t->gte($now) && $t->lte($cutoff)) {
                    $resultTimes[] = $t->format('Y-m-d\TH:i:s\Z');
                    $resultValues[] = $values[$i] !== null ? (float) $values[$i] : null;
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
                'source' => 'open_meteo',
                'available_points' => $nonNull,
                'total_points' => count($resultTimes),
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Open-Meteo solar forecast error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
