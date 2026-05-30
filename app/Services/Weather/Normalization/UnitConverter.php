<?php

namespace App\Services\Weather\Normalization;

class UnitConverter
{
    public static function fahrenheitToCelsius(?float $fahrenheit, int $precision = 1): ?float
    {
        if ($fahrenheit === null) {
            return null;
        }

        return round(($fahrenheit - 32) * 5 / 9, $precision);
    }

    public static function mphToKmh(?float $mph, int $precision = 1): ?float
    {
        if ($mph === null) {
            return null;
        }

        return round($mph * 1.60934, $precision);
    }

    public static function inHgToHpa(?float $inHg, int $precision = 1): ?float
    {
        if ($inHg === null) {
            return null;
        }

        return round($inHg * 33.8639, $precision);
    }

    public static function inchesToMm(?float $inches, int $precision = 2): ?float
    {
        if ($inches === null) {
            return null;
        }

        return round($inches * 25.4, $precision);
    }

    public static function msToKmh(?float $ms, int $precision = 1): ?float
    {
        if ($ms === null) {
            return null;
        }

        return round($ms * 3.6, $precision);
    }

    public static function knotsToKmh(?float $knots, int $precision = 1): ?float
    {
        if ($knots === null) {
            return null;
        }

        return round($knots * 1.852, $precision);
    }
}
