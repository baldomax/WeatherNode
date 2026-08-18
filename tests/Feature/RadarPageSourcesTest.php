<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadarPageSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function useProvider(string $provider): void
    {
        Setting::setValue('radar.provider', $provider, 'select', 'radar');
    }

    /** #46: the setting was only read by the dashboard widget. */
    public function test_animation_speed_comes_from_the_configured_frame_delay(): void
    {
        $this->useProvider('rainviewer');
        Setting::setValue('radar.frame_delay', '1500', 'select', 'radar');

        $response = $this->get(route('radar'));

        $response->assertOk();
        $response->assertSee('frameDelay: 1500', false);
        $response->assertDontSee('frameDelay: 800', false);
    }

    /** #47: a station outside the Netherlands should not be served NL-only maps. */
    public function test_dutch_sources_are_hidden_when_another_provider_is_configured(): void
    {
        $this->useProvider('rainviewer');

        $response = $this->get(route('radar'));

        $response->assertOk();
        $response->assertDontSee('api.buienradar.nl', false);
        $response->assertDontSee('cdn.knmi.nl', false);
    }

    public function test_dutch_sources_are_offered_when_a_dutch_provider_is_configured(): void
    {
        $this->useProvider('buienradar');

        $response = $this->get(route('radar'));

        $response->assertOk();
        $response->assertSee('api.buienradar.nl', false);
    }

    /** #47, final note: the satellite panel ignored its own enabled flag. */
    public function test_the_satellite_panel_respects_its_setting(): void
    {
        $this->useProvider('rainviewer');
        Setting::setValue('satellite.enabled', false, 'boolean', 'satellite');

        $this->get(route('radar'))->assertOk()->assertDontSee('id="radar-satellite-image"', false);

        Setting::setValue('satellite.enabled', true, 'boolean', 'satellite');

        $this->get(route('radar'))->assertOk()->assertSee('id="radar-satellite-image"', false);
    }
}
