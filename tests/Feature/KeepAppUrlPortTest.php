<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Behind a port mapping, such as Unraid's 10130 to the container's 80, the
 * app only learns the port from the Host header. Debian's nginx now passes
 * HTTP_HOST as $host, without the port, as a security workaround. Every link
 * built from the request then pointed at port 80: on Unraid that is Unraid's
 * own login page. The language menu on the home page and the units menu on
 * every page did that.
 *
 * Each request here arrives as it does behind that nginx: host without port,
 * on port 80.
 */
class KeepAppUrlPortTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $path, string $appUrl = 'http://192.168.1.15:10130'): string
    {
        config(['app.url' => $appUrl]);

        return (string) $this->get('http://192.168.1.15' . $path)->assertOk()->getContent();
    }

    public function test_the_home_page_language_menu_keeps_the_port(): void
    {
        $page = $this->visit('/');

        $this->assertStringContainsString('value="http://192.168.1.15:10130/?lang=nl-nl"', $page);
        $this->assertStringNotContainsString('value="http://192.168.1.15/?lang=', $page);
    }

    public function test_the_units_menu_keeps_the_port_on_every_page(): void
    {
        foreach (['/', '/forecast'] as $path) {
            $page = $this->visit($path);

            $this->assertMatchesRegularExpression('#value="http://192\.168\.1\.15:10130[^"]*units=imperial"#', $page, $path);
            $this->assertDoesNotMatchRegularExpression('#value="http://192\.168\.1\.15[/?][^"]*units=#', $page, $path);
        }
    }

    /** Choosing a language redirects; the redirect has to keep the port too. */
    public function test_the_language_redirect_keeps_the_port(): void
    {
        config(['app.url' => 'http://192.168.1.15:10130']);

        $location = $this->get('http://192.168.1.15/?lang=nl-nl')->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('http://192.168.1.15:10130/', $location);
    }

    public function test_a_page_that_is_not_found_keeps_the_port(): void
    {
        config(['app.url' => 'http://192.168.1.15:10130']);

        $page = (string) $this->get('http://192.168.1.15/no-such-page')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('href="http://192.168.1.15/', $page);
    }

    /** Only the configured host gets the port; a visit through another name is left alone. */
    public function test_another_host_name_is_left_alone(): void
    {
        config(['app.url' => 'http://192.168.1.15:10130']);

        $page = (string) $this->get('http://weather.example.org/')->assertOk()->getContent();

        $this->assertStringNotContainsString('weather.example.org:10130', $page);
    }

    public function test_an_app_url_without_a_port_changes_nothing(): void
    {
        $page = $this->visit('/', 'http://192.168.1.15');

        $this->assertStringContainsString('value="http://192.168.1.15/?lang=nl-nl"', $page);
    }

    public function test_a_request_that_already_has_the_port_is_unchanged(): void
    {
        config(['app.url' => 'http://192.168.1.15:10130']);

        $page = (string) $this->get('http://192.168.1.15:10130/')->assertOk()->getContent();

        $this->assertStringContainsString('value="http://192.168.1.15:10130/?lang=nl-nl"', $page);
    }
}
