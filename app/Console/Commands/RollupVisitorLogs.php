<?php

namespace App\Console\Commands;

use App\Models\VisitorDailyStat;
use App\Models\VisitorLog;
use App\Support\VisitorStatsCache;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RollupVisitorLogs extends Command
{
    protected $signature = 'visitorlog:rollup {--date=} {--days=1 : Roll up this many days back from --date, oldest first}';
    protected $description = 'Aggregate visitor logs into daily statistics and purge old raw logs.';

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $date = $dateOption ? Carbon::parse($dateOption) : now()->subDay();
        $days = max(1, (int) $this->option('days'));

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $this->rollupDay($date->copy()->subDays($offset));
        }

        $this->backfillMissingSegments();

        $this->purgeOldLogs();
        $this->purgeOldAggregates();

        VisitorStatsCache::flush();

        return self::SUCCESS;
    }

    /**
     * Store one row per segment so the admin page can read either cut of the
     * traffic straight from the rollup. It used to store bot-inclusive totals
     * only, which left the default view (bots hidden) re-scanning raw logs on
     * every request.
     */
    private function rollupDay(Carbon $date, bool $quiet = false): void
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        foreach ([VisitorDailyStat::SEGMENT_ALL, VisitorDailyStat::SEGMENT_HUMANS] as $segment) {
            $query = VisitorLog::query()->whereBetween('occurred_at', [$start, $end]);
            if ($segment === VisitorDailyStat::SEGMENT_HUMANS) {
                $query->where('is_bot', false);
            }

            // Matched on a Carbon rather than a Y-m-d string: the date cast
            // stores "Y-m-d 00:00:00", so a bare date string never matches an
            // existing row and updateOrCreate falls through to an insert that
            // then trips the unique index. That made re-running the rollup for
            // a day that already had a row fail outright.
            VisitorDailyStat::updateOrCreate(
                ['date' => $start->copy(), 'segment' => $segment],
                $this->aggregate($query)
            );
        }

        if (!$quiet) {
            $this->info("Visitor logs rolled up for {$start->toDateString()}.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregate(Builder $baseQuery): array
    {
        $pageviews = (clone $baseQuery)->count();
        $uniques = (clone $baseQuery)->distinct('ip_hash')->count('ip_hash');
        $totalResponseMs = (int) (clone $baseQuery)->sum('response_ms');

        return [
            'pageviews' => $pageviews,
            'uniques' => $uniques,
            'total_response_ms' => $totalResponseMs,
            'avg_response_ms' => $pageviews > 0 ? (int) round($totalResponseMs / $pageviews) : null,
            'status_codes' => $this->aggregateCounts($baseQuery, 'status_code', 20),
            'top_pages' => $this->aggregateCounts($baseQuery, 'path', 10),
            'referrers' => $this->aggregateCounts($baseQuery, 'referrer_host', 10, 'Direct'),
            'countries' => $this->aggregateCounts($baseQuery, 'country_code', 10, 'Unknown'),
            'devices' => $this->aggregateCounts($baseQuery, 'device_type', 10, 'Unknown'),
            'browsers' => $this->aggregateCounts($baseQuery, 'browser_family', 10, 'Other'),
            'oses' => $this->aggregateCounts($baseQuery, 'os_family', 10, 'Other'),
            'search_engines' => $this->aggregateCounts($baseQuery, 'search_engine', 10, 'Unknown'),
            'search_terms' => $this->aggregateCounts((clone $baseQuery)->whereNotNull('search_terms'), 'search_terms', 10),
        ];
    }

    private function aggregateCounts(Builder $query, string $column, int $limit, ?string $defaultKey = null): array
    {
        $rows = (clone $query)
            ->selectRaw("{$column} as label, count(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $label = $row->label;
            if ($label === null || $label === '') {
                if ($defaultKey === null) {
                    continue;
                }
                $label = $defaultKey;
            }

            // A null and an empty string both fold into the default label.
            $result[$label] = ($result[$label] ?? 0) + (int) $row->count;
        }

        arsort($result);

        if ($limit > 0 && count($result) > $limit) {
            $result = array_slice($result, 0, $limit, true);
        }

        return $result;
    }

    /**
     * Roll up any past day the rollup does not fully cover yet.
     *
     * Two ways a day ends up here:
     *
     *  - Upgraded installs. Before segments existed the rollup wrote one
     *    bot-inclusive row per day, which the migration labels 'all', so every
     *    historical day is missing its 'humans' half.
     *  - Missed nights. If the scheduler was not running, or the site was down
     *    at 00:15, that day never got a row at all. The page used to scan raw
     *    logs so it still showed those days; reading the rollup would silently
     *    drop them.
     *
     * Deliberately before the purge, so a day can still be captured from raw
     * logs on the last night before those logs age out.
     *
     * Capped so a pathological table cannot turn the nightly job into an
     * all-night job; the remainder is picked up on the next run.
     */
    private function backfillMissingSegments(int $maxDays = 400): void
    {
        $today = now()->startOfDay();

        // Dates formatted in PHP rather than compared in SQL: the date cast
        // round-trips through "Y-m-d 00:00:00", so matching stored values
        // against Y-m-d strings finds nothing and every night would re-roll
        // everything.
        $covered = VisitorDailyStat::query()
            ->get(['date', 'segment'])
            ->groupBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'))
            ->filter(fn ($rows) => $rows->pluck('segment')
                ->intersect([VisitorDailyStat::SEGMENT_ALL, VisitorDailyStat::SEGMENT_HUMANS])
                ->unique()
                ->count() === 2)
            ->keys()
            ->flip();

        $candidates = DB::table('visitor_logs')
            ->where('occurred_at', '<', $today)
            ->selectRaw('DATE(occurred_at) as day')
            ->distinct()
            ->pluck('day')
            ->merge(
                VisitorDailyStat::query()
                    ->pluck('date')
                    ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            )
            ->map(fn ($day) => (string) $day)
            ->unique()
            ->reject(fn ($day) => $covered->has($day))
            ->sortDesc()
            ->take($maxDays)
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $this->info("Backfilling {$candidates->count()} day(s) of visitor statistics...");

        foreach ($candidates as $day) {
            $this->rollupDay(Carbon::parse($day), quiet: true);
        }
    }

    private function purgeOldLogs(): void
    {
        $retentionDays = config('visitorlog.retention_days');
        if (!is_numeric($retentionDays) || $retentionDays <= 0) {
            return;
        }

        $cutoff = now()->subDays((int) $retentionDays)->startOfDay();
        VisitorLog::query()->where('occurred_at', '<', $cutoff)->delete();
    }

    private function purgeOldAggregates(): void
    {
        $retentionDays = config('visitorlog.aggregate_retention_days');
        if (!is_numeric($retentionDays) || $retentionDays <= 0) {
            return;
        }

        $cutoff = now()->subDays((int) $retentionDays)->startOfDay()->toDateString();
        VisitorDailyStat::query()->where('date', '<', $cutoff)->delete();
    }
}
