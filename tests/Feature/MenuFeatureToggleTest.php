<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MenuFeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_feature_redirects_standard_requests_to_home(): void
    {
        Setting::setValue('navigation.forecast_enabled', false, 'boolean', 'navigation');

        $response = $this->get(route('forecast'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('feature_disabled');
    }

    public function test_disabled_feature_returns_json_404_for_json_requests(): void
    {
        Setting::setValue('navigation.forecast_enabled', false, 'boolean', 'navigation');

        $response = $this->getJson(route('forecast'));

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Feature is disabled',
            'feature' => 'forecast',
        ]);
    }

    public function test_navigation_hides_disabled_feature_links_but_keeps_community_stations(): void
    {
        Setting::setValue('navigation.forecast_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.air_pollen_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.sky_water_enabled', false, 'boolean', 'navigation');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('href="' . route('forecast') . '"', false);
        $response->assertDontSee('href="' . route('airquality') . '"', false);
        $response->assertDontSee('href="' . route('aviation') . '"', false);
        $response->assertSee('href="' . route('weather.community-stations') . '"', false);
    }

    public function test_dashboard_widgets_do_not_link_to_astronomy_when_feature_disabled(): void
    {
        Setting::setValue('navigation.astronomy_enabled', false, 'boolean', 'navigation');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('href="' . route('astronomy') . '"', false);
        $response->assertSee('Page disabled', false);
    }

    public function test_disabled_feature_widgets_stay_visible_but_links_are_removed(): void
    {
        Setting::setValue('navigation.air_pollen_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.sky_water_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.earthquakes_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.alerts_enabled', false, 'boolean', 'navigation');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-widget="pollen"', false);
        $response->assertSee('data-widget="tide"', false);
        $response->assertSee('data-widget="metar"', false);
        $response->assertSee('data-widget="earthquakes"', false);
        $response->assertSee('data-widget="alerts"', false);
        $response->assertDontSee('href="' . route('pollen') . '"', false);
        $response->assertDontSee('href="' . route('water') . '"', false);
        $response->assertDontSee('href="' . route('aviation') . '"', false);
        $response->assertDontSee('href="' . route('earthquakes') . '"', false);
        $response->assertDontSee('href="' . route('alerts') . '"', false);
        $response->assertSee('Page disabled', false);
    }

    public function test_admin_widgets_page_notifies_when_linked_page_feature_is_disabled(): void
    {
        Setting::setValue('navigation.astronomy_enabled', false, 'boolean', 'navigation');

        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get(route('admin.settings.widgets'));

        $response->assertOk();
        $response->assertSee('Linked page is disabled in Navigation settings (Astronomy).', false);
        $response->assertSee(route('admin.settings.group', 'navigation'), false);
    }

    public function test_sitemap_excludes_disabled_feature_pages(): void
    {
        Setting::setValue('navigation.forecast_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.sky_water_enabled', false, 'boolean', 'navigation');

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee(url('/forecast'));
        $response->assertDontSee(url('/aviation'));
        $response->assertDontSee(url('/water'));
        $response->assertSee(url('/community-stations'));
    }

    public function test_air_pollen_toggle_disables_all_grouped_routes(): void
    {
        Setting::setValue('navigation.air_pollen_enabled', false, 'boolean', 'navigation');

        $this->get(route('airquality'))->assertRedirect(route('home'));
        $this->get(route('noise'))->assertRedirect(route('home'));
        $this->get(route('pollen'))->assertRedirect(route('home'));
    }

    public function test_sky_water_toggle_disables_all_grouped_routes(): void
    {
        Setting::setValue('navigation.sky_water_enabled', false, 'boolean', 'navigation');

        $this->get(route('aviation'))->assertRedirect(route('home'));
        $this->get(route('water'))->assertRedirect(route('home'));
        $this->get(route('water.waves'))->assertRedirect(route('home'));
    }

    public function test_home_and_community_stations_routes_are_not_guarded_by_feature_middleware(): void
    {
        $homeRoute = Route::getRoutes()->getByName('home');
        $communityRoute = Route::getRoutes()->getByName('weather.community-stations');

        $this->assertNotNull($homeRoute);
        $this->assertNotNull($communityRoute);
        $this->assertNotContains('feature.menu', $homeRoute->gatherMiddleware());
        $this->assertNotContains('feature.menu', $communityRoute->gatherMiddleware());
    }
}
