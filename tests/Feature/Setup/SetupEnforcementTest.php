<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\User;
use App\Support\FirstRunSetup;
use Database\Seeders\FirstRunSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A notice is easy to scroll past, and an install that does not know where it
 * is publishes wrong weather in the meantime. So while setup is owed, the
 * admin area sends the owner to the step that is owed.
 *
 * It has to be narrow. Never a save, never the wizard itself, never anything
 * asking for JSON, and never the public site, which belongs to visitors.
 */
class SetupEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_admin_area_sends_you_to_the_step_that_is_owed(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.setup.station'));
    }

    public function test_it_follows_you_to_the_second_step(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::SOURCE);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('admin.setup.source'));
    }

    public function test_the_wizard_itself_is_not_redirected(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.station'))
            ->assertOk();
    }

    /** Otherwise the form could never be submitted. */
    public function test_a_save_is_never_blocked(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.station.store'), [
                'name' => 'Testfield Weather',
                'latitude' => '52.1',
                'longitude' => '4.5',
                'timezone' => 'Europe/Madrid',
            ])
            ->assertRedirect(route('admin.setup.source'));
    }

    /** A redirect is not an answer to a request for JSON. */
    public function test_a_request_for_json_is_answered_not_redirected(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);

        $this->actingAs($this->admin())
            ->getJson(route('admin.dashboard'))
            ->assertOk();
    }

    /** The public site belongs to visitors, not to the owner's unfinished jobs. */
    public function test_the_public_site_is_untouched(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->get('/')->assertOk();
        $this->get('/forecast')->assertOk();
    }

    public function test_putting_it_off_stops_the_redirect(): void
    {
        $this->seed(FirstRunSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::SKIPPED);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_a_finished_install_is_never_redirected(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
