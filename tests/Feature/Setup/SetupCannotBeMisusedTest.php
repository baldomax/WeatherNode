<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use App\Support\FirstRunSetup;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Both setups hand out something worth having: the first creates an
 * administrator, the second writes settings. Each closes behind itself, and
 * these are the ways somebody would try to prise them open again.
 *
 * The pages 404 or redirect, but a page that merely hides is not closed. What
 * matters is that the POST behind it refuses too, so every case here posts
 * directly rather than going through a form.
 */
class SetupCannotBeMisusedTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function adminPayload(): array
    {
        return [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];
    }

    /** @return array<string, string> */
    private function stationPayload(): array
    {
        return [
            'name' => 'Taken Over',
            'latitude' => '1.0',
            'longitude' => '2.0',
            'timezone' => 'UTC',
        ];
    }

    // ── The admin account form ────────────────────────────────────────────

    public function test_no_second_admin_can_be_posted_once_one_exists(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->post(route('setup.admin.store'), $this->adminPayload())->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    /** Any user at all closes it, not just an admin. */
    public function test_no_admin_can_be_posted_once_any_user_exists(): void
    {
        User::factory()->create(['is_admin' => false]);

        $this->post(route('setup.admin.store'), $this->adminPayload())->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    /**
     * Being logged in as a normal user is not a way back in either. These
     * routes are guest-only, so a signed-in visitor is turned away before the
     * 404 is even reached. Either answer is fine; creating a user is not.
     */
    public function test_a_signed_in_user_cannot_reopen_the_admin_form(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('setup.admin.create'))->assertRedirect();
        $this->actingAs($user)->post(route('setup.admin.store'), $this->adminPayload())->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
        $this->assertSame(1, User::query()->count());
    }

    // ── The station wizard ────────────────────────────────────────────────

    public function test_a_stranger_cannot_write_settings_through_the_wizard(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);

        $this->post(route('admin.setup.station.store'), $this->stationPayload())
            ->assertRedirect(route('login'));

        $this->assertSame('WeatherNode', Setting::stationName());
    }

    public function test_a_signed_in_user_cannot_write_settings_through_the_wizard(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('admin.setup.station.store'), $this->stationPayload())
            ->assertRedirect(route('dashboard'));

        $this->assertSame('WeatherNode', Setting::stationName());
    }

    public function test_a_finished_setup_writes_nothing_more(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.setup.station.store'), $this->stationPayload());
        $this->actingAs($admin)->post(route('admin.setup.source.store'), ['format' => 'wu']);

        $this->assertSame('WeatherNode', Setting::stationName());
        $this->assertSame('ecoLcl', Setting::getValue('livedata.format'));
        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    /**
     * Skipping moves the flag backwards, which is the one way left to reopen
     * a setup that is over.
     */
    public function test_a_finished_setup_cannot_be_reopened_by_skipping(): void
    {
        $this->seed(SettingsSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.setup.skip'));

        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    public function test_a_stranger_cannot_touch_the_skip_route(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);

        $this->post(route('admin.setup.skip'))->assertRedirect(route('login'));

        $this->assertSame(FirstRunSetup::STATION, FirstRunSetup::state());
    }

    public function test_a_signed_in_user_cannot_touch_the_skip_route(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('admin.setup.skip'))->assertRedirect(route('dashboard'));

        $this->assertSame(FirstRunSetup::STATION, FirstRunSetup::state());
    }
}
