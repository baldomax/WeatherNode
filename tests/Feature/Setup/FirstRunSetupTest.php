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
 * Issue #105. A fresh install has to be told where it is, and nothing asked.
 * The owner had to find Settings, then Station Info, then know their own
 * latitude to four decimal places.
 *
 * The flag that drives this is a single settings row, and a missing row means
 * finished. That one rule is what keeps every existing install, and the whole
 * test suite, out of the wizard: neither has the row.
 */
class FirstRunSetupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_a_missing_flag_means_finished(): void
    {
        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
        $this->assertFalse(FirstRunSetup::pending());
    }

    /**
     * Seen in a container. Every `docker exec php artisan ...` runs as root,
     * and anything it reads through Setting::getValue leaves a root-owned file
     * in the cache. The web process runs as www-data, so its Cache::forget on
     * that key fails silently and it reads the stale value for an hour.
     *
     * A stuck flag is either a wizard that will not go away or a notice that
     * hides real work, so this one value is read from its row every time.
     */
    public function test_the_state_comes_from_the_row_and_not_a_stale_cache(): void
    {
        $this->seed(FirstRunSeeder::class);
        FirstRunSetup::state();

        // A write from somewhere this process cannot invalidate.
        Setting::where('key', FirstRunSetup::SETTING)->update(['value' => FirstRunSetup::DONE]);

        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    public function test_a_fresh_install_starts_at_the_station_step(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->assertSame(FirstRunSetup::STATION, FirstRunSetup::state());
        $this->assertTrue(FirstRunSetup::pending());
    }

    /**
     * Migrations create settings rows of their own, so an empty table is not
     * the test for a first run. An install that already has its station
     * settings must never be sent back to the wizard.
     */
    public function test_an_existing_install_is_never_sent_to_setup(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->seed(FirstRunSeeder::class);

        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    public function test_re_running_the_seeder_does_not_reopen_a_finished_setup(): void
    {
        $this->seed(FirstRunSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::DONE);

        $this->seed(FirstRunSeeder::class);

        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    public function test_the_notice_is_gone_once_setup_is_finished(): void
    {
        $this->seed(FirstRunSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::DONE);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Finish setting up your station');
    }

    /** Telling somebody to go where they already are is just noise. */
    public function test_the_notice_stays_off_the_setup_pages_themselves(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.station'))
            ->assertOk()
            ->assertDontSee('Finish setting up your station');
    }

    /**
     * Doing it later stops the redirect but not the notice, which is the only
     * state the notice is seen in: while setup is owed, the admin area takes
     * you to the step rather than mentioning it.
     */
    public function test_skipping_leaves_the_notice_in_place(): void
    {
        $this->seed(FirstRunSeeder::class);

        $this->actingAs($this->admin())->post(route('admin.setup.skip'));

        $this->assertSame(FirstRunSetup::SKIPPED, FirstRunSetup::state());
        $this->assertFalse(FirstRunSetup::pending());
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertSee('Finish setting up your station');
    }
}
