<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_toggle_footer_coordinates_visibility(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'footer'), [
                'footer_enabled' => '1',
                'footer_show_station_info' => '1',
                'footer_show_coordinates' => '0',
                'footer_show_social' => '1',
                'footer_show_quick_links' => '1',
                'footer_show_legal' => '1',
                'footer_custom_links' => '[]',
            ]);

        $response->assertRedirect(route('admin.settings.group', 'footer'));
        $response->assertSessionHas('success');
        $this->assertFalse((bool) Setting::getValue('footer.show_coordinates', true));
    }

    public function test_footer_coordinates_are_hidden_when_disabled_and_visible_when_enabled(): void
    {
        Setting::setValue('footer.enabled', '1', 'boolean', 'footer');
        Setting::setValue('footer.show_station_info', '1', 'boolean', 'footer');
        Setting::setValue('footer.show_coordinates', '0', 'boolean', 'footer');
        Setting::setValue('station.latitude', '52.1234567', 'float', 'station');
        Setting::setValue('station.longitude', '4.9876543', 'float', 'station');

        $hiddenHtml = view('weather.partials.footer')->render();
        $this->assertStringNotContainsString('Coordinates', $hiddenHtml);
        $this->assertStringNotContainsString('52.123457, 4.987654', $hiddenHtml);

        Setting::setValue('footer.show_coordinates', '1', 'boolean', 'footer');
        $visibleHtml = view('weather.partials.footer')->render();
        $this->assertStringContainsString('Coordinates', $visibleHtml);
        $this->assertStringContainsString('52.123457, 4.987654', $visibleHtml);
    }
}

