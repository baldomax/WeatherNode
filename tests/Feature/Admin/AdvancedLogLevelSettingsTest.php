<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedLogLevelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_save_advanced_log_level(): void
    {
        Setting::updateOrCreate(['key' => 'advanced.log_level'], [
            'key' => 'advanced.log_level',
            'value' => 'info',
            'type' => 'select',
            'group' => 'advanced',
            'description' => 'Application log verbosity threshold',
            'options' => 'debug:Debug,info:Info,notice:Notice,warning:Warning,error:Error,critical:Critical,alert:Alert,emergency:Emergency',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'advanced'), [
                'advanced_log_level' => 'error',
            ]);

        $response->assertRedirect(route('admin.settings.group', 'advanced'));
        $response->assertSessionHas('success');
        $this->assertSame('error', Setting::getValue('advanced.log_level'));
    }

    public function test_legacy_debug_mode_is_absent_and_ignored_when_posted(): void
    {
        Setting::updateOrCreate(['key' => 'advanced.log_level'], [
            'key' => 'advanced.log_level',
            'value' => 'info',
            'type' => 'select',
            'group' => 'advanced',
            'description' => 'Application log verbosity threshold',
            'options' => 'debug:Debug,info:Info,notice:Notice,warning:Warning,error:Error,critical:Critical,alert:Alert,emergency:Emergency',
        ]);

        $this->assertDatabaseMissing('settings', ['key' => 'advanced.debug_mode']);

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'advanced'), [
                'advanced_log_level' => 'warning',
                'advanced_debug_mode' => '1',
            ]);

        $response->assertRedirect(route('admin.settings.group', 'advanced'));
        $this->assertSame('warning', Setting::getValue('advanced.log_level'));
        $this->assertDatabaseMissing('settings', ['key' => 'advanced.debug_mode']);
    }

    public function test_non_admin_cannot_update_advanced_log_level(): void
    {
        Setting::updateOrCreate(['key' => 'advanced.log_level'], [
            'key' => 'advanced.log_level',
            'value' => 'info',
            'type' => 'select',
            'group' => 'advanced',
            'description' => 'Application log verbosity threshold',
            'options' => 'debug:Debug,info:Info,notice:Notice,warning:Warning,error:Error,critical:Critical,alert:Alert,emergency:Emergency',
        ]);

        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->post(route('admin.settings.update', 'advanced'), [
                'advanced_log_level' => 'critical',
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('info', Setting::getValue('advanced.log_level'));
    }
}
