<?php

declare(strict_types=1);

namespace Tests\Feature\Marine;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marine APIs only return data for ocean grid cells. An inland station gets
 * nothing back, and moving the station coordinates is not an option because
 * everything else uses them. So the marine lookups get their own optional
 * point, falling back to the station when unset.
 */
class MarineCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private function station(float $lat, float $lon): void
    {
        Setting::setValue('station.latitude', (string) $lat, 'float', 'station');
        Setting::setValue('station.longitude', (string) $lon, 'float', 'station');
    }

    public function test_it_falls_back_to_the_station_when_unset(): void
    {
        $this->station(43.6626, 10.6373);

        $this->assertSame(43.6626, Setting::marineLatitude());
        $this->assertSame(10.6373, Setting::marineLongitude());
    }

    public function test_a_configured_coastal_point_is_used_instead(): void
    {
        $this->station(43.6626, 10.6373);   // inland Tuscany
        Setting::setValue('marine.latitude', '43.5000', 'string', 'marine');
        Setting::setValue('marine.longitude', '10.2500', 'string', 'marine');

        $this->assertSame(43.5, Setting::marineLatitude());
        $this->assertSame(10.25, Setting::marineLongitude());
    }

    public function test_a_blank_value_is_treated_as_unset(): void
    {
        $this->station(52.5, 4.7);
        Setting::setValue('marine.latitude', '', 'string', 'marine');
        Setting::setValue('marine.longitude', '', 'string', 'marine');

        $this->assertSame(52.5, Setting::marineLatitude());
        $this->assertSame(4.7, Setting::marineLongitude());
    }

    public function test_the_admin_page_offers_the_fields_and_saves_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.group', 'waves'))
            ->assertOk()
            ->assertSee('marine_latitude', false)
            ->assertSee('marine_longitude', false);

        $this->actingAs($admin)
            ->post(route('admin.settings.update', 'waves'), [
                'waves_enabled' => '1',
                'marine_latitude' => '43.5',
                'marine_longitude' => '10.25',
            ])->assertRedirect();

        $this->assertSame(43.5, Setting::marineLatitude());
    }

    public function test_clearing_the_fields_returns_to_the_station(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->station(52.5, 4.7);
        Setting::setValue('marine.latitude', '43.5', 'string', 'marine');

        $this->actingAs($admin)
            ->post(route('admin.settings.update', 'waves'), [
                'waves_enabled' => '1',
                'marine_latitude' => '',
                'marine_longitude' => '',
            ])->assertRedirect();

        $this->assertSame(52.5, Setting::marineLatitude());
    }
}
