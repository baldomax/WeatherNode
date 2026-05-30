<?php

declare(strict_types=1);

namespace App\Services\Weather\Sources;

use App\Services\Weather\AirLinkLocalService;

/**
 * AirLink Local API Adapter
 * 
 * Adapter for AirLink Local API service to integrate with the weather source system.
 */
class AirLinkLocalAdapter implements WeatherSourceAdapter
{
    private AirLinkLocalService $service;

    /**
     * Constructor
     * 
     * @param AirLinkLocalService $service AirLink Local API service instance
     */
    public function __construct(AirLinkLocalService $service)
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
        return 'airlink_local';
    }

    /**
     * Fetch current weather data from AirLink Local API
     * 
     * @return array|null Normalized weather reading data, or null on failure
     */
    public function fetch(): ?array
    {
        $data = $this->service->getCurrentConditions();
        if (!$data) {
            return null;
        }

        // Map AirLink data to standard reading format
        $reading = [
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'dew_point' => $data['dew_point'] ?? null,
            'pm_1' => $data['pm_1'] ?? null,
            'pm_2p5' => $data['pm_2p5'] ?? null,
            'pm_10' => $data['pm_10'] ?? null,
            'pm_2p5_1h' => $data['pm_2p5_1h'] ?? null,
            'pm_2p5_24h' => $data['pm_2p5_24h'] ?? null,
            'pm_10_1h' => $data['pm_10_1h'] ?? null,
            'pm_10_24h' => $data['pm_10_24h'] ?? null,
        ];

        return array_filter($reading, static fn ($value) => $value !== null);
    }
}
