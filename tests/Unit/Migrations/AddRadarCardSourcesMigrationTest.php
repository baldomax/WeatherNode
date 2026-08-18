<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddRadarCardSourcesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function stationAt(float $lat, float $lon): void
    {
        DB::table('settings')->where('key', 'radar.card_sources')->delete();
        foreach ([['station.latitude', $lat], ['station.longitude', $lon]] as [$key, $value]) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'type' => 'float', 'group' => 'station', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function value(): ?string
    {
        return DB::table('settings')->where('key', 'radar.card_sources')->value('value');
    }

    public function test_a_dutch_station_keeps_the_dutch_cards(): void
    {
        $this->stationAt(52.5163996, 4.7078991); // Uitgeest

        $this->migration()->up();

        $this->assertSame('knmi,buienradar', $this->value());
    }

    public function test_a_station_elsewhere_starts_with_none(): void
    {
        $this->stationAt(59.4370, 24.7536); // Tallinn

        $this->migration()->up();

        $this->assertSame('', $this->value());
    }

    public function test_an_existing_choice_is_never_overwritten(): void
    {
        $this->stationAt(59.4370, 24.7536);
        DB::table('settings')->insert([
            'key' => 'radar.card_sources', 'value' => 'knmi', 'type' => 'string',
            'group' => 'radar', 'description' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertSame('knmi', $this->value());
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_18_120000_add_radar_card_sources_setting.php');
    }
}
