<?php

namespace App\Services\Weather\Normalization;

class WeatherDerivedMetrics
{
    public static function apply(array $reading): array
    {
        $tempC = $reading['temperature'] ?? null;
        $humidity = $reading['humidity'] ?? null;
        $windKmh = $reading['wind_speed'] ?? null;
        $pressure = $reading['pressure_rel'] ?? ($reading['pressure_abs'] ?? null);

        if (self::shouldFill($reading, 'dew_point')) {
            $dewPoint = WeatherCalculations::dewPoint($tempC, $humidity);
            if ($dewPoint !== null) {
                $reading['dew_point'] = $dewPoint;
            }
        }

        if (self::shouldFill($reading, 'heat_index')) {
            $heatIndex = WeatherCalculations::heatIndex($tempC, $humidity);
            if ($heatIndex !== null) {
                $reading['heat_index'] = $heatIndex;
            }
        }

        if (self::shouldFill($reading, 'wind_chill')) {
            $windChill = WeatherCalculations::windChill($tempC, $windKmh);
            if ($windChill !== null) {
                $reading['wind_chill'] = $windChill;
            }
        }

        if (self::shouldFill($reading, 'feels_like')) {
            $feelsLike = WeatherCalculations::feelsLike($tempC, $humidity, $windKmh);
            if ($feelsLike !== null) {
                $reading['feels_like'] = $feelsLike;
            }
        }

        if (self::shouldFill($reading, 'wet_bulb')) {
            $wetBulb = WeatherCalculations::wetBulb($tempC, $humidity, $pressure);
            if ($wetBulb !== null) {
                $reading['wet_bulb'] = $wetBulb;
            }
        }

        return $reading;
    }

    private static function shouldFill(array $reading, string $key): bool
    {
        return !array_key_exists($key, $reading) || $reading[$key] === null;
    }
}
