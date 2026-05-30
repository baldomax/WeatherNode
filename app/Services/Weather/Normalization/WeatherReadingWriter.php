<?php

namespace App\Services\Weather\Normalization;

use App\Models\WeatherReading;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class WeatherReadingWriter
{
    public function store(array $reading, int $cacheMinutes = 10): WeatherReading
    {
        if (!array_key_exists('recorded_at', $reading) || $reading['recorded_at'] === null) {
            $reading['recorded_at'] = now();
        } else {
            $reading['recorded_at'] = $this->normalizeRecordedAt($reading['recorded_at']);
        }

        $reading = WeatherDerivedMetrics::apply($reading);

        $model = WeatherReading::create($reading);

        Cache::put('weather:current', $model->toArray(), now()->addMinutes($cacheMinutes));
        Cache::put('weather:last_update', now()->toIso8601String(), now()->addMinutes($cacheMinutes));

        return $model;
    }

    private function normalizeRecordedAt(mixed $value): CarbonInterface
    {
        $appTz = config('app.timezone');

        if ($value instanceof CarbonInterface) {
            $tzName = $value->getTimezone()->getName();
            if (in_array($tzName, ['UTC', 'GMT'], true)) {
                return $value->copy()->setTimezone($appTz);
            }
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $isUtcString = str_ends_with($trimmed, 'Z') || stripos($trimmed, 'UTC') !== false;
            if ($isUtcString) {
                return Carbon::parse($trimmed, 'UTC')->setTimezone($appTz);
            }
            return Carbon::parse($trimmed, $appTz);
        }

        return now();
    }
}
