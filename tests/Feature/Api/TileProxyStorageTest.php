<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Radar imagery must never go through the cache store.
 *
 * The cache table's value column is mediumtext/utf8mb4, so PNG bytes are
 * rejected on MySQL in strict mode and silently truncated without it. Tiles
 * are kept on the local disk instead, which is where blobs belong.
 */
class TileProxyStorageTest extends TestCase
{
    use RefreshDatabase;

    private const TILE_PATH = 'v2/radar/1737561600/256/5/16/10/1/1_0.png';

    /** A real 1x1 PNG: starts \x89PNG, which is not valid UTF-8. */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        // phpunit.xml runs on the array store, where anything at all can be
        // cached and these assertions would pass no matter what the controller
        // does. The bug only exists on a real backing store, so use the one
        // Laravel actually defaults to.
        config(['cache.default' => 'database']);
        Cache::store('database')->clear();
    }

    /**
     * Cache keys the controller used to write image bytes under.
     *
     * Asserted by key rather than by inspecting stored bytes: whether a binary
     * value survives a text column is engine-specific (SQLite keeps it, MySQL
     * rejects or truncates it), so checking the value would make this test pass
     * or fail for reasons unrelated to the controller.
     */
    private function imageCacheKeys(): array
    {
        return DB::table('cache')
            ->where(function ($q) {
                $q->where('key', 'like', '%radar_tile_%')
                    ->orWhere('key', 'like', '%radar_future_image_%');
            })
            ->pluck('key')
            ->all();
    }

    public function test_a_png_is_not_valid_utf8(): void
    {
        // The premise the rest of this class rests on.
        $this->assertFalse(mb_check_encoding($this->pngBytes(), 'UTF-8'));
    }

    public function test_fetching_a_tile_writes_no_binary_into_the_cache_store(): void
    {
        $png = $this->pngBytes();
        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $response = $this->get('/api/radar/tile/' . self::TILE_PATH);

        $response->assertOk();
        $this->assertSame($png, $response->getContent());

        $this->assertSame([], $this->imageCacheKeys(), 'tile bytes were written into the cache store');
    }

    public function test_the_tile_is_stored_on_disk(): void
    {
        Http::fake(['*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        $this->get('/api/radar/tile/' . self::TILE_PATH)->assertOk();

        Storage::disk('local')->assertExists('radar-tiles/' . self::TILE_PATH);
        $this->assertSame($this->pngBytes(), Storage::disk('local')->get('radar-tiles/' . self::TILE_PATH));
    }

    public function test_a_second_request_is_served_from_disk_without_refetching(): void
    {
        $png = $this->pngBytes();
        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $this->get('/api/radar/tile/' . self::TILE_PATH)->assertOk();
        $upstreamCallsAfterFirst = count(Http::recorded());

        $second = $this->get('/api/radar/tile/' . self::TILE_PATH);

        $second->assertOk();
        $this->assertSame($png, $second->getContent());
        $this->assertCount($upstreamCallsAfterFirst, Http::recorded(), 'the disk copy should have served this');
    }

    /**
     * The path that used to throw: it sits outside the controller's try/catch,
     * so on strict MySQL it surfaced as a 500 for every tile once the file
     * existed.
     */
    public function test_a_disk_hit_does_not_touch_the_cache_store(): void
    {
        Storage::disk('local')->put('radar-tiles/' . self::TILE_PATH, $this->pngBytes());
        Http::preventStrayRequests();

        $response = $this->get('/api/radar/tile/' . self::TILE_PATH);

        $response->assertOk();
        $this->assertSame([], $this->imageCacheKeys(), 'serving from disk must not write tile bytes to the cache');
    }

    public function test_future_images_are_stored_on_disk_too(): void
    {
        $png = $this->pngBytes();
        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $url = 'https://digital.weather.gov/some/frame.png';
        $response = $this->get('/api/radar/future-image?url=' . urlencode($url));

        $response->assertOk();
        $this->assertSame([], $this->imageCacheKeys(), 'future image bytes were written into the cache store');
        Storage::disk('local')->assertExists('radar-tiles/future/' . md5($url) . '.img');
    }

    public function test_future_images_are_served_from_disk_on_a_repeat_request(): void
    {
        $png = $this->pngBytes();
        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
        $url = 'https://digital.weather.gov/some/frame.png';

        $this->get('/api/radar/future-image?url=' . urlencode($url))->assertOk();
        $callsAfterFirst = count(Http::recorded());

        $second = $this->get('/api/radar/future-image?url=' . urlencode($url));

        $second->assertOk();
        $this->assertCount($callsAfterFirst, Http::recorded(), 'the disk copy should have served this');
    }
}
