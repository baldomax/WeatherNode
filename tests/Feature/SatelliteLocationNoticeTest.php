<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #105. The satellite page warned every station outside the Netherlands
 * that KNMI data might not be relevant to it, whether or not any KNMI data was
 * switched on. On a fresh install it appeared directly above a panel saying no
 * satellite sources were enabled at all.
 *
 * The warning is about one optional layer, so it belongs to that layer.
 */
class SatelliteLocationNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const NOTICE = 'KNMI satellite data covers the Netherlands region';

    private function station(float $latitude, float $longitude): void
    {
        Setting::setValue('station.latitude', (string) $latitude, 'float', 'station');
        Setting::setValue('station.longitude', (string) $longitude, 'float', 'station');
    }

    public function test_no_warning_when_the_dutch_layer_is_not_even_on(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('satellite.wms_enabled', false, 'boolean', 'satellite');
        $this->station(51.8749, 19.3107); // Łódź

        $this->get(route('satellite'))->assertOk()->assertDontSee(self::NOTICE);
    }

    /** With the layer on, a station outside its coverage should be told. */
    public function test_the_warning_appears_when_the_dutch_layer_is_on(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('satellite.wms_enabled', true, 'boolean', 'satellite');
        $this->station(51.8749, 19.3107);

        $this->get(route('satellite'))->assertOk()->assertSee(self::NOTICE);
    }

    public function test_no_warning_for_a_dutch_station(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('satellite.wms_enabled', true, 'boolean', 'satellite');
        $this->station(52.5164, 4.7086);

        $this->get(route('satellite'))->assertOk()->assertDontSee(self::NOTICE);
    }
}
