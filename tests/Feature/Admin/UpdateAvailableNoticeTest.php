<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\VersionService;
use App\Support\UpdateAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateAvailableNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function bumpedVersion(): string
    {
        // One patch ahead of whatever this checkout reports.
        $current = VersionService::getAppVersion();
        if (preg_match('/^v?(\d{4})\.(\d{2})\.(\d+)/', $current, $m)) {
            return sprintf('v%s.%s.%d', $m[1], $m[2], ((int) $m[3]) + 1);
        }

        return 'v2099.01.1';
    }

    public function test_no_notice_when_nothing_has_been_recorded(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertDontSee('is available');
    }

    public function test_notice_appears_when_a_newer_release_was_recorded(): void
    {
        $newer = $this->bumpedVersion();
        UpdateAvailability::remember($newer);

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('WeatherNode ' . $newer . ' is available', false);
        $response->assertSee(route('admin.settings.updates'), false);
    }

    public function test_no_notice_once_the_running_version_has_caught_up(): void
    {
        UpdateAvailability::remember(VersionService::getAppVersion());

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertDontSee('is available');
    }

    public function test_notice_is_suppressed_when_update_checks_are_disabled(): void
    {
        UpdateAvailability::remember($this->bumpedVersion());
        Setting::setValue(UpdateAvailability::SETTING_ENABLED, false, 'boolean', 'updater');

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertDontSee('is available');
    }

    public function test_rendering_the_admin_area_never_calls_github(): void
    {
        Http::preventStrayRequests();
        UpdateAvailability::remember($this->bumpedVersion());

        // Would throw if the banner reached for the network on a page load.
        $this->actingAs($this->adminUser())->get(route('admin.settings.index'))->assertOk();
    }

    public function test_the_command_records_what_it_found(): void
    {
        $newer = $this->bumpedVersion();
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => $newer,
                'name' => $newer,
                'body' => 'notes',
                'published_at' => '2026-08-09T00:00:00Z',
                'assets' => [],
            ]),
        ]);

        $this->artisan('updater:check')->assertExitCode(0);

        $this->assertSame($newer, UpdateAvailability::latestSeen());
        $this->assertNotNull(UpdateAvailability::checkedAt());
    }

    public function test_the_command_does_nothing_when_checks_are_disabled(): void
    {
        Setting::setValue(UpdateAvailability::SETTING_ENABLED, false, 'boolean', 'updater');
        Http::preventStrayRequests();

        $this->artisan('updater:check')->assertExitCode(0);

        $this->assertNull(UpdateAvailability::latestSeen());
    }

    public function test_the_toggle_is_offered_even_when_the_in_app_updater_is_off(): void
    {
        config(['updater.enabled' => false]);
        Http::fake();

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.updates'));

        $response->assertOk();
        $response->assertSee('Update checks');
        $response->assertSee(route('admin.updates.notifications.update'), false);
    }

    public function test_the_toggle_persists_both_ways(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.updates.notifications.update'), []);
        $this->assertFalse(UpdateAvailability::enabled());

        $this->actingAs($admin)->post(route('admin.updates.notifications.update'), ['check_enabled' => '1']);
        $this->assertTrue(UpdateAvailability::enabled());
    }
}
