<?php

declare(strict_types=1);

namespace App\Services\Weather\Sources;

use App\Services\Weather\WeatherLinkLiveLocalService;

/**
 * WeatherLink Live Local API Adapter
 * 
 * Adapter for WeatherLink Live Local API service to integrate with the weather source system.
 */
class WeatherLinkLiveLocalAdapter implements WeatherSourceAdapter
{
    private WeatherLinkLiveLocalService $service;

    /**
     * Constructor
     * 
     * @param WeatherLinkLiveLocalService $service WeatherLink Live Local API service instance
     */
    public function __construct(WeatherLinkLiveLocalService $service)
    {
        $this->service = $service;
    }

    /**
     * Get the adapter key identifier
     * 
     * @return string Adapter key
     */
    public function key(): string
    {
        return 'wll_local';
    }

    /**
     * Fetch current weather data from WeatherLink Live Local API
     * 
     * @return array|null Normalized weather reading data, or null on failure
     */
    public function fetch(): ?array
    {
        $data = $this->service->getCurrentConditions();
        if (!$data) {
            return null;
        }

        // Map WLL data to standard reading format
        $reading = [
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'dew_point' => $data['dew_point'] ?? null,
            'wind_chill' => $data['wind_chill'] ?? null,
            'heat_index' => $data['heat_index'] ?? null,
            'pressure_rel' => $data['pressure_rel'] ?? null,
            'wind_speed' => $data['wind_speed'] ?? null,
            'wind_gust' => $data['wind_gust'] ?? null,
            'wind_direction' => $data['wind_direction'] ?? null,
            'rain_rate' => $data['rain_rate'] ?? null,
            'rain_daily' => $data['rain_daily'] ?? null,
            'rain_monthly' => $data['rain_monthly'] ?? null,
            'rain_yearly' => $data['rain_yearly'] ?? null,
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
