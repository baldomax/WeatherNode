<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\StatTileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatTileSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_widgets_page_lists_every_stat_tile(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.widgets'));

        $response->assertOk();
        $response->assertSee('Quick Stats Bar');
        foreach (StatTileRegistry::ids() as $tileId) {
            $response->assertSee('data-stat-tile="' . $tileId . '"', false);
        }
    }

    public function test_widgets_page_warns_when_a_tile_is_gated_off_by_navigation(): void
    {
        Setting::setValue('navigation.earthquakes_enabled', false, 'boolean', 'navigation');

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.widgets'));

        $response->assertOk();
        $response->assertSee('Hidden while Earthquakes is disabled in Navigation settings.');
    }

    public function test_saving_persists_the_enabled_tiles(): void
    {
        $response = $this->actingAs($this->adminUser())->post(route('admin.settings.widgets.update'), [
            'enabled_widgets' => ['current'],
            'grid_cols' => 3,
            'stat_tiles_submitted' => '1',
            'enabled_stat_tiles' => ['uv', 'today'],
        ]);

        $response->assertRedirect(route('admin.settings.widgets'));
        $this->assertSame(['today', 'uv'], Setting::getValue(StatTileRegistry::SETTING_ENABLED));
    }

    public function test_saving_with_no_tiles_selected_disables_them_all(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today', 'uv'], 'json', 'widgets');

        $this->actingAs($this->adminUser())->post(route('admin.settings.widgets.update'), [
            'enabled_widgets' => ['current'],
            'grid_cols' => 3,
            'stat_tiles_submitted' => '1',
        ]);

        $this->assertSame([], Setting::getValue(StatTileRegistry::SETTING_ENABLED));
    }

    public function test_a_form_without_the_stats_section_leaves_the_tiles_alone(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today'], 'json', 'widgets');

        $this->actingAs($this->adminUser())->post(route('admin.settings.widgets.update'), [
            'enabled_widgets' => ['current'],
            'grid_cols' => 3,
        ]);

        $this->assertSame(['today'], Setting::getValue(StatTileRegistry::SETTING_ENABLED));
    }

    public function test_unknown_tile_ids_are_not_persisted(): void
    {
        $this->actingAs($this->adminUser())->post(route('admin.settings.widgets.update'), [
            'enabled_widgets' => ['current'],
            'grid_cols' => 3,
            'stat_tiles_submitted' => '1',
            'enabled_stat_tiles' => ['today', 'definitely_not_a_tile'],
        ]);

        $this->assertSame(['today'], Setting::getValue(StatTileRegistry::SETTING_ENABLED));
    }

    public function test_a_disabled_tile_does_not_show_on_the_dashboard(): void
    {
        $this->actingAs($this->adminUser())->post(route('admin.settings.widgets.update'), [
            'enabled_widgets' => ['current'],
            'grid_cols' => 3,
            'stat_tiles_submitted' => '1',
            'enabled_stat_tiles' => ['today'],
        ]);

        $content = $this->get(route('home'))->getContent();

        $this->assertMatchesRegularExpression('/data-stat="aqi"[^>]*style="display: none"/', $content);
        $this->assertDoesNotMatchRegularExpression('/data-stat="today"[^>]*style="display: none"/', $content);
    }
}
