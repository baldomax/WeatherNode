<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidgetOrderPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_saving_widget_order_clears_dashboard_payload_cache_and_persists_layout(): void
    {
        Setting::setValue('widgets.layout', ['grid_cols' => 3], 'json', 'widgets');

        $payloadOrder = [
            'sortable-left-column' => ['current', 'pressure'],
            'sortable-middle-column' => ['forecast'],
            'sortable-right-column' => ['airquality'],
            'sortable-media-row' => ['webcam', 'radar'],
            'sortable-widgets' => ['metar', 'alerts'],
        ];

        Cache::put('dashboard_payload_default_us', ['widget_order' => ['stale']], 120);
        Cache::put('dashboard_payload_custom-lang_us', ['widget_order' => ['stale']], 120);
        Cache::forever('dashboard_payload_keys', [
            'dashboard_payload_default_us',
            'dashboard_payload_custom-lang_us',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('widgets.order'), ['widget_order' => $payloadOrder]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $layout = Setting::getValue('widgets.layout', []);
        $this->assertSame($payloadOrder, $layout['widget_order'] ?? null);

        $this->assertNull(Cache::get('dashboard_payload_default_us'));
        $this->assertNull(Cache::get('dashboard_payload_custom-lang_us'));
        $this->assertNull(Cache::get('dashboard_payload_keys'));
    }

    public function test_stat_tile_order_persists_alongside_the_widget_order(): void
    {
        Setting::setValue('widgets.layout', ['grid_cols' => 3, 'widget_order' => ['keep' => ['current']]], 'json', 'widgets');

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('widgets.order'), ['stat_order' => ['uv', 'today']]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'stat_order' => ['uv', 'today']]);

        $layout = Setting::getValue('widgets.layout', []);
        $this->assertSame(['uv', 'today'], $layout['stat_order'] ?? null);
        // A stats-only save must not wipe the widget order.
        $this->assertSame(['keep' => ['current']], $layout['widget_order'] ?? null);
    }

    public function test_unknown_stat_tile_ids_are_dropped_before_saving(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson(route('widgets.order'), ['stat_order' => ['uv', 'made_up', 'uv']]);

        $response->assertOk();
        $this->assertSame(['uv'], Setting::getValue('widgets.layout', [])['stat_order'] ?? null);
    }

    public function test_a_request_with_neither_order_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson(route('widgets.order'), []);

        $response->assertOk();
        $response->assertJson(['success' => false]);
    }
}
