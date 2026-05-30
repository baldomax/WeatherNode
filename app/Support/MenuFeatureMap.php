<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

class MenuFeatureMap
{
    public const FEATURE_FORECAST = 'forecast';
    public const FEATURE_HISTORY = 'history';
    public const FEATURE_STATISTICS = 'statistics';
    public const FEATURE_RADAR = 'radar';
    public const FEATURE_SATELLITE = 'satellite';
    public const FEATURE_AIR_POLLEN = 'air_pollen';
    public const FEATURE_ASTRONOMY = 'astronomy';
    public const FEATURE_SKY_WATER = 'sky_water';
    public const FEATURE_FIRE_WEATHER = 'fire_weather';
    public const FEATURE_EARTHQUAKES = 'earthquakes';
    public const FEATURE_ALERTS = 'alerts';

    /**
     * Feature key => settings key.
     * Home and community stations are intentionally excluded and always enabled.
     *
     * @var array<string, string>
     */
    private const SETTING_KEYS = [
        self::FEATURE_FORECAST => 'navigation.forecast_enabled',
        self::FEATURE_HISTORY => 'navigation.history_enabled',
        self::FEATURE_STATISTICS => 'navigation.statistics_enabled',
        self::FEATURE_RADAR => 'navigation.radar_enabled',
        self::FEATURE_SATELLITE => 'navigation.satellite_enabled',
        self::FEATURE_AIR_POLLEN => 'navigation.air_pollen_enabled',
        self::FEATURE_ASTRONOMY => 'navigation.astronomy_enabled',
        self::FEATURE_SKY_WATER => 'navigation.sky_water_enabled',
        self::FEATURE_FIRE_WEATHER => 'navigation.fire_weather_enabled',
        self::FEATURE_EARTHQUAKES => 'navigation.earthquakes_enabled',
        self::FEATURE_ALERTS => 'navigation.alerts_enabled',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::SETTING_KEYS);
    }

    public static function has(string $feature): bool
    {
        return array_key_exists($feature, self::SETTING_KEYS);
    }

    public static function settingKey(string $feature): ?string
    {
        return self::SETTING_KEYS[$feature] ?? null;
    }

    public static function enabled(string $feature): bool
    {
        $settingKey = self::settingKey($feature);
        if ($settingKey === null) {
            return true;
        }

        return (bool) Setting::getValue($settingKey, true);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $map = [
            'home' => true,
            'community_stations' => true,
        ];

        foreach (self::SETTING_KEYS as $feature => $settingKey) {
            $map[$feature] = (bool) Setting::getValue($settingKey, true);
        }

        return $map;
    }
}
