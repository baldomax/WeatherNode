<?php

namespace App\Services\Solar;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenQuartzSolarService implements SolarForecastContract
{
    private const BASE_URL = 'https://open.quartz.solar/forecast/';

    /** Default 1 kWp horizontal for irradiance-like power (W → treat as W scale) */
    public function getSolarForecast(int $hours = 24): ?array
    {
        $hours = max(1, min($hours, 48));

        try {
            $lat = (float) Setting::latitude();
            $lon = (float) Setting::longitude();

            $response = Http::timeout(25)
                ->post(self::BASE_URL, [
                    'site' => [
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'capacity_kwp' => 1.0,
                        'tilt' => 0,
                        'orientation' => 180,
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Open Quartz Solar request failed', ['status' => $response->status()]);
                return null;
            }

            $body = $response->json();
            $predictions = $body['predictions']['power_kw'] ?? null;
            if (!$predictions || !is_array($predictions)) {
                return null;
            }

            $now = Carbon::now('UTC');
            $cutoff = $now->copy()->addHours($hours);
            $resultTimes = [];
            $resultValues = [];

            foreach ($predictions as $iso => $kw) {
                try {
                    $t = Carbon::parse($iso, 'UTC');
                } catch (\Throwable $e) {
                    continue;
                }
                if ($t->gte($now) && $t->lte($cutoff)) {
                    $resultTimes[] = $t->format('Y-m-d\TH:i:s\Z');
                    $resultValues[] = is_numeric($kw) ? (float) $kw * 1000 : null;
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
                'unit' => 'W',
                'forecast_hours' => $hours,
                'source' => 'open_quartz',
                'available_points' => $nonNull,
                'total_points' => count($resultTimes),
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Open Quartz Solar error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
