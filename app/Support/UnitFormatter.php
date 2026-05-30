<?php

namespace App\Support;

use App\Models\Setting;

class UnitFormatter
{
    public function temperature(float $celsius, string $units, ?string $locale = null): string
    {
        $value = $units === 'imperial' ? ($celsius * 9 / 5 + 32) : $celsius;
        $suffix = $units === 'imperial' ? 'F' : 'C';
        $decimals = (int) Setting::getValue('display.temperature_decimals', 1);

        return $this->format($value, $decimals, $locale) . ' ' . $suffix;
    }

    public function wind(float $kmh, string $units, ?string $locale = null): string
    {
        [$value, $suffix] = match ($units) {
            'imperial', 'uk' => [$kmh * 0.6213711922, 'mph'],
            'scandinavia' => [$kmh / 3.6, 'm/s'],
            default => [$kmh, 'km/h'],
        };

        $decimals = (int) Setting::getValue('display.wind_decimals', 1);

        return $this->format($value, $decimals, $locale) . ' ' . $suffix;
    }

    public function pressure(float $hpa, string $units, ?string $locale = null): string
    {
        [$value, $suffix] = $units === 'imperial'
            ? [$hpa * 0.02953, 'inHg']
            : [$hpa, 'hPa'];

        $decimals = (int) Setting::getValue('display.pressure_decimals', 1);

        return $this->format($value, $decimals, $locale) . ' ' . $suffix;
    }

    public function rain(float $mm, string $units, ?string $locale = null): string
    {
        [$value, $suffix] = $units === 'imperial'
            ? [$mm * 0.0393700787, 'in']
            : [$mm, 'mm'];

        $decimals = (int) Setting::getValue('display.rain_decimals', 1);

        return $this->format($value, $decimals, $locale) . ' ' . $suffix;
    }

    public function distance(float $km, string $units, ?string $locale = null): string
    {
        [$value, $suffix] = match ($units) {
            'imperial', 'uk' => [$km * 0.621371, 'mi'],
            default => [$km, 'km'],
        };

        return $this->format($value, 1, $locale) . ' ' . $suffix;
    }

    public function cloudBase(float $meters, string $units, ?string $locale = null): string
    {
        if ($units === 'imperial' || $units === 'uk') {
            return $this->format($meters * 3.28084, 0, $locale) . ' ft';
        }

        return $this->format($meters, 0, $locale) . ' m';
    }

    public function rainRate(float $mmPerHour, string $units, ?string $locale = null): string
    {
        $rateUnit = Setting::getValue('display.rainrate_unit', '/h');
        $value = $mmPerHour;
        $suffix = $rateUnit === '/min' ? '/min' : '/h';

        if ($rateUnit === '/min') {
            $value = $mmPerHour / 60;
        }

        if ($units === 'imperial') {
            $value = $value * 0.0393700787;
            $unitLabel = 'in';
        } else {
            $unitLabel = 'mm';
        }

        $decimals = (int) Setting::getValue('display.rain_decimals', 1);

        return $this->format($value, $decimals, $locale) . ' ' . $unitLabel . $suffix;
    }

    public function compass(float $degrees, bool $short = true): string
    {
        $labels = $short
            ? [__('N'), __('NNE'), __('NE'), __('ENE'), __('E'), __('ESE'), __('SE'), __('SSE'), __('S'), __('SSW'), __('SW'), __('WSW'), __('W'), __('WNW'), __('NW'), __('NNW')]
            : [__('North'), __('NNE'), __('NE'), __('ENE'), __('East'), __('ESE'), __('SE'), __('SSE'), __('South'), __('SSW'), __('SW'), __('WSW'), __('West'), __('WNW'), __('NW'), __('NNW')];

        $index = (int) fmod((($degrees + 11) / 22.5), 16);

        return $labels[$index] ?? $labels[0];
    }

    private function format(float $value, int $decimals, ?string $locale = null): string
    {
        if ($locale && class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format($value);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($value, $decimals, '.', '');
    }
}
