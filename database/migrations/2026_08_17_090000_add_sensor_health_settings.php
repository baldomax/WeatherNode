<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-sensor health tracking gained an admin control. Upgrades only run
 * migrations, never the seeder, so the settings have to arrive here.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'sensor_health.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'sensors', 'description' => 'Track individual sensors over time and alert when one stops reporting (e.g. empty battery)'],
        ['key' => 'sensor_health.track_days', 'value' => '7', 'type' => 'integer', 'group' => 'sensors', 'description' => 'Consider a sensor "active" if it reported in the last N days'],
        ['key' => 'sensor_health.fail_minutes', 'value' => '30', 'type' => 'integer', 'group' => 'sensors', 'description' => 'Alert if an active sensor has not reported in this many minutes'],
    ];

    private const OLD_DEFAULT_FAIL_MINUTES = '120';

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            DB::table('settings')->insertOrIgnore(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 120 shipped as the default while the value was unreachable from the
        // admin panel, so it reflects no decision. Anything else is left alone.
        DB::table('settings')
            ->where('key', 'sensor_health.fail_minutes')
            ->where('value', self::OLD_DEFAULT_FAIL_MINUTES)
            ->update(['value' => '30', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'sensor_health.fail_minutes')
            ->where('value', '30')
            ->update(['value' => self::OLD_DEFAULT_FAIL_MINUTES, 'updated_at' => now()]);
    }
};
