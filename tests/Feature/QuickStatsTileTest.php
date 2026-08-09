<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\StatTileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickStatsTileTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_tiles_render_by_default(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        foreach (StatTileRegistry::ids() as $tileId) {
            $response->assertSee('data-stat="' . $tileId . '"', false);
        }
    }

    public function test_disabled_tile_is_rendered_hidden_so_it_can_be_shown_live(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today', 'uv'], 'json', 'widgets');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-stat="aqi"', false);
        $response->assertSee('x-show="isStatTileEnabled(\'aqi\')"', false);
        $this->assertMatchesRegularExpression(
            '/data-stat="aqi"[^>]*style="display: none"/',
            $response->getContent()
        );
    }

    public function test_enabled_tile_is_not_hidden(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today', 'uv'], 'json', 'widgets');

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression(
            '/data-stat="today"[^>]*style="display: none"/',
            $response->getContent()
        );
    }

    /**
     * The bug in issue #13: earthquakes switched off in Navigation still showed
     * a permanent "✓" tile on the dashboard.
     */
    public function test_tile_is_absent_when_its_navigation_feature_is_disabled(): void
    {
        Setting::setValue('navigation.earthquakes_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.air_pollen_enabled', false, 'boolean', 'navigation');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('data-stat="earthquakes"', false);
        $response->assertDontSee('data-stat="aqi"', false);
        $response->assertSee('data-stat="today"', false);
    }

    public function test_tiles_render_in_the_saved_drag_order(): void
    {
        Setting::setValue('widgets.layout', ['stat_order' => ['uv', 'today']], 'json', 'widgets');

        $content = $this->get(route('home'))->getContent();

        $this->assertLessThan(
            strpos($content, 'data-stat="today"'),
            strpos($content, 'data-stat="uv"'),
            'The UV tile should precede the Today tile once the saved order says so.'
        );
    }

    public function test_dashboard_config_exposes_the_enabled_tiles(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today', 'uv'], 'json', 'widgets');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('"enabledStatTiles":["today","uv"]', false);
    }
}
