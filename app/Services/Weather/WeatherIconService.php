<?php

namespace App\Services\Weather;

/**
 * Weather Icon Service
 *
 * Maps weather condition codes from various APIs to SVG icon names.
 *
 * Icons: Basmilius Weather Icons (https://github.com/basmilius/weather-icons)
 * License: MIT
 *
 * Supported APIs:
 * - Yr.no/Met.no (primary): clearsky_day, partlycloudy_night, lightrain, etc.
 * - Aeris Weather: weather_code field (TSTM, RAIN, SNOW, etc.)
 * - WeatherFlow: conditions text field
 * - Generic: Common weather terms work across most sources
 */
class WeatherIconService
{
    /**
     * Map weather symbol/condition to SVG icon name.
     * Supports multiple API formats: Yr.no, Aeris, WeatherFlow, and generic terms.
     */
    public static function getIconForSymbol(string $symbol, ?bool $isNight = null): string
    {
        $symbol = strtolower($symbol);

        // Normalize different API formats to common terms
        $symbol = self::normalizeSymbol($symbol);

        // Determine day/night from symbol suffix if not explicitly provided
        if ($isNight === null) {
            $isNight = str_contains($symbol, '_night') || str_contains($symbol, '_polartwilight');
        }
        $suffix = $isNight ? '-night' : '-day';

        // Thunderstorms (check first as they can combine with other conditions)
        if (str_contains($symbol, 'thunder')) {
            if (str_contains($symbol, 'snow')) {
                return $isNight ? 'thunderstorms-night-snow' : 'thunderstorms-day-snow';
            }
            if (str_contains($symbol, '_day') || str_contains($symbol, '_night')) {
                return $isNight ? 'thunderstorms-night-rain' : 'thunderstorms-day-rain';
            }
            if (str_contains($symbol, 'rain')) {
                return 'thunderstorms-rain';
            }
            return 'thunderstorms';
        }

        // Clear sky
        if (str_contains($symbol, 'clearsky')) {
            return "clear{$suffix}";
        }

        // Fair (slightly cloudy)
        if (str_contains($symbol, 'fair')) {
            return "partly-cloudy{$suffix}";
        }

        // Fog
        if (str_contains($symbol, 'fog')) {
            return "fog{$suffix}";
        }

        // Snow conditions
        if (str_contains($symbol, 'snow')) {
            if (str_contains($symbol, 'showers')) {
                return "partly-cloudy{$suffix}-snow";
            }
            return 'snow';
        }

        // Sleet conditions
        if (str_contains($symbol, 'sleet')) {
            if (str_contains($symbol, 'showers')) {
                return "partly-cloudy{$suffix}-sleet";
            }
            return 'sleet';
        }

        // Rain conditions - differentiate by intensity
        if (str_contains($symbol, 'rain')) {
            // Light rain / drizzle
            if (str_contains($symbol, 'light')) {
                if (str_contains($symbol, 'showers')) {
                    return "partly-cloudy{$suffix}-drizzle";
                }
                return 'drizzle';
            }
            // Regular or heavy rain with showers
            if (str_contains($symbol, 'showers')) {
                return "partly-cloudy{$suffix}-rain";
            }
            return 'rain';
        }

        // Partly cloudy
        if (str_contains($symbol, 'partlycloudy')) {
            return "partly-cloudy{$suffix}";
        }

        // Cloudy / Overcast
        if (str_contains($symbol, 'cloudy') || str_contains($symbol, 'overcast')) {
            return 'cloudy';
        }

        // Default fallback
        return "partly-cloudy{$suffix}";
    }

    /**
     * Get icon for moon phase (0-1 where 0 = new moon, 0.5 = full moon).
     */
    public static function getMoonPhaseIcon(float $phase): string
    {
        // Normalize phase to 0-1 range
        $phase = fmod($phase, 1.0);
        if ($phase < 0) {
            $phase += 1.0;
        }

        return match (true) {
            $phase < 0.0625 || $phase >= 0.9375 => 'moon-new',
            $phase < 0.1875 => 'moon-waxing-crescent',
            $phase < 0.3125 => 'moon-first-quarter',
            $phase < 0.4375 => 'moon-waxing-gibbous',
            $phase < 0.5625 => 'moon-full',
            $phase < 0.6875 => 'moon-waning-gibbous',
            $phase < 0.8125 => 'moon-last-quarter',
            default => 'moon-waning-crescent',
        };
    }

    /**
     * Get UV index icon (1-11).
     */
    public static function getUvIndexIcon(int $uvIndex): string
    {
        $index = min(11, max(1, $uvIndex));
        return "uv-index-{$index}";
    }

    /**
     * Get Beaufort wind scale icon (0-12).
     */
    public static function getBeaufortIcon(int $beaufort): string
    {
        $scale = min(12, max(0, $beaufort));
        return "wind-beaufort-{$scale}";
    }

    /**
     * Get pressure trend icon.
     */
    public static function getPressureIcon(string $trend): string
    {
        $trend = strtolower($trend);

        if (str_contains($trend, 'stijg') || str_contains($trend, 'ris')) {
            return 'pressure-high';
        }
        if (str_contains($trend, 'dal') || str_contains($trend, 'fall')) {
            return 'pressure-low';
        }

        return 'barometer';
    }

