<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CacheFreshness;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheFreshnessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_put_stores_the_payload_and_its_write_time(): void
    {
        Carbon::setTestNow('2026-08-18 09:00:00');

        CacheFreshness::put('demo_key', ['a' => 1], now()->addMinutes(120));

        $this->assertSame(['a' => 1], Cache::get('demo_key'));
        $this->assertTrue(CacheFreshness::updatedAt('demo_key')->equalTo(now()));
    }

    public function test_updated_at_is_null_when_nothing_was_written(): void
    {
        $this->assertNull(CacheFreshness::updatedAt('demo_key'));
    }

    /** The stamp must survive whatever the configured cache store does to it. */
    public function test_updated_at_reads_back_a_string_or_a_carbon(): void
    {
        Cache::put('a_updated_at', '2026-08-18T09:00:00+00:00', 600);
        Cache::put('b_updated_at', Carbon::parse('2026-08-18 09:00:00', 'UTC'), 600);

        $this->assertSame('2026-08-18 09:00:00', CacheFreshness::updatedAt('a')->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-18 09:00:00', CacheFreshness::updatedAt('b')->utc()->format('Y-m-d H:i:s'));
    }

    public function test_remember_stamps_the_entry_when_it_populates_the_cache(): void
    {
        Carbon::setTestNow('2026-08-18 09:00:00');

        $value = CacheFreshness::remember('demo_key', 600, fn () => ['fresh' => true]);

        $this->assertSame(['fresh' => true], $value);
        $this->assertTrue(CacheFreshness::updatedAt('demo_key')->equalTo(now()));
    }

    public function test_forget_removes_the_stamp_too(): void
    {
        CacheFreshness::put('demo_key', 'v', 600);
        CacheFreshness::forget('demo_key');

        $this->assertNull(Cache::get('demo_key'));
        $this->assertNull(CacheFreshness::updatedAt('demo_key'));
    }
}
