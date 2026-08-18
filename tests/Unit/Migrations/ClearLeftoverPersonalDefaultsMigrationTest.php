<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClearLeftoverPersonalDefaultsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function storeSetting(string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'type' => 'string', 'group' => 'station', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function valueOf(string $key): ?string
    {
        return DB::table('settings')->where('key', $key)->value('value');
    }

    public function test_it_clears_settings_still_holding_the_shipped_value(): void
    {
        $this->storeSetting('webcam.url', 'https://www.meteouitgeest.nl/thumbnail/image.jpg');
        $this->storeSetting('station.server_url', 'https://meteouitgeest.nl/');

        $this->migration()->up();

        $this->assertSame('', $this->valueOf('webcam.url'));
        $this->assertSame('', $this->valueOf('station.server_url'));
    }

    public function test_it_leaves_a_configured_value_alone(): void
    {
        $this->storeSetting('webcam.url', 'https://example.test/cam.jpg');
        $this->storeSetting('station.server_url', 'https://weather.example.test/');

        $this->migration()->up();

        $this->assertSame('https://example.test/cam.jpg', $this->valueOf('webcam.url'));
        $this->assertSame('https://weather.example.test/', $this->valueOf('station.server_url'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_18_140000_clear_leftover_personal_defaults.php');
    }
}
