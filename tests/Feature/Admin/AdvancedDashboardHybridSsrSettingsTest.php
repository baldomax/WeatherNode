<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedDashboardHybridSsrSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_advanced_settings_page_includes_hybrid_dashboard_toggle(): void
    {
        Setting::updateOrCreate(['key' => 'dashboard.hybrid_ssr_enabled'], [
            'key' => 'dashboard.hybrid_ssr_enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'advanced',
            'description' => 'Enable hybrid SSR for dashboard first render (server HTML + JS hydration)',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->get(route('admin.settings.group', 'advanced'));

        $response->assertOk();
        $response->assertSee('dashboard_hybrid_ssr_enabled', false);
    }

    public function test_admin_can_toggle_hybrid_dashboard_ssr_setting(): void
    {
        Setting::updateOrCreate(['key' => 'dashboard.hybrid_ssr_enabled'], [
            'key' => 'dashboard.hybrid_ssr_enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'advanced',
            'description' => 'Enable hybrid SSR for dashboard first render (server HTML + JS hydration)',
        ]);

        $enableResponse = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'advanced'), [
                'dashboard_hybrid_ssr_enabled' => '1',
            ]);

        $enableResponse->assertRedirect(route('admin.settings.group', 'advanced'));
        $enableResponse->assertSessionHas('success');
        $this->assertTrue((bool) Setting::getValue('dashboard.hybrid_ssr_enabled'));

        $disableResponse = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'advanced'), [
                'dashboard_hybrid_ssr_enabled' => '0',
            ]);

        $disableResponse->assertRedirect(route('admin.settings.group', 'advanced'));
        $this->assertFalse((bool) Setting::getValue('dashboard.hybrid_ssr_enabled'));
    }
}
