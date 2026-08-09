<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_page_contains_the_quick_start_and_integration_examples(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.api-keys.index'));

        $response->assertOk();
        $response->assertSee('Using the WeatherNode API');
        $response->assertSee('X-API-Key: YOUR_API_KEY');
        $response->assertSee('https://weather.example.com/api');
        $response->assertSee('/api/weather/current');
        $response->assertSee('Home Assistant example');
        $response->assertSee('Node-RED and Telegraf');
        $response->assertSee(
            'https://github.com/'.config('updater.github_repo').'/blob/main/docs/API.md',
            false,
        );
    }

    public function test_api_markdown_documents_every_registered_api_route(): void
    {
        $documentation = file_get_contents(base_path('docs/API.md'));

        $this->assertIsString($documentation);

        $apiRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'));

        $this->assertNotEmpty($apiRoutes);

        foreach ($apiRoutes as $route) {
            $method = collect($route->methods())->first(fn (string $method): bool => $method !== 'HEAD');
            $documentedRoute = sprintf('| %s | `/%s` |', $method, $route->uri());

            $this->assertStringContainsString(
                $documentedRoute,
                $documentation,
                sprintf('%s /%s is missing from docs/API.md', $method, $route->uri()),
            );
        }
    }

    public function test_api_documentation_uses_plain_punctuation(): void
    {
        $page = file_get_contents(resource_path('views/admin/api-keys/index.blade.php'));
        $documentation = file_get_contents(base_path('docs/API.md'));

        $this->assertIsString($page);
        $this->assertIsString($documentation);
        $this->assertStringNotContainsString('—', $page);
        $this->assertStringNotContainsString('—', $documentation);
        $this->assertStringNotContainsString('meteouitgeest.nl', strtolower($page.$documentation));
        $this->assertStringNotContainsString("url('/api", $page);
    }
}
