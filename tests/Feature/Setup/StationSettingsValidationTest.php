<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wizard refuses a latitude of 999. The ordinary Station Info page was the
 * back door: nothing in the app validated coordinates, so 999 was stored, and
 * a cleared field became 0.0, which is a real place in the Gulf of Guinea
 * where date_sun_info returns perfectly plausible and entirely wrong times.
 */
class StationSettingsValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'station_name' => 'Testfield Weather',
            'station_latitude' => '52.1234',
            'station_longitude' => '4.5678',
            'station_timezone' => 'Europe/Madrid',
        ], $overrides);
    }

    private function save(array $payload)
    {
        $this->seed(SettingsSeeder::class);

        return $this->actingAs($this->admin())
            ->post(route('admin.settings.update', 'station'), $payload);
    }

    public function test_it_rejects_an_impossible_latitude(): void
    {
        $this->save($this->payload(['station_latitude' => '999']))
            ->assertSessionHasErrors('station_latitude');

        $this->assertSame(Setting::DEFAULT_LATITUDE, Setting::latitude());
    }

    public function test_it_rejects_an_impossible_longitude(): void
    {
        $this->save($this->payload(['station_longitude' => '1000']))
            ->assertSessionHasErrors('station_longitude');
    }

    /** A cleared field used to become 0.0 without a word. */
    public function test_it_rejects_a_cleared_coordinate(): void
    {
        $this->save($this->payload(['station_latitude' => '']))
            ->assertSessionHasErrors('station_latitude');
    }

    /** new DateTimeZone('') throws, and the dashboard builds a date from it. */
    public function test_it_rejects_a_timezone_that_does_not_exist(): void
    {
        $this->save($this->payload(['station_timezone' => 'Mars/Olympus_Mons']))
            ->assertSessionHasErrors('station_timezone');
    }

    public function test_a_real_position_still_saves(): void
    {
        $this->save($this->payload())->assertSessionHasNoErrors();

        $this->assertSame(52.1234, Setting::latitude());
        $this->assertSame(4.5678, Setting::longitude());
        $this->assertSame('Europe/Madrid', Setting::timezone());
    }

    /** Saving a different group must not demand station fields. */
    public function test_another_settings_page_is_not_affected(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.update', 'display'), ['display_theme' => 'dark'])
            ->assertSessionHasNoErrors();
    }
}
