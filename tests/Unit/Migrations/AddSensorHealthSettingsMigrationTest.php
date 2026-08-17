<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddSensorHealthSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'sensor_health.enabled',
        'sensor_health.track_days',
        'sensor_health.fail_minutes',
    ];

    private function clearKeys(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->delete();
    }

    public function test_up_creates_the_settings_when_missing(): void
    {
        $this->clearKeys();

        $this->migration()->up();

        $this->assertDatabaseHas('settings', ['key' => 'sensor_health.enabled', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['key' => 'sensor_health.track_days', 'value' => '7']);
        $this->assertDatabaseHas('settings', ['key' => 'sensor_health.fail_minutes', 'value' => '30']);
    }

    /**
     * 120 was the shipped default while no admin control existed, so it is a
     * stale default rather than a choice anyone made.
     */
    public function test_up_lowers_the_untouched_two_hour_default(): void
    {
        $this->clearKeys();
        DB::table('settings')->insert([
            'key' => 'sensor_health.fail_minutes',
            'value' => '120',
            'type' => 'integer',
            'group' => 'sensors',
            'description' => 'Alert if an active sensor has not reported in this many minutes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', ['key' => 'sensor_health.fail_minutes', 'value' => '30']);
    }

    public function test_up_leaves_a_deliberately_chosen_threshold_alone(): void
    {
        $this->clearKeys();
        DB::table('settings')->insert([
            'key' => 'sensor_health.fail_minutes',
            'value' => '240',
            'type' => 'integer',
            'group' => 'sensors',
            'description' => 'Alert if an active sensor has not reported in this many minutes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', ['key' => 'sensor_health.fail_minutes', 'value' => '240']);
    }

    public function test_up_does_not_overwrite_an_existing_enabled_choice(): void
    {
        $this->clearKeys();
        DB::table('settings')->insert([
            'key' => 'sensor_health.enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'sensors',
            'description' => 'Track individual sensors',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', ['key' => 'sensor_health.enabled', 'value' => '0']);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_17_090000_add_sensor_health_settings.php');
    }
}
