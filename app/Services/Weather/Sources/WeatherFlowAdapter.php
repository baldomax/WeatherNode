<?php

namespace App\Services\Weather\Sources;

use App\Services\Weather\WeatherFlowService;
use Carbon\Carbon;

class WeatherFlowAdapter implements WeatherSourceAdapter
{
    private WeatherFlowService $service;

    public function __construct(WeatherFlowService $service)
    {
        $this->service = $service;
    }

    public function key(): string
    {
        return 'wf';
    }

    public function fetch(): ?array
    {
        $data = $this->service->getCurrentConditions();
        if (!$data) {
            return null;
        }

        $outdoor = $data['outdoor'] ?? [];
        $wind = $data['wind'] ?? [];
        $pressure = $data['pressure'] ?? [];
        $rain = $data['rain'] ?? [];
        $solar = $data['solar'] ?? [];
        $lightning = $data['lightning'] ?? [];

        $recordedAt = null;
        if (!empty($data['timestamp'])) {
            try {
                $recordedAt = Carbon::parse($data['timestamp']);
            } catch (\Exception $e) {
                $recordedAt = null;
            }
        }

        return array_filter([
            'recorded_at' => $recordedAt,
            'temperature' => $outdoor['temperature'] ?? null,
            'feels_like' => $outdoor['feels_like'] ?? null,
            'humidity' => $outdoor['humidity'] ?? null,
            'dew_point' => $outdoor['dew_point'] ?? null,
            'wet_bulb' => $outdoor['wet_bulb'] ?? null,
            'pressure_rel' => $pressure['sea_level'] ?? $pressure['station'] ?? null,
            'pressure_abs' => $pressure['station'] ?? null,
            'wind_speed' => $wind['speed'] ?? null,
            'wind_gust' => $wind['gust'] ?? null,
            'wind_direction' => $wind['direction'] ?? null,
            'rain_rate' => $rain['rate_hourly'] ?? null,
            'rain_daily' => $rain['daily'] ?? null,
            'uv_index' => $solar['uv_index'] ?? null,
            'solar_radiation' => $solar['solar_radiation'] ?? null,
            'lightning_distance' => $lightning['last_distance'] ?? null,
            'lightning_time' => !empty($lightning['last_epoch']) ? Carbon::createFromTimestamp((int) $lightning['last_epoch']) : null,
            'lightning_count' => $lightning['strike_count_1h'] ?? null,
            'lightning_count_daily' => $lightning['strike_count_3h'] ?? null,
        ], static fn ($value) => $value !== null);
    }
}
