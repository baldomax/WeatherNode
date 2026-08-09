<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard page and its payload are sent with a short browser cache so
 * repeat visitors and wall displays do not re-render everything. That window
 * must not apply to admins: they change settings and then navigate straight
 * back to the dashboard, where a cached copy hides the change they just made
 * until the window expires or they force a hard refresh.
 */
class DashboardCacheHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_dashboard_page_is_not_browser_cached_for_admins(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('home'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('max-age=30', (string) $response->headers->get('Cache-Control'));
    }

    public function test_dashboard_page_keeps_the_short_cache_for_visitors(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=30', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=30', $cacheControl);
    }

    public function test_dashboard_page_is_not_browser_cached_for_non_admin_users(): void
    {
        // A logged-in non-admin cannot change settings, so the cache still applies.
        $response = $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('home'));

        $response->assertOk();
        $this->assertStringContainsString('max-age=30', (string) $response->headers->get('Cache-Control'));
    }

    public function test_dashboard_payload_is_not_browser_cached_for_admins(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        $response = $this->actingAs($this->adminUser())->getJson('/api/weather/dashboard');

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_dashboard_payload_keeps_the_short_cache_for_visitors(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        $response = $this->getJson('/api/weather/dashboard');

        $response->assertOk();
        $this->assertStringContainsString('max-age=30', (string) $response->headers->get('Cache-Control'));
    }
}
