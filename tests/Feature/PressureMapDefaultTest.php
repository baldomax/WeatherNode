<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The default map was chosen from longitude alone, with no Europe case, so
 * every European station fell through to a mid-Atlantic ocean chart.
 */
class PressureMapDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function station(float $lat, float $lon): void
    {
        Setting::setValue('station.latitude', (string) $lat, 'float', 'station');
        Setting::setValue('station.longitude', (string) $lon, 'float', 'station');
    }

    private function defaultMap(): string
    {
        $response = $this->get('/pressure-map');
        $response->assertOk();

        return $response->viewData('defaultMap');
    }

    public function test_a_european_station_gets_the_europe_map(): void
    {
        $this->station(52.5164, 4.7079);        // Netherlands
        $this->assertSame('europe', $this->defaultMap());

        $this->station(43.6626, 10.6373);       // Italy
        $this->assertSame('europe', $this->defaultMap());
    }

    public function test_the_existing_buckets_are_unchanged(): void
    {
        $this->station(38.9, -77.0);            // Washington DC
        $this->assertSame('us', $this->defaultMap());

        $this->station(21.3, -157.8);           // Honolulu
        $this->assertSame('pacific', $this->defaultMap());

        $this->station(-23.5, -46.6);           // Sao Paulo, still Atlantic
        $this->assertSame('atlantic', $this->defaultMap());
    }

    /** Same longitude as Europe but far south, so not the Europe chart. */
    public function test_latitude_is_required_not_just_longitude(): void
    {
        $this->station(-33.9, 18.4);            // Cape Town
        $this->assertSame('atlantic', $this->defaultMap());
    }

    public function test_the_query_parameter_still_overrides(): void
    {
        $this->station(38.9, -77.0);

        $response = $this->get('/pressure-map?map=europe');
        $response->assertOk();
        $this->assertSame('europe', $response->viewData('defaultMap'));
    }

    public function test_an_unknown_map_falls_back_to_the_location_default(): void
    {
        $this->station(52.5164, 4.7079);

        $response = $this->get('/pressure-map?map=' . urlencode('../etc'));
        $response->assertOk();
        $this->assertSame('europe', $response->viewData('defaultMap'));
    }
}
