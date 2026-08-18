<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The charts were hotlinked straight from NOAA and DWD. DWD's European surface
 * analysis is a 4.6MB scan at 4389x3114, roughly thirty times the NOAA images,
 * and every visitor fetched it from DWD directly.
 */
class PressureMapImageProxyTest extends TestCase
{
    use RefreshDatabase;

    /** A real 40x30 PNG, big enough to prove downscaling happened. */
    private function wideImage(): string
    {
        $im = imagecreatetruecolor(2400, 1600);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 20, 30));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_serves_a_known_map(): void
    {
        Http::fake(['*' => Http::response($this->wideImage(), 200, ['Content-Type' => 'image/png'])]);

        $response = $this->get('/pressure-map/image/europe');

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_it_downscales_an_oversized_chart(): void
    {
        Http::fake(['*' => Http::response($this->wideImage(), 200, ['Content-Type' => 'image/png'])]);

        $bytes = $this->get('/pressure-map/image/europe')->getContent();
        $size = getimagesizefromstring($bytes);

        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(1600, $size[0], 'The chart should be scaled down before it reaches the browser.');
        $this->assertLessThan(strlen($this->wideImage()), strlen($bytes));
    }

    public function test_an_unknown_map_is_refused(): void
    {
        Http::fake();

        $this->get('/pressure-map/image/not-a-map')->assertStatus(404);
        Http::assertNothingSent();
    }

    /** Keyed by map name, so it cannot be pointed at an arbitrary host. */
    public function test_it_takes_no_url_from_the_caller(): void
    {
        Http::fake(['*' => Http::response($this->wideImage(), 200, ['Content-Type' => 'image/png'])]);

        $this->get('/pressure-map/image/europe?url=' . urlencode('https://example.test/evil.png'))->assertOk();

        Http::assertSent(fn ($request) => !str_contains($request->url(), 'example.test'));
    }

    public function test_a_second_request_is_served_from_disk(): void
    {
        Http::fake(['*' => Http::response($this->wideImage(), 200, ['Content-Type' => 'image/png'])]);

        $this->get('/pressure-map/image/europe')->assertOk();
        $this->get('/pressure-map/image/europe')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_an_upstream_failure_does_not_500(): void
    {
        Http::fake(['*' => Http::response('nope', 503)]);

        $this->get('/pressure-map/image/atlantic')->assertStatus(502);
    }
}
