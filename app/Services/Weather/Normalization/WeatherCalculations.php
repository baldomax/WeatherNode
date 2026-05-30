<?php

namespace App\Services\Weather\Normalization;

class WeatherCalculations
{
    public static function dewPoint(?float $tempC, ?float $humidity, int $precision = 1): ?float
    {
        if ($tempC === null || $humidity === null || $humidity <= 0) {
            return null;
        }

        $a = 17.27;
        $b = 237.7;

        $alpha = (($a * $tempC) / ($b + $tempC)) + log($humidity / 100.0);
        $dewPoint = ($b * $alpha) / ($a - $alpha);

        return round($dewPoint, $precision);
    }

    public static function heatIndex(?float $tempC, ?float $humidity, int $precision = 1): ?float
    {
        if ($tempC === null || $humidity === null || $tempC < 20) {
            return null;
        }

        $tempF = ($tempC * 9 / 5) + 32;

        $hi = -42.379 +
            2.04901523 * $tempF +
            10.14333127 * $humidity -
            0.22475541 * $tempF * $humidity -
            0.00683783 * $tempF * $tempF -
            0.05481717 * $humidity * $humidity +
            0.00122874 * $tempF * $tempF * $humidity +
            0.00085282 * $tempF * $humidity * $humidity -
            0.00000199 * $tempF * $tempF * $humidity * $humidity;

        if ($humidity < 13 && $tempF >= 80 && $tempF <= 112) {
            $hi -= ((13 - $humidity) / 4) * sqrt((17 - abs($tempF - 95)) / 17);
        } elseif ($humidity > 85 && $tempF >= 80 && $tempF <= 87) {
            $hi += (($humidity - 85) / 10) * ((87 - $tempF) / 5);
        }

        return round(($hi - 32) * 5 / 9, $precision);
    }

    public static function windChill(?float $tempC, ?float $windKmh, int $precision = 1): ?float
    {
        if ($tempC === null || $windKmh === null || $tempC > 10 || $windKmh < 4.8) {
            return null;
        }

        $windChill = 13.12 + 0.6215 * $tempC -
            11.37 * pow($windKmh, 0.16) +
            0.3965 * $tempC * pow($windKmh, 0.16);

        return round($windChill, $precision);
    }

    public static function feelsLike(?float $tempC, ?float $humidity, ?float $windKmh, int $precision = 1): ?float
    {
        if ($tempC === null) {
            return null;
        }

        if ($tempC >= 27 && $humidity !== null && $humidity > 40) {
            return self::heatIndex($tempC, $humidity, $precision);
        }

        if ($tempC <= 10 && $windKmh !== null && $windKmh > 4.8) {
            return self::windChill($tempC, $windKmh, $precision);
        }

        return round($tempC, $precision);
    }

    public static function wetBulb(?float $tempC, ?float $humidity, ?float $pressureHPa, int $precision = 1): ?float
    {
        if ($tempC === null || $humidity === null || $pressureHPa === null) {
            return null;
        }

        $Tdc = (($tempC - (14.55 + 0.114 * $tempC) * (1 - (0.01 * $humidity))
            - pow((2.5 + 0.007 * $tempC) * (1 - (0.01 * $humidity)), 3)
            - (15.9 + 0.117 * $tempC) * pow(1 - (0.01 * $humidity), 14)));

        $E = 6.11 * pow(10, (7.5 * $Tdc / (237.7 + $Tdc)));

        $wetBulb = ((0.00066 * $pressureHPa) * $tempC + (4098 * $E) / pow(($Tdc + 237.7), 2) * $Tdc)
            / ((0.00066 * $pressureHPa) + (4098 * $E) / pow(($Tdc + 237.7), 2));

        return round($wetBulb, $precision);
    }
}
