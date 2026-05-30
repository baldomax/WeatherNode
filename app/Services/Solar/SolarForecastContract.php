<?php

namespace App\Services\Solar;

interface SolarForecastContract
{
    /**
     * Get solar radiation forecast data.
     * Returns normalized array with 'times' (ISO8601), 'values' (W/m² or W), 'unit', 'source', etc.
     *
     * @param int $hours Number of hours to forecast (1-48)
     * @return array|null Normalized forecast data or null on error
     */
    public function getSolarForecast(int $hours = 24): ?array;
}
