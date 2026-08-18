<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The validator required the RainViewer frame id to be digits, which matched
 * their old timestamp scheme. They now issue hex ids, so every tile the app
 * asked for was rejected by the app itself: /api/radar/frames hands the
 * frontend these paths and /api/radar/tile then refuses them.
 */
class RadarTilePathValidationTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(ApiKeyMiddleware::class);
    }

    /** Http::fake() merges stubs, so a success stub must not be registered globally. */
    private function fakeUpstreamServesTile(): void
    {
        Http::fake(['tilecache.rainviewer.com/*' => Http::response(base64_decode(self::PNG_BASE64), 200, ['Content-Type' => 'image/png'])]);
    }

    private function tile(string $path)
    {
        return $this->get('/api/radar/tile/' . $path);
    }

    public function test_a_current_hex_frame_id_is_accepted(): void
    {
        $this->fakeUpstreamServesTile();
        $response = $this->tile('v2/radar/c912c99f5a7d/512/7/73/38/1/1_0.png');

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_a_legacy_numeric_frame_id_still_works(): void
    {
        $this->fakeUpstreamServesTile();
        $this->tile('v2/radar/1737561600/256/5/16/10/1/1_0.png')->assertOk();
    }

    public function test_path_traversal_is_still_refused(): void
    {
        $this->tile('v2/radar/../../../etc/passwd.png')->assertStatus(400);
        $this->tile('v2/radar/abc/512/7/73/38/1/../../secret.png')->assertStatus(400);
    }

    public function test_a_non_radar_upstream_path_is_still_refused(): void
    {
        $this->tile('v2/satellite/c912c99f5a7d/512/7/73/38/1/1_0.png')->assertStatus(400);
        $this->tile('v2/radar/c912c99f5a7d/512/7/73/38/1/1_0.jpg')->assertStatus(400);
    }

    public function test_nothing_outside_the_frame_id_alphabet_is_accepted(): void
    {
        $this->tile('v2/radar/abc%2F..%2F/512/7/73/38/1/1_0.png')->assertStatus(400);
        $this->tile('v2/radar/ab.cd/512/7/73/38/1/1_0.png')->assertStatus(400);
    }

    /** A blank radar with a silent 400 gave nothing to diagnose from. */
    public function test_a_rejected_path_is_logged(): void
    {
        Log::spy();

        $this->tile('v2/radar/ab.cd/512/7/73/38/1/1_0.png')->assertStatus(400);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => str_contains((string) $message, 'tile path')
                && ($context['path'] ?? '') === 'v2/radar/ab.cd/512/7/73/38/1/1_0.png');
    }

    /**
     * When upstream fails, the proxy serves the last good tile for the same map
     * cell. That lookup carried the same numeric-id assumption, so with hex
     * frames it bailed out and users got a transparent pixel instead.
     */
    public function test_a_failed_upstream_falls_back_to_the_previous_hex_frame(): void
    {
        \Illuminate\Support\Facades\Cache::put('rainviewer_frames', [
            'version' => '2.0',
            'generated' => time(),
            'host' => 'https://tilecache.rainviewer.com',
            'radar' => ['past' => [
                ['time' => time() - 600, 'path' => '/v2/radar/aaaa11112222'],
                ['time' => time(), 'path' => '/v2/radar/bbbb33334444'],
            ]],
        ], 1800);

        $cell = '/512/7/73/38/1/1_0.png';
        Storage::disk('local')->put('radar-tiles/v2/radar/aaaa11112222' . $cell, base64_decode(self::PNG_BASE64));

        Http::fake(['tilecache.rainviewer.com/*' => Http::response('nope', 500)]);

        $response = $this->tile('v2/radar/bbbb33334444' . $cell);

        $response->assertOk();
        $this->assertSame(base64_decode(self::PNG_BASE64), $response->getContent());
    }

    public function test_the_upstream_url_is_built_from_the_validated_path(): void
    {
        $this->fakeUpstreamServesTile();
        $this->tile('v2/radar/c912c99f5a7d/512/7/73/38/1/1_0.png')->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/radar/c912c99f5a7d/512/7/73/38/1/1_0.png'));
    }
}
