<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #105. The radar page shipped with two Dutch radar cards switched on
 * and a satellite provider labelled KNMI that actually hotlinked a GIF from
 * meteociel.fr, so every install in the world pulled a French site's image
 * every five minutes and credited it to a Dutch agency.
 *
 * #47 made the Dutch cards switchable. It did not change what a new install
 * starts with.
 */
class SatelliteDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_shows_no_dutch_radar_cards(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $response = $this->get(route('radar'));

        $response->assertOk();
        $response->assertDontSee('cdn.knmi.nl', false);
        $response->assertDontSee('api.buienradar.nl', false);
    }

    public function test_a_fresh_install_hotlinks_nobody_elses_satellite_image(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->get(route('radar'))->assertOk()->assertDontSee('meteociel', false);
    }

    /** NASA GIBS is a public tile service, and it covers the whole planet. */
    public function test_the_default_satellite_provider_covers_the_whole_world(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('nasa', (string) Setting::getValue('satellite.provider'));
        $this->get(route('radar'))->assertOk()->assertSee('gibs.earthdata.nasa.gov', false);
    }

    /** A Dutch owner switching the cards back on must still get them. */
    public function test_the_dutch_cards_still_work_when_chosen(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        Setting::setValue('radar.card_sources', 'knmi,buienradar', 'string', 'radar');

        $response = $this->get(route('radar'));

        $response->assertSee('cdn.knmi.nl', false);
        $response->assertSee('api.buienradar.nl', false);
    }
}
