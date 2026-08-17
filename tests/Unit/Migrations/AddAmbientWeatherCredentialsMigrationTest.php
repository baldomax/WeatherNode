<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddAmbientWeatherCredentialsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_adds_application_key_and_renames_legacy_device_id(): void
    {
        DB::table('settings')->whereIn('key', [
            'ambient.application_key',
            'ambient.mac_address',
            'ambient.device_id',
        ])->delete();
        DB::table('settings')->insert([
            'key' => 'ambient.device_id',
            'value' => 'AA:BB:CC:DD:EE:FF',
            'type' => 'string',
            'group' => 'ambient',
            'description' => 'Ambient Weather device MAC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'ambient.application_key',
            'value' => '',
            'type' => 'encrypted',
            'group' => 'ambient',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'ambient.mac_address',
            'value' => 'AA:BB:CC:DD:EE:FF',
            'type' => 'string',
            'group' => 'ambient',
        ]);
        $this->assertDatabaseMissing('settings', ['key' => 'ambient.device_id']);
    }

    public function test_up_preserves_existing_mac_address_over_legacy_value(): void
    {
        DB::table('settings')->whereIn('key', ['ambient.mac_address', 'ambient.device_id'])->delete();
        DB::table('settings')->insert([
            [
                'key' => 'ambient.mac_address',
                'value' => 'AA:AA:AA:AA:AA:AA',
                'type' => 'string',
                'group' => 'ambient',
                'description' => 'Ambient Weather device MAC',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ambient.device_id',
                'value' => 'BB:BB:BB:BB:BB:BB',
                'type' => 'string',
                'group' => 'ambient',
                'description' => 'Legacy Ambient Weather device MAC',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'ambient.mac_address',
            'value' => 'AA:AA:AA:AA:AA:AA',
        ]);
        $this->assertDatabaseMissing('settings', ['key' => 'ambient.device_id']);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_07_120000_add_ambient_weather_credentials.php');
    }
}
