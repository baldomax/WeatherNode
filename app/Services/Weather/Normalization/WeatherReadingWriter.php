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

        // A Carbon is a fixed moment, so convert it whatever zone it carries.
        // Only "UTC" and "GMT" used to be converted, and a Unix timestamp
        // comes back tagged "+00:00", so cloud readings were stored in UTC
        // wall time and looked hours old or hours ahead (#156).
        if ($value instanceof CarbonInterface) {
            return $value->copy()->setTimezone($appTz);
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
