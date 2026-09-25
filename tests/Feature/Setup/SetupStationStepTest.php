<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use App\Support\FirstRunSetup;
use Database\Seeders\FirstRunSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step one asks where the station is. Latitude and longitude are the two
 * things nobody can answer from memory, so the form offers a map and the
 * browser's own location, and the timezone arrives pre-selected.
 */
class SetupStationStepTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Testfield Weather',
            'location' => 'Testfield, Anywhere',
            'latitude' => '52.1234',
            'longitude' => '4.5678',
            'elevation' => '12',
            'timezone' => 'Europe/Madrid',
        ], $overrides);
    }

    public function test_the_station_step_opens(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.station'))
            ->assertOk();
    }

    /** Typing two numbers has to stay possible with no map and no network. */
    public function test_it_offers_a_map_and_the_browsers_own_location(): void
    {
        $this->seed(FirstRunSeeder::class);

        $response = $this->actingAs($this->admin())->get(route('admin.setup.station'));

        $response->assertSee('leaflet', false);
        $response->assertSee('setup-map', false);
        $response->assertSee('setup-locate', false);
        $response->assertSee('name="latitude"', false);
        $response->assertSee('name="longitude"', false);
    }

    public function test_the_timezone_list_holds_every_identifier(): void
    {
        $this->seed(FirstRunSeeder::class);

        $response = $this->actingAs($this->admin())->get(route('admin.setup.station'));

        $response->assertSee('value="Pacific/Auckland"', false);
        $response->assertSee('value="America/Argentina/Ushuaia"', false);
        $response->assertSee('value="Africa/Nairobi"', false);
    }

    public function test_it_saves_the_station_and_moves_on(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload())
            ->assertRedirect(route('admin.setup.source'));

        $this->assertSame('Testfield Weather', Setting::stationName());
        $this->assertSame('Testfield, Anywhere', Setting::stationLocation());
        $this->assertSame(52.1234, Setting::latitude());
        $this->assertSame(4.5678, Setting::longitude());
        $this->assertSame('Europe/Madrid', Setting::timezone());
        $this->assertSame(FirstRunSetup::SOURCE, FirstRunSetup::state());
    }

    /**
     * The address the community map links back to, and what this site tells
     * other services about itself. Nobody should have to go and find it.
     */
    public function test_the_site_address_is_filled_in_already(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.station'))
            ->assertOk()
            ->assertSee('name="server_url"', false)
            ->assertSee('value="' . rtrim(config('app.url'), '/') . '"', false);
    }

    public function test_the_site_address_can_be_changed(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload([
                'server_url' => 'https://weather.example.com',
            ]));

        $this->assertSame('https://weather.example.com', Setting::getValue('station.server_url'));
    }

    public function test_it_rejects_an_address_that_is_not_one(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload(['server_url' => 'not a url']))
            ->assertSessionHasErrors('server_url');
    }

    /**
     * setValue rewrites the row's type as well as its value, so a wizard that
     * passes the wrong one quietly changes how that setting is read
     * everywhere. Elevation is declared a float and must stay one.
     */
    public function test_saving_keeps_the_declared_types(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);
        $before = Setting::whereIn('key', [
            'station.name', 'station.location', 'station.latitude',
            'station.longitude', 'station.elevation', 'station.timezone',
        ])->pluck('type', 'key')->all();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload());

        $after = Setting::whereIn('key', array_keys($before))->pluck('type', 'key')->all();

        $this->assertSame($before, $after);
    }

    /** Nothing in the app validated coordinates before. 999 was accepted. */
    public function test_it_rejects_an_impossible_latitude(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload(['latitude' => '999']))
            ->assertSessionHasErrors('latitude');

        $this->assertSame(FirstRunSetup::STATION, FirstRunSetup::state());
    }

    public function test_it_rejects_an_impossible_longitude(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload(['longitude' => '-1000']))
            ->assertSessionHasErrors('longitude');
    }

    /** A blank timezone throws on the dashboard, so it cannot be optional. */
    public function test_it_rejects_a_timezone_that_does_not_exist(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload(['timezone' => 'Mars/Olympus_Mons']))
            ->assertSessionHasErrors('timezone');
    }

    public function test_it_rejects_an_empty_name(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    /** The wizard is for admins, like every other page that writes settings. */
    public function test_a_normal_user_cannot_open_it(): void
    {
        $this->seed(FirstRunSeeder::class);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.setup.station'))
            ->assertRedirect(route('dashboard'));
    }
}
