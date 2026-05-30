<?php

namespace App\Console\Commands;

use App\Models\VisitorDailyStat;
use App\Models\VisitorLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RollupVisitorLogs extends Command
{
    protected $signature = 'visitorlog:rollup {--date=}';
    protected $description = 'Aggregate visitor logs into daily statistics and purge old raw logs.';

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $date = $dateOption ? Carbon::parse($dateOption) : now()->subDay();

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $baseQuery = VisitorLog::query()->whereBetween('occurred_at', [$start, $end]);

        $pageviews = (clone $baseQuery)->count();
        $uniques = (clone $baseQuery)->distinct('ip_hash')->count('ip_hash');
        $totalResponseMs = (clone $baseQuery)->sum('response_ms');
        $avgResponseMs = $pageviews > 0 ? (int) round($totalResponseMs / $pageviews) : null;

        $statusCodes = $this->aggregateCounts($baseQuery, 'status_code', 20);
        $topPages = $this->aggregateCounts($baseQuery, 'path', 10);
        $referrers = $this->aggregateCounts($baseQuery, 'referrer_host', 10, 'Direct');
        $countries = $this->aggregateCounts($baseQuery, 'country_code', 10, 'Unknown');
        $devices = $this->aggregateCounts($baseQuery, 'device_type', 10, 'Unknown');
        $browsers = $this->aggregateCounts($baseQuery, 'browser_family', 10, 'Other');
        $oses = $this->aggregateCounts($baseQuery, 'os_family', 10, 'Other');
        $searchEngines = $this->aggregateCounts($baseQuery, 'search_engine', 10, 'Unknown');
        $searchTerms = $this->aggregateCounts((clone $baseQuery)->whereNotNull('search_terms'), 'search_terms', 10);

        VisitorDailyStat::updateOrCreate(
            ['date' => $start->toDateString()],
            [
                'pageviews' => $pageviews,
                'uniques' => $uniques,
                'total_response_ms' => $totalResponseMs,
                'avg_response_ms' => $avgResponseMs,
                'status_codes' => $statusCodes,
                'top_pages' => $topPages,
                'referrers' => $referrers,
                'countries' => $countries,
                'devices' => $devices,
                'browsers' => $browsers,
                'oses' => $oses,
                'search_engines' => $searchEngines,
                'search_terms' => $searchTerms,
            ]
        );

        $this->purgeOldLogs();
        $this->purgeOldAggregates();

        $this->info("Visitor logs rolled up for {$start->toDateString()}.");

        return self::SUCCESS;
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

            $result[$label] = (int) $row->count;
        }

        if ($limit > 0 && count($result) > $limit) {
            $result = array_slice($result, 0, $limit, true);
        }

        return $result;
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