    /**
     * Get thermometer icon based on temperature or trend.
     */
    public static function getThermometerIcon(?float $temperature = null, ?string $trend = null): string
    {
        if ($trend !== null) {
            $trend = strtolower($trend);
            if (str_contains($trend, 'warm') || str_contains($trend, 'ris')) {
                return 'thermometer-warmer';
            }
            if (str_contains($trend, 'cold') || str_contains($trend, 'cool') || str_contains($trend, 'dal')) {
                return 'thermometer-colder';
            }
        }

        if ($temperature !== null) {
            if ($temperature > 25) {
                return 'thermometer-warmer';
            }
            if ($temperature < 5) {
                return 'thermometer-colder';
            }
        }

        return 'thermometer';
    }

    /**
     * Normalize weather symbols from different API formats to common terms.
     * This allows the same icon mapping logic to work with multiple providers.
     */
    private static function normalizeSymbol(string $symbol): string
    {
        // Aeris Weather codes (uppercase like TSTM, RAIN, SNOW)
        $aerisMap = [
            'tstm' => 'thunder',
            'rain' => 'rain',
            'snow' => 'snow',
            'sleet' => 'sleet',
            'fzra' => 'sleet',           // Freezing rain
            'fzdz' => 'sleet',           // Freezing drizzle
            'clear' => 'clearsky',
            'sunny' => 'clearsky',
            'pcloudy' => 'partlycloudy',
            'mcloudy' => 'cloudy',
            'cloudy' => 'cloudy',
            'fog' => 'fog',
            'haze' => 'fog',
            'mist' => 'fog',
            'drizzle' => 'lightrain',
            'flurries' => 'lightsnow',
            'blizzard' => 'heavysnow',
            'wind' => 'wind',
            'dust' => 'dust',
            'smoke' => 'smoke',
        ];

        // WeatherFlow / generic text conditions
        $textMap = [
            'thunderstorm' => 'thunder',
            'thunderstorms' => 'thunder',
            'storm' => 'thunder',
            'rainy' => 'rain',
            'raining' => 'rain',
            'showers' => 'rainshowers',
            'drizzle' => 'lightrain',
            'drizzling' => 'lightrain',
            'snowy' => 'snow',
            'snowing' => 'snow',
            'flurries' => 'lightsnow',
            'blizzard' => 'heavysnow',
            'sleet' => 'sleet',
            'freezing' => 'sleet',
            'hail' => 'sleet',
            'clear' => 'clearsky',
            'sunny' => 'clearsky',
            'fine' => 'clearsky',
            'fair' => 'fair',
            'partly cloudy' => 'partlycloudy',
            'mostly cloudy' => 'cloudy',
            'overcast' => 'cloudy',
            'foggy' => 'fog',
            'misty' => 'fog',
            'hazy' => 'fog',
            'windy' => 'wind',
            'breezy' => 'wind',
        ];

        // Check Aeris codes first (they're typically short uppercase)
        foreach ($aerisMap as $code => $normalized) {
            if ($symbol === $code || str_starts_with($symbol, $code)) {
                return $normalized;
            }
        }

        // Check text-based conditions
        foreach ($textMap as $text => $normalized) {
            if (str_contains($symbol, $text)) {
                return $normalized;
            }
        }

        // Return as-is if no mapping found (likely already Yr.no format)
        return $symbol;
    }

    /**
     * Map common emojis to icon names.
     */
    public static function emojiToIcon(string $emoji): ?string
    {
        return match ($emoji) {
            // Weather conditions
            '☀️' => 'clear-day',
            '🌤️' => 'partly-cloudy-day',
            '⛅' => 'partly-cloudy-day',
            '☁️' => 'cloudy',
            '🌧️' => 'rain',
            '🌦️' => 'partly-cloudy-day-rain',
            '🌨️' => 'sleet',
            '❄️' => 'snow',
            '⛈️' => 'thunderstorms',
            '🌫️' => 'fog',

            // Moon phases
            '🌑' => 'moon-new',
            '🌒' => 'moon-waxing-crescent',
            '🌓' => 'moon-first-quarter',
            '🌔' => 'moon-waxing-gibbous',
            '🌕' => 'moon-full',
            '🌖' => 'moon-waning-gibbous',
            '🌗' => 'moon-last-quarter',
            '🌘' => 'moon-waning-crescent',
            '🌙' => 'moon-waxing-crescent',
            '🌚' => 'moon-new',
            '🌝' => 'moon-full',

            // Sun events
            '🌅' => 'sunrise',
            '🌇' => 'sunset',

            // Other weather elements
            '💨' => 'wind',
            '⚡' => 'lightning-bolt',
            '✨' => 'star',
            '☄️' => 'falling-stars',
            '🌌' => 'starry-night',
            '🌡️' => 'thermometer',
            '💧' => 'humidity',

            default => null,
        };
    }

    /**
     * Get icon credits/attribution info.
     */
    public static function getCredits(): array
    {
        return [
            'name' => 'Basmilius Weather Icons',
            'author' => 'Bas Milius',
            'url' => 'https://github.com/basmilius/weather-icons',
            'license' => 'MIT',
        ];
    }
}
