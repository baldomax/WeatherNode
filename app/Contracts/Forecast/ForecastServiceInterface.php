<?php

namespace App\Contracts\Forecast;

interface ForecastServiceInterface
{
    /**
     * Fetch raw forecast data from the API
     */
    public function fetchForecast(): ?array;

    /**
     * Get hourly forecast for specified number of hours
     * 
     * @param int $hours Number of hours to return
     * @return array Array of hourly forecast entries with: time, temperature, wind_speed, wind_direction, symbol, precipitation_1h, humidity, cloud_cover
     */
    public function getHourlyForecast(int $hours = 48): array;

    /**
     * Get daily forecast summary
     * 
     * @param int $days Number of days to return
     * @return array Array of daily forecast entries with: date, temp_high, temp_low, symbol, precipitation, wind_speed, wind_direction
     */
    public function getDailyForecast(int $days = 7): array;
}
