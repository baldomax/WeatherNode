<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisitorDailyStat;
use App\Support\VisitorStatsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitorLogController extends Controller
{
    /** Buckets stored as JSON on each rollup row, and how many to show. */
    private const BUCKETS = [
        'top_pages' => 10,
        'referrers' => 10,
        'countries' => 10,
        'devices' => 10,
        'browsers' => 10,
        'oses' => 10,
        'search_engines' => 10,
        'search_terms' => 10,
        'status_codes' => 10,
    ];

    /** Bucket name => [raw log column, label used when the column is empty]. */
    private const BUCKET_COLUMNS = [
        'top_pages' => ['path', null],
        'referrers' => ['referrer_host', 'Direct'],
        'countries' => ['country_code', 'Unknown'],
        'devices' => ['device_type', 'Unknown'],
        'browsers' => ['browser_family', 'Other'],
        'oses' => ['os_family', 'Other'],
        'search_engines' => ['search_engine', 'Unknown'],
        'search_terms' => ['search_terms', null],
        'status_codes' => ['status_code', null],
    ];

    public function index(Request $request)
    {
        $range = (int) $request->query('range', 30);
        $range = max(7, min($range, 365));
        $fromDate = now()->subDays($range - 1)->startOfDay();
        $showBots = $request->query('show_bots', '0') === '1';

        try {
            return $this->buildVisitorIndex($request, $range, $fromDate, $showBots);
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            return $this->visitorIndexErrorView($range, $showBots, 'database', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return $this->visitorIndexErrorView($range, $showBots, 'error', $e->getMessage());
        }
    }

    private function visitorIndexErrorView(int $range, bool $showBots, string $reason, ?string $detail = null): \Illuminate\View\View
    {
        $emptyData = array_merge(
            ['dates' => [], 'pageviews' => [], 'uniques' => []],
            array_fill_keys(array_keys(self::BUCKETS), [])
        );
        $totals = [
            'pageviews' => 0,
            'uniques' => 0,
            'avg_response_ms' => null,
            'days' => 0,
        ];

        $message = $reason === 'database'
            ? __('Visitor analytics tables may be missing. Run: php artisan migrate')
            : __('Visitor analytics are temporarily unavailable.');
        if ($detail) {
            $message .= ' ' . __('Detail') . ': ' . $detail;
        }

        return view('admin.visitors.index', [
            'range' => $range,
            'showBots' => $showBots,
            'totals' => $totals,
            'analyticsData' => $emptyData,
            'lastRollupDate' => null,
            'availableDays' => 0,
            'error' => $message,
        ]);
    }

    private function buildVisitorIndex(Request $request, int $range, Carbon $fromDate, bool $showBots)
    {
        // Explicit check using same connection as web app (CLI migrate may use different config)
        if (! Schema::hasTable('visitor_logs') || ! Schema::hasTable('visitor_daily_stats')) {
            $hint = 'visitor_logs and/or visitor_daily_stats are missing. Run: php artisan migrate --force. If you already ran migrate, ensure the web app uses the same DB as the CLI (check .env and config).';
            return $this->visitorIndexErrorView($range, $showBots, 'database', $hint);
        }

        $today = now()->startOfDay();
        $segment = $showBots ? VisitorDailyStat::SEGMENT_ALL : VisitorDailyStat::SEGMENT_HUMANS;

        // Cached on the calendar day so a stale entry can never outlive the
        // nightly rollup, on top of the short TTL and the version bump the
        // rollup itself triggers.
        $signature = implode(':', [$range, $segment, $today->toDateString()]);

        [$analyticsData, $totals, $lastRollupTimestamp] = VisitorStatsCache::remember(
            $signature,
            fn () => $this->assemble($fromDate, $today, $segment)
        );

        return view('admin.visitors.index', [
            'range' => $range,
            'showBots' => $showBots,
            'totals' => $totals,
            'analyticsData' => $analyticsData,
            'lastRollupDate' => $lastRollupTimestamp ? Carbon::parse($lastRollupTimestamp) : null,
            'availableDays' => $this->availableDays($today),
            'error' => null,
        ]);
    }

    /**
     * How far back the data actually goes, so the range selector can stop
     * offering spans nobody can fill.
     *
     * Raw logs are purged at visitorlog.retention_days while aggregates are
     * kept indefinitely, so asking for 365 days used to silently return 90
     * with nothing saying so.
     */
    private function availableDays(Carbon $today): int
    {
        $earliestAggregate = VisitorDailyStat::query()->min('date');
        $earliestLog = DB::table('visitor_logs')->min('occurred_at');

        $candidates = array_filter([$earliestAggregate, $earliestLog]);
        if ($candidates === []) {
            return 0;
        }

        $earliest = collect($candidates)
            ->map(fn ($value) => Carbon::parse($value)->startOfDay())
            ->min();

        return (int) $earliest->diffInDays($today) + 1;
    }

    /**
     * Historical days come from the rollup, today comes from raw logs.
     *
     * The rollup runs just after midnight for the previous day, so today is
     * never in it. Reading a single day back out of visitor_logs keeps the page
     * live without the whole-range scan the page used to do on every request.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: ?string}
     */
    private function assemble(Carbon $fromDate, Carbon $today, string $segment): array
    {
        $stats = VisitorDailyStat::query()
            ->where('segment', $segment)
            // Carbon rather than Y-m-d strings, to match how the date cast
            // stores these ("Y-m-d 00:00:00").
            ->where('date', '>=', $fromDate->copy()->startOfDay())
            ->where('date', '<', $today->copy()->startOfDay())
            ->orderBy('date')
            ->get();

        $series = [];
        foreach ($stats as $stat) {
            $series[$stat->date->format('Y-m-d')] = [
                'pageviews' => (int) $stat->pageviews,
                'uniques' => (int) $stat->uniques,
            ];
        }

        $buckets = [];
        foreach (array_keys(self::BUCKETS) as $bucket) {
            $buckets[$bucket] = $this->sumBuckets($stats, $bucket);
        }

        $totalPageviews = (int) $stats->sum('pageviews');
        $totalUniques = (int) $stats->sum('uniques');
        $totalResponseMs = (int) $stats->sum('total_response_ms');

        $todayStats = $this->todaySoFar($today, $segment);
        if ($todayStats['pageviews'] > 0) {
            $series[$today->format('Y-m-d')] = [
                'pageviews' => $todayStats['pageviews'],
                'uniques' => $todayStats['uniques'],
            ];
            $totalPageviews += $todayStats['pageviews'];
            $totalUniques += $todayStats['uniques'];
            $totalResponseMs += $todayStats['total_response_ms'];

            foreach ($todayStats['buckets'] as $bucket => $counts) {
                foreach ($counts as $label => $count) {
                    $buckets[$bucket][$label] = ($buckets[$bucket][$label] ?? 0) + $count;
                }
            }
        }

        foreach ($buckets as $bucket => $counts) {
            arsort($counts);
            $buckets[$bucket] = array_slice($counts, 0, self::BUCKETS[$bucket], true);
        }

        ksort($series);

        $analyticsData = array_merge([
            'dates' => array_keys($series),
            'pageviews' => array_column($series, 'pageviews'),
            'uniques' => array_column($series, 'uniques'),
        ], $buckets);

        // Uniques are per-day distinct counts, so summing them over-counts
        // anyone who visited on more than one day. Kept as-is for continuity
        // with the previous rollup-backed view; the label in the UI says
        // "unique visits" rather than "unique visitors" for that reason.
        $totals = [
            'pageviews' => $totalPageviews,
            'uniques' => $totalUniques,
            'avg_response_ms' => $totalPageviews > 0 ? (int) round($totalResponseMs / $totalPageviews) : null,
            'days' => count($series),
        ];

        $lastDate = array_key_last($series);

        return [$analyticsData, $totals, $lastDate];
    }

    /**
     * Aggregate the current day straight from raw logs.
     *
     * Bounded to one day, so this stays cheap regardless of the selected range,
     * and the (is_bot, occurred_at) index covers the filter.
     *
     * @return array{pageviews: int, uniques: int, total_response_ms: int, buckets: array<string, array<string, int>>}
     */
    private function todaySoFar(Carbon $today, string $segment): array
    {
        $base = function () use ($today, $segment) {
            $query = DB::table('visitor_logs')->where('occurred_at', '>=', $today);
            if ($segment === VisitorDailyStat::SEGMENT_HUMANS) {
                $query->where('is_bot', false);
            }

            return $query;
        };

        $totalsRow = $base()
            ->selectRaw('COUNT(*) as pageviews, COUNT(DISTINCT ip_hash) as uniques, COALESCE(SUM(response_ms), 0) as total_response_ms')
            ->first();

        $pageviews = (int) ($totalsRow->pageviews ?? 0);
        if ($pageviews === 0) {
            return ['pageviews' => 0, 'uniques' => 0, 'total_response_ms' => 0, 'buckets' => []];
        }

        // One streamed pass rather than a GROUP BY per bucket.
        //
        // Grouping in SQL let the planner choose the index on the grouped
        // column and scan the whole table to avoid a sort, instead of using
        // occurred_at to narrow to today first. On 200k rows that turned a
        // 1ms query into 106ms, nine times over. Reading a single day through
        // a cursor is bounded work with no planner involved, and it is one
        // query instead of nine.
        $columns = array_values(array_map(fn ($spec) => $spec[0], self::BUCKET_COLUMNS));

        $buckets = array_fill_keys(array_keys(self::BUCKET_COLUMNS), []);
        foreach ($base()->select(array_unique($columns))->cursor() as $row) {
            foreach (self::BUCKET_COLUMNS as $bucket => [$column, $defaultKey]) {
                $label = $row->{$column} ?? null;
                if ($label === null || $label === '') {
                    if ($defaultKey === null) {
                        continue;
                    }
                    $label = $defaultKey;
                }

                $buckets[$bucket][$label] = ($buckets[$bucket][$label] ?? 0) + 1;
            }
        }

        foreach ($buckets as $bucket => $counts) {
            arsort($counts);
            $buckets[$bucket] = array_slice($counts, 0, self::BUCKETS[$bucket], true);
        }

        return [
            'pageviews' => $pageviews,
            'uniques' => (int) ($totalsRow->uniques ?? 0),
            'total_response_ms' => (int) ($totalsRow->total_response_ms ?? 0),
            'buckets' => $buckets,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function sumBuckets(Collection $stats, string $field): array
    {
        $totals = [];
        foreach ($stats as $stat) {
            $bucket = $stat->{$field} ?? [];
            if (!is_array($bucket)) {
                continue;
            }
            foreach ($bucket as $key => $count) {
                $totals[$key] = ($totals[$key] ?? 0) + (int) $count;
            }
        }

        return $totals;
    }
}
