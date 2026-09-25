<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The telemetry page shows "this is what will be shared" so an owner can
 * decide before switching it on. It only built that list once telemetry was
 * already enabled and saved, so the one moment it was wanted was the one
 * moment it was missing.
 */
class TelemetryPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_it_shows_what_would_be_shared_before_it_is_switched_on(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('telemetry.enabled', false, 'boolean', 'telemetry');
        Setting::setValue('station.name', 'Testfield Weather', 'string', 'station');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.telemetry'))
            ->assertOk()
            ->assertSee('Your Station Data')
            ->assertSee('Testfield Weather');
    }

    public function test_it_still_shows_it_once_switched_on(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('telemetry.enabled', true, 'boolean', 'telemetry');
        Setting::setValue('station.name', 'Testfield Weather', 'string', 'station');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.telemetry'))
            ->assertOk()
            ->assertSee('Testfield Weather');
    }

    /**
     * The seeder says "leave empty to use APP_URL", but an empty row is not a
     * missing one: getValue hands back the blank and the default never fires.
     * A station shared with no address cannot be linked to from the map.
     */
    public function test_a_blank_server_url_falls_back_to_the_site_address(): void
    {
        $this->seed(SettingsSeeder::class);
        config(['app.url' => 'https://weather.example.com']);
        Setting::setValue('station.server_url', '', 'string', 'station');

        $data = app(\App\Services\Telemetry\TelemetryService::class)->previewStationData();

        $this->assertSame('https://weather.example.com', $data['url']);
    }

    public function test_a_server_url_that_is_set_is_the_one_used(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('station.server_url', 'https://my.station.example/', 'string', 'station');

        $data = app(\App\Services\Telemetry\TelemetryService::class)->previewStationData();

        $this->assertSame('https://my.station.example', $data['url']);
    }

    /** The stored value is a key. A person reads the name. */
    public function test_the_maker_is_shown_by_name(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('station.manufacturer', 'davis', 'select', 'station');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.telemetry'))
            ->assertSee('Davis Instruments')
            ->assertDontSee('>davis<', false);
    }

    /** An empty cell says nothing. The page has an N/A for this. */
    public function test_unknown_hardware_reads_as_not_available(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('station.hardware', '', 'string', 'station');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.telemetry'))
            ->assertSee('N/A');
    }

    /**
     * Showing it is not sharing it. Nothing may be sent while the setting is
     * off, whatever the page displays.
     */
    public function test_nothing_is_collected_for_sending_while_it_is_off(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('telemetry.enabled', false, 'boolean', 'telemetry');

        $service = app(\App\Services\Telemetry\TelemetryService::class);

        $this->assertNull($service->collectStationData());
        $this->assertNotNull($service->previewStationData());
    }
}
