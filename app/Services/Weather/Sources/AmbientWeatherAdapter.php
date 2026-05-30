<?php

namespace App\Services\Weather\Sources;

use App\Services\Weather\AmbientWeatherService;
use Carbon\Carbon;

class AmbientWeatherAdapter implements WeatherSourceAdapter
{
    private AmbientWeatherService $service;

    public function __construct(AmbientWeatherService $service)
    {
        $this->service = $service;
    }

    public function key(): string
    {
        return 'AWapi';
    }

    public function fetch(): ?array
    {
        $data = $this->service->getCurrentConditions();
        if (!$data) {
            return null;
        }

        $outdoor = $data['outdoor'] ?? [];
        $indoor = $data['indoor'] ?? [];
        $wind = $data['wind'] ?? [];
        $pressure = $data['pressure'] ?? [];
        $rain = $data['rain'] ?? [];
        $solar = $data['solar'] ?? [];

        $recordedAt = null;
        if (!empty($data['timestamp'])) {
            try {
                $recordedAt = Carbon::parse($data['timestamp']);
            } catch (\Exception $e) {
                $recordedAt = null;
            }
        }

        $reading = [
            'recorded_at' => $recordedAt,
            'temperature' => $outdoor['temperature'] ?? null,
            'feels_like' => $outdoor['feels_like'] ?? null,
            'humidity' => $outdoor['humidity'] ?? null,
            'dew_point' => $outdoor['dew_point'] ?? null,
            'temperature_indoor' => $indoor['temperature'] ?? null,
            'humidity_indoor' => $indoor['humidity'] ?? null,
            'indoor_temperature' => $indoor['temperature'] ?? null,
            'indoor_humidity' => $indoor['humidity'] ?? null,
            'wind_speed' => $wind['speed'] ?? null,
            'wind_gust' => $wind['gust'] ?? null,
            'wind_direction' => $wind['direction'] ?? null,
            'wind_direction_avg_10m' => $wind['direction_avg'] ?? null,
            'pressure_rel' => $pressure['relative'] ?? null,
            'pressure_abs' => $pressure['absolute'] ?? null,
            'rain_rate' => $rain['rate'] ?? null,
            'rain_hourly' => $rain['hourly'] ?? null,
            'rain_daily' => $rain['daily'] ?? null,
            'rain_weekly' => $rain['weekly'] ?? null,
            'rain_monthly' => $rain['monthly'] ?? null,
            'rain_yearly' => $rain['yearly'] ?? null,
            'uv_index' => $solar['uv_index'] ?? null,
            'solar_radiation' => $solar['solar_radiation'] ?? null,
        ];

        return array_filter($reading, static fn ($value) => $value !== null);
    }
}
