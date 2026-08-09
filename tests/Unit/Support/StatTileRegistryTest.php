<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Support\StatTileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatTileRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_tiles_are_enabled_when_the_setting_is_missing(): void
    {
        Setting::query()->where('key', StatTileRegistry::SETTING_ENABLED)->delete();

        $this->assertSame(StatTileRegistry::ids(), StatTileRegistry::enabledIds());
    }

    public function test_enabled_ids_respect_the_stored_list_and_drop_unknown_ids(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['uv', 'today', 'not_a_tile'], 'json', 'widgets');

        // Registry order, not stored order — order is the drag setting's job.
        $this->assertSame(['today', 'uv'], StatTileRegistry::enabledIds());
    }

    public function test_no_tiles_are_enabled_when_the_stored_list_is_empty(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, [], 'json', 'widgets');

        $this->assertSame([], StatTileRegistry::enabledIds());
    }

    public function test_renderable_ids_follow_the_saved_drag_order(): void
    {
        Setting::setValue('widgets.layout', ['stat_order' => ['uv', 'today']], 'json', 'widgets');

        $renderable = StatTileRegistry::renderableIds();

        $this->assertSame('uv', $renderable[0]);
        $this->assertSame('today', $renderable[1]);
    }

    public function test_renderable_ids_append_tiles_the_saved_order_does_not_mention(): void
    {
        Setting::setValue('widgets.layout', ['stat_order' => ['best_time']], 'json', 'widgets');

        $renderable = StatTileRegistry::renderableIds();

        $this->assertSame('best_time', $renderable[0]);
        $this->assertEqualsCanonicalizing(StatTileRegistry::ids(), $renderable);
    }

    public function test_renderable_ids_include_disabled_tiles_so_they_can_be_shown_live(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today'], 'json', 'widgets');

        $this->assertContains('uv', StatTileRegistry::renderableIds());
    }

    public function test_renderable_ids_omit_tiles_whose_navigation_feature_is_off(): void
    {
        Setting::setValue('navigation.earthquakes_enabled', false, 'boolean', 'navigation');
        Setting::setValue('navigation.air_pollen_enabled', false, 'boolean', 'navigation');

        $renderable = StatTileRegistry::renderableIds();

        $this->assertNotContains('earthquakes', $renderable);
        $this->assertNotContains('aqi', $renderable);
        $this->assertContains('today', $renderable);
    }

    public function test_enabled_ids_ignore_the_navigation_feature_so_admin_shows_stored_state(): void
    {
        Setting::setValue(StatTileRegistry::SETTING_ENABLED, ['today', 'earthquakes'], 'json', 'widgets');
        Setting::setValue('navigation.earthquakes_enabled', false, 'boolean', 'navigation');

        $this->assertContains('earthquakes', StatTileRegistry::enabledIds());
    }

    public function test_sanitize_order_drops_unknown_ids_and_duplicates(): void
    {
        $this->assertSame(
            ['today', 'uv'],
            StatTileRegistry::sanitizeOrder(['today', 'uv', 'today', 'bogus', ['nested']])
        );
    }

    public function test_stored_order_is_empty_when_the_layout_has_none(): void
    {
        Setting::setValue('widgets.layout', ['grid_cols' => 3], 'json', 'widgets');

        $this->assertSame([], StatTileRegistry::storedOrder());
    }
}
