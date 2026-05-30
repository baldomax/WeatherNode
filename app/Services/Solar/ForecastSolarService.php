<?php

namespace App\Services\Solar;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForecastSolarService implements SolarForecastContract
{
    /** Horizontal 1 kWp for irradiance-like watts (dec=0, az=0, 1 kwp) */
    private const BASE_URL = 'https://api.forecast.solar/estimate';

    public function getSolarForecast(int $hours = 24): ?array
    {
        $hours = max(1, min($hours, 48));

        try {
            $lat = (float) Setting::latitude();
            $lon = (float) Setting::longitude();
            $url = sprintf('%s/%s/%s/0/0/1', self::BASE_URL, $lat, $lon);

            $response = Http::timeout(20)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url, ['time' => 'iso8601']);

            if (!$response->successful()) {
                Log::warning('Forecast.Solar request failed', ['status' => $response->status()]);
                return null;
            }

            $body = $response->json();
            if (!$body || !isset($body['result']['watts'])) {
                return null;
            }

            $watts = $body['result']['watts'];
            if (!is_array($watts)) {
                return null;
            }

            $now = Carbon::now('UTC');
            $cutoff = $now->copy()->addHours($hours);
            $resultTimes = [];
            $resultValues = [];

            foreach ($watts as $iso => $w) {
                try {
                    $t = Carbon::parse($iso, 'UTC');
                } catch (\Throwable $e) {
                    continue;
                }
                if ($t->gte($now) && $t->lte($cutoff)) {
                    $resultTimes[] = $t->format('Y-m-d\TH:i:s\Z');
                    $resultValues[] = is_numeric($w) ? (float) $w : null;
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
                'source' => 'forecast_solar',
                'available_points' => $nonNull,
                'total_points' => count($resultTimes),
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Forecast.Solar error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
