<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The app shipped no robots.txt, so every install had to write its own. */
class RobotsTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_served_as_plain_text(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_it_points_at_this_installs_own_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Sitemap: '.rtrim(url('/'), '/').'/sitemap.xml');
    }

    public function test_it_keeps_crawlers_out_of_the_admin_and_the_api(): void
    {
        $response = $this->get('/robots.txt');

        foreach (['Disallow: /admin', 'Disallow: /api/', 'Disallow: /*?units='] as $rule) {
            $response->assertSee($rule);
        }
    }

    /** The pages people actually search for have to stay crawlable. */
    public function test_it_does_not_block_the_weather_pages(): void
    {
        $body = $this->get('/robots.txt')->getContent();

        foreach (["\nDisallow: /history/", "\nDisallow: /forecast", "\nDisallow: /\n"] as $rule) {
            $this->assertStringNotContainsString($rule, $body);
        }
    }

    public function test_it_explains_itself_so_it_can_be_edited(): void
    {
        $body = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString('public/robots.txt', $body);
        $this->assertGreaterThan(10, substr_count($body, "\n#"), 'expected comments explaining each rule');
    }
}
