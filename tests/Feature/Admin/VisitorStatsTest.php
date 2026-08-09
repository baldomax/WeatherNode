<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\VisitorDailyStat;
use App\Support\VisitorStatsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisitorStatsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function log(string $when, bool $isBot = false, array $overrides = []): void
    {
        DB::table('visitor_logs')->insert(array_merge([
            'occurred_at' => $when,
            'path' => '/',
            'method' => 'GET',
            'status_code' => 200,
            'response_ms' => 100,
            'referrer_host' => null,
            'search_engine' => null,
            'search_terms' => null,
            'country_code' => 'NL',
            'device_type' => 'desktop',
            'browser_family' => 'Chrome',
            'os_family' => 'macOS',
            'is_bot' => $isBot,
            'ip_hash' => 'hash-' . uniqid('', true),
            'ip_encrypted' => 'x',
            'created_at' => $when,
            'updated_at' => $when,
        ], $overrides));
    }

    public function test_rollup_writes_one_row_per_segment(): void
    {
        $day = now()->subDay();
        $this->log($day->copy()->setTime(9, 0)->toDateTimeString());
        $this->log($day->copy()->setTime(10, 0)->toDateTimeString());
        $this->log($day->copy()->setTime(11, 0)->toDateTimeString(), isBot: true);

        $this->artisan('visitorlog:rollup')->assertExitCode(0);

        $all = VisitorDailyStat::where('segment', VisitorDailyStat::SEGMENT_ALL)->first();
        $humans = VisitorDailyStat::where('segment', VisitorDailyStat::SEGMENT_HUMANS)->first();

        $this->assertSame(3, $all->pageviews);
        $this->assertSame(2, $humans->pageviews, 'the bot must be excluded from the humans segment');
    }

    /**
     * The date cast stores "Y-m-d 00:00:00", so matching updateOrCreate on a
     * bare date string never found the existing row and the insert tripped the
     * unique index.
     */
    public function test_rolling_up_the_same_day_twice_updates_rather_than_failing(): void
    {
        $day = now()->subDay();
        $this->log($day->copy()->setTime(9, 0)->toDateTimeString());

        $this->artisan('visitorlog:rollup')->assertExitCode(0);
        $this->log($day->copy()->setTime(12, 0)->toDateTimeString());
        $this->artisan('visitorlog:rollup')->assertExitCode(0);

        $this->assertSame(1, VisitorDailyStat::where('segment', VisitorDailyStat::SEGMENT_ALL)->count());
        $this->assertSame(2, VisitorDailyStat::where('segment', VisitorDailyStat::SEGMENT_ALL)->first()->pageviews);
    }

    public function test_rollup_backfills_segments_missing_from_older_versions(): void
    {
        // An install upgraded from before segments existed: the migration
        // labels its rows 'all' and there are no 'humans' rows at all.
        $day = now()->subDays(3);
        $this->log($day->copy()->setTime(9, 0)->toDateTimeString());
        $this->log($day->copy()->setTime(10, 0)->toDateTimeString(), isBot: true);
        VisitorDailyStat::create([
            'date' => $day->copy()->startOfDay(),
            'segment' => VisitorDailyStat::SEGMENT_ALL,
            'pageviews' => 2,
            'uniques' => 2,
            'total_response_ms' => 200,
            'avg_response_ms' => 100,
        ]);

        $this->artisan('visitorlog:rollup')->assertExitCode(0);

        $humans = VisitorDailyStat::where('segment', VisitorDailyStat::SEGMENT_HUMANS)
            ->whereDate('date', $day->toDateString())
            ->first();

        $this->assertNotNull($humans, 'the missing humans row should have been backfilled');
        $this->assertSame(1, $humans->pageviews);
    }

    /**
     * A night the scheduler did not run leaves a day with raw logs and no
     * rollup row. The page used to scan raw logs so it still showed that day;
     * reading the rollup would drop it silently.
     */
    public function test_rollup_captures_days_it_previously_missed(): void
    {
        $missedDay = now()->subDays(4);
        $this->log($missedDay->copy()->setTime(9, 0)->toDateTimeString());
        $this->log($missedDay->copy()->setTime(10, 0)->toDateTimeString());

        // Only yesterday gets rolled up the normal way.
        $this->log(now()->subDay()->setTime(9, 0)->toDateTimeString());
        $this->artisan('visitorlog:rollup')->assertExitCode(0);
        VisitorStatsCache::flush();

        $response = $this->actingAs($this->adminUser())->get(route('admin.visitors.index', ['range' => 30]));

        $response->assertOk();
        $this->assertSame(
            3,
            $response->viewData('totals')['pageviews'],
            'the missed day must be picked up rather than disappearing from the page'
        );
    }

    public function test_backfill_does_not_repeat_once_segments_exist(): void
    {
        $day = now()->subDay();
        $this->log($day->copy()->setTime(9, 0)->toDateTimeString());

        $this->artisan('visitorlog:rollup')->assertExitCode(0);
        $this->artisan('visitorlog:rollup')
            ->doesntExpectOutputToContain('Backfilling')
            ->assertExitCode(0);
    }

    public function test_the_page_reads_the_rollup_instead_of_scanning_raw_logs(): void
    {
        for ($d = 10; $d >= 1; $d--) {
            $this->log(now()->subDays($d)->setTime(9, 0)->toDateTimeString());
        }
        $this->artisan('visitorlog:rollup', ['--days' => 10])->assertExitCode(0);
        VisitorStatsCache::flush();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->adminUser())->get(route('admin.visitors.index', ['range' => 30]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        $scans = array_filter(
            $queries,
            fn ($q) => str_contains($q['query'], 'visitor_logs') && str_contains($q['query'], 'group by')
        );
        $this->assertCount(0, $scans, 'historical days must come from the rollup, not a GROUP BY over raw logs');
    }

    public function test_today_is_included_even_though_the_rollup_has_not_run_for_it(): void
    {
        $this->log(now()->subDay()->setTime(9, 0)->toDateTimeString());
        $this->artisan('visitorlog:rollup')->assertExitCode(0);

        $this->log(now()->setTime(8, 0)->toDateTimeString(), overrides: ['path' => '/today-only']);
        VisitorStatsCache::flush();

        $response = $this->actingAs($this->adminUser())->get(route('admin.visitors.index'));

        $response->assertOk();
        $response->assertSee('/today-only', false);
    }

    public function test_the_bots_toggle_selects_the_matching_segment(): void
    {
        $day = now()->subDay();
        $this->log($day->copy()->setTime(9, 0)->toDateTimeString());
        $this->log($day->copy()->setTime(10, 0)->toDateTimeString(), isBot: true);
        $this->log($day->copy()->setTime(11, 0)->toDateTimeString(), isBot: true);
        $this->artisan('visitorlog:rollup')->assertExitCode(0);
        VisitorStatsCache::flush();

        $admin = $this->adminUser();

        $humans = $this->actingAs($admin)->get(route('admin.visitors.index'));
        $humans->assertOk();
        $this->assertSame(1, $humans->viewData('totals')['pageviews']);

        VisitorStatsCache::flush();
        $all = $this->actingAs($admin)->get(route('admin.visitors.index', ['show_bots' => '1']));
        $all->assertOk();
        $this->assertSame(3, $all->viewData('totals')['pageviews']);
    }

    public function test_the_rollup_invalidates_the_cached_payload(): void
    {
        $this->log(now()->subDay()->setTime(9, 0)->toDateTimeString());
        $this->artisan('visitorlog:rollup')->assertExitCode(0);

        $admin = $this->adminUser();
        $first = $this->actingAs($admin)->get(route('admin.visitors.index'));
        $this->assertSame(1, $first->viewData('totals')['pageviews']);

        // Same request again, served from cache and unchanged.
        $this->log(now()->subDay()->setTime(10, 0)->toDateTimeString());
        $cached = $this->actingAs($admin)->get(route('admin.visitors.index'));
        $this->assertSame(1, $cached->viewData('totals')['pageviews'], 'should still be the cached payload');

        $this->artisan('visitorlog:rollup')->assertExitCode(0);
        $fresh = $this->actingAs($admin)->get(route('admin.visitors.index'));
        $this->assertSame(2, $fresh->viewData('totals')['pageviews'], 'the rollup should have invalidated the cache');
    }

    /**
     * Raw logs are purged at retention_days while the range selector goes to
     * 365, so asking for a year used to silently return whatever existed with
     * nothing saying so.
     */
    public function test_a_range_longer_than_the_available_data_is_flagged(): void
    {
        $this->log(now()->subDays(3)->setTime(9, 0)->toDateTimeString());
        $this->artisan('visitorlog:rollup', ['--days' => 5])->assertExitCode(0);
        VisitorStatsCache::flush();

        $response = $this->actingAs($this->adminUser())->get(route('admin.visitors.index', ['range' => 365]));

        $response->assertOk();
        $this->assertLessThan(365, $response->viewData('availableDays'));
        $response->assertSee('days of data exist so far', false);
    }

    public function test_no_shortfall_notice_when_the_range_fits(): void
    {
        for ($d = 40; $d >= 1; $d -= 10) {
            $this->log(now()->subDays($d)->setTime(9, 0)->toDateTimeString());
        }
        $this->artisan('visitorlog:rollup')->assertExitCode(0);
        VisitorStatsCache::flush();

        $response = $this->actingAs($this->adminUser())->get(route('admin.visitors.index', ['range' => 30]));

        $response->assertOk();
        $response->assertDontSee('days of data exist so far', false);
    }

    public function test_the_page_still_renders_with_no_data_at_all(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.visitors.index'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('totals')['pageviews']);
        $this->assertSame([], $response->viewData('analyticsData')['dates']);
    }
}
