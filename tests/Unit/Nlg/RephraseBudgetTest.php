<?php

namespace Tests\Unit\Nlg;

use App\Services\Nlg\RephraseBudget;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RephraseBudgetTest extends TestCase
{
    private CarbonImmutable $clock;

    /** @var array<int, int> */
    private array $sleeps = [];

    private function makeBudget(array $limits): RephraseBudget
    {
        $this->clock = CarbonImmutable::parse('2026-05-29 12:00:10');
        $this->sleeps = [];

        return new RephraseBudget(
            cache: new Repository(new ArrayStore()),
            limitsResolver: fn (string $provider): array => $limits,
            clock: fn (): CarbonImmutable => $this->clock,
            sleeper: function (int $seconds): void {
                $this->sleeps[] = $seconds;
                $this->clock = $this->clock->addSeconds($seconds);
            },
        );
    }

    public function test_minute_saturation_paces_then_allows_instead_of_skipping(): void
    {
        $budget = $this->makeBudget(['rpm' => 2, 'rph' => null, 'rpd' => null]);

        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertSame([], $this->sleeps, 'should not sleep before the minute is full');

        // Third call in the same minute is over the per-minute cap: it must wait for
        // the next minute boundary and then proceed, never return false.
        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertSame([50], $this->sleeps, 'should sleep to the next minute boundary (60 - 10s)');
    }

    public function test_hour_saturation_skips_without_sleeping(): void
    {
        // High rpm so the minute window never trips; low rph.
        $budget = $this->makeBudget(['rpm' => 100, 'rph' => 3, 'rpd' => null]);

        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertTrue($budget->tryReserve('cerebras'));

        // Fourth within the hour exceeds rph → skip (false), and never sleep.
        $this->assertFalse($budget->tryReserve('cerebras'));
        $this->assertSame([], $this->sleeps, 'hour exhaustion must skip, not sleep');
    }

    public function test_day_saturation_skips_without_sleeping(): void
    {
        $budget = $this->makeBudget(['rpm' => 100, 'rph' => 100, 'rpd' => 2]);

        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertTrue($budget->tryReserve('cerebras'));

        $this->assertFalse($budget->tryReserve('cerebras'));
        $this->assertSame([], $this->sleeps, 'day exhaustion must skip, not sleep');
    }

    public function test_null_limits_mean_unlimited(): void
    {
        $budget = $this->makeBudget(['rpm' => null, 'rph' => null, 'rpd' => null]);

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($budget->tryReserve('cerebras'));
        }
        $this->assertSame([], $this->sleeps);
    }

    public function test_counters_are_isolated_per_provider(): void
    {
        $budget = $this->makeBudget(['rpm' => 100, 'rph' => 100, 'rpd' => 1]);

        // Each provider gets its own daily bucket.
        $this->assertTrue($budget->tryReserve('cerebras'));
        $this->assertTrue($budget->tryReserve('groq'));

        // Second call for each provider exceeds its own rpd of 1.
        $this->assertFalse($budget->tryReserve('cerebras'));
        $this->assertFalse($budget->tryReserve('groq'));
    }

    public function test_fails_open_when_cache_throws(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->andThrow(new RuntimeException('cache down'));

        $budget = new RephraseBudget(
            cache: $cache,
            limitsResolver: fn (string $provider): array => ['rpm' => 1, 'rph' => 1, 'rpd' => 1],
            clock: fn (): CarbonImmutable => CarbonImmutable::parse('2026-05-29 12:00:10'),
            sleeper: function (int $seconds): void {},
        );

        // A cache hiccup must not block weather-text generation.
        $this->assertTrue($budget->tryReserve('cerebras'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
