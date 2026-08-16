<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorHealthSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_notifications_page_exposes_sensor_health_controls(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->get(route('admin.settings.notifications'));

        $response->assertOk();
        $response->assertSee('sensor_health_enabled', false);
        $response->assertSee('sensor_health_fail_minutes', false);
        $response->assertSee('sensor_health_track_days', false);
    }

    public function test_saves_sensor_health_thresholds(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.notifications.update'), [
                'notifications_enabled' => '1',
                'method' => 'email',
                'email' => 'ops@example.com',
                'sensor_health_enabled' => '1',
                'sensor_health_fail_minutes' => '45',
                'sensor_health_track_days' => '10',
            ])
            ->assertRedirect(route('admin.settings.notifications'));

        $this->assertSame('45', (string) Setting::getValue('sensor_health.fail_minutes'));
        $this->assertSame('10', (string) Setting::getValue('sensor_health.track_days'));
    }

    public function test_clamps_out_of_range_thresholds(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.notifications.update'), [
                'notifications_enabled' => '1',
                'method' => 'email',
                'email' => 'ops@example.com',
                'sensor_health_enabled' => '1',
                'sensor_health_fail_minutes' => '2',
                'sensor_health_track_days' => '999',
            ]);

        $this->assertSame(15, (int) Setting::getValue('sensor_health.fail_minutes'));
        $this->assertSame(30, (int) Setting::getValue('sensor_health.track_days'));
    }
}
