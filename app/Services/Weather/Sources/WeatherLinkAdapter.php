<?php

namespace App\Services\Weather\Sources;

use App\Services\Weather\WeatherLinkService;

class WeatherLinkAdapter implements WeatherSourceAdapter
{
    private WeatherLinkService $service;

    public function __construct(WeatherLinkService $service)
    {
        $this->service = $service;
    }

    public function key(): string
    {
        return 'DWL_v2api';
    }

    public function fetch(): ?array
    {
        $data = $this->service->getCurrentConditions();
        if (!$data) {
            return null;
        }

        $reading = [
            'temperature' => $data['temperature'] ?? null,
            'feels_like' => $data['feels_like'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'dew_point' => $data['dew_point'] ?? null,
            'pressure_rel' => $data['pressure'] ?? null,
            'wind_speed' => $data['wind_speed'] ?? null,
            'wind_gust' => $data['wind_gust'] ?? null,
            'wind_direction' => $data['wind_direction'] ?? null,
            'rain_rate' => $data['rain_rate'] ?? null,
            'rain_daily' => $data['rain_daily'] ?? null,
            'uv_index' => $data['uv_index'] ?? null,
            'solar_radiation' => $data['solar_radiation'] ?? null,
            'temperature_indoor' => $data['indoor_temperature'] ?? null,
            'humidity_indoor' => $data['indoor_humidity'] ?? null,
            'indoor_temperature' => $data['indoor_temperature'] ?? null,
            'indoor_humidity' => $data['indoor_humidity'] ?? null,
        ];

        return array_filter($reading, static fn ($value) => $value !== null);
    }
}
