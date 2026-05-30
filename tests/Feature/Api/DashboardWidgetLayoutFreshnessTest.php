<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardWidgetLayoutFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_latest_widget_layout_even_when_payload_cache_is_stale(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        Cache::put('dashboard_payload_en-us_us', [
            'success' => true,
            'grid_cols' => 1,
            'widget_order' => [
                'sortable-left-column' => ['stale-widget'],
            ],
        ], 120);

        $latestLayout = [
            'grid_cols' => 4,
            'widget_order' => [
                'sortable-left-column' => ['current', 'pressure'],
                'sortable-middle-column' => ['forecast'],
                'sortable-right-column' => ['airquality'],
                'sortable-media-row' => ['webcam', 'radar'],
                'sortable-widgets' => ['metar', 'alerts'],
            ],
        ];
        Setting::setValue('widgets.layout', $latestLayout, 'json', 'widgets');

        $response = $this->getJson('/api/weather/dashboard?lang=en-us');

        $response->assertOk();
        $response->assertJsonPath('grid_cols', 4);
        $response->assertJsonPath('widget_order.sortable-left-column.0', 'current');
        $response->assertJsonPath('widget_order.sortable-widgets.1', 'alerts');
    }
}

