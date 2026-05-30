<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'navigation.forecast_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Forecast in navigation and allow route access'],
        ['key' => 'navigation.history_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show History in navigation and allow route access'],
        ['key' => 'navigation.statistics_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Statistics in navigation and allow route access'],
        ['key' => 'navigation.radar_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Radar in navigation and allow route access'],
        ['key' => 'navigation.satellite_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Satellite in navigation and allow route access'],
        ['key' => 'navigation.air_pollen_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Air & Pollen in navigation and allow route access'],
        ['key' => 'navigation.astronomy_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Astronomy in navigation and allow route access'],
        ['key' => 'navigation.sky_water_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Sky & Water in navigation and allow route access'],
        ['key' => 'navigation.fire_weather_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Fire Weather in navigation and allow route access'],
        ['key' => 'navigation.earthquakes_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Earthquakes in navigation and allow route access'],
        ['key' => 'navigation.alerts_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'navigation', 'description' => 'Show Alerts in navigation and allow route access'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            DB::table('settings')->insertOrIgnore(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
    }
};
