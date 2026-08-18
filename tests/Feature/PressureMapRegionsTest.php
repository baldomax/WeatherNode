<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PressureMapRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page only ever offered four charts. NOAA publishes the same unified
 * analysis for several more regions at stable URLs, so they are worth having.
 */
class PressureMapRegionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_regional_charts_are_registered(): void
    {
        foreach (['canada', 'alaska', 'mexico', 'hawaii', 'us_east', 'us_west',
            'atlantic_tropics', 'pacific_tropics', 'northern_hemisphere'] as $map) {
            $this->assertTrue(PressureMapRegistry::exists($map), "{$map} is not registered");
            $this->assertStringStartsWith('https://', (string) PressureMapRegistry::urlFor($map));
        }
    }

    public function test_every_registered_chart_is_offered_on_the_page(): void
    {
        $response = $this->get('/pressure-map');
        $response->assertOk();

        foreach (PressureMapRegistry::names() as $map) {
            $response->assertSee('value="'.$map.'"', false);
        }
    }

    /** A chart nobody can pick is a chart nobody can see. */
    public function test_every_registered_chart_has_a_label(): void
    {
        foreach (PressureMapRegistry::all() as $map => $chart) {
            $this->assertNotSame('', trim($chart['label']), "{$map} has no label");
        }
    }

    public function test_the_labels_are_translated(): void
    {
        \App\Models\Setting::setValue('display.language', 'nl-nl', 'string', 'display');

        $response = $this->get('/pressure-map');
        $response->assertOk();
        $response->assertSee('Noordelijk halfrond', false);
        $response->assertSee('Oostkust van de VS', false);
    }

    public function test_every_registered_chart_has_a_proxy_url_on_the_page(): void
    {
        $response = $this->get('/pressure-map');

        foreach (PressureMapRegistry::names() as $map) {
            $this->assertArrayHasKey($map, $response->viewData('mapUrls'));
        }
    }
}
