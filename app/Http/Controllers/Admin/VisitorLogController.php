<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisitorDailyStat;
use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitorLogController extends Controller
{
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
        $emptyData = [
            'dates' => [],
            'pageviews' => [],
            'uniques' => [],
            'referrers' => [],
            'countries' => [],
            'devices' => [],
            'browsers' => [],
            'oses' => [],
            'search_engines' => [],
            'search_terms' => [],
            'top_pages' => [],
            'status_codes' => [],
        ];
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
            'error' => $message,
        ]);
    }

    private function buildVisitorIndex(Request $request, int $range, \Illuminate\Support\Carbon $fromDate, bool $showBots)
    {
        // Explicit check using same connection as web app (CLI migrate may use different config)
        if (! Schema::hasTable('visitor_logs') || ! Schema::hasTable('visitor_daily_stats')) {
            $hint = 'visitor_logs and/or visitor_daily_stats are missing. Run: php artisan migrate --force. If you already ran migrate, ensure the web app uses the same DB as the CLI (check .env and config).';
            return $this->visitorIndexErrorView($range, $showBots, 'database', $hint);
        }

        if ($showBots) {
            // Use aggregated stats (includes bots)
            $stats = VisitorDailyStat::query()
                ->where('date', '>=', $fromDate->toDateString())
                ->orderBy('date')
                ->get();

            $dates = $stats->pluck('date')->map(fn ($date) => $date->format('Y-m-d'))->all();
            $pageviews = $stats->pluck('pageviews')->all();
            $uniques = $stats->pluck('uniques')->all();

            $totals = [
                'pageviews' => $stats->sum('pageviews'),
                'uniques' => $stats->sum('uniques'),
                'avg_response_ms' => $this->weightedAverageResponseMs($stats),
                'days' => $stats->count(),
            ];

            $topPages = $this->sumBuckets($stats, 'top_pages', 10);
            $referrers = $this->sumBuckets($stats, 'referrers', 10);
            $countries = $this->sumBuckets($stats, 'countries', 10);
            $devices = $this->sumBuckets($stats, 'devices', 10);
            $browsers = $this->sumBuckets($stats, 'browsers', 10);
            $oses = $this->sumBuckets($stats, 'oses', 10);
            $searchEngines = $this->sumBuckets($stats, 'search_engines', 10);
            $searchTerms = $this->sumBuckets($stats, 'search_terms', 10);
            $statusCodes = $this->sumBuckets($stats, 'status_codes', 10);

            $lastRollupDate = $stats->last()?->date;
        } else {
            // Aggregate from DB (avoids loading all rows into memory on high-traffic prod)
            $base = fn () => DB::table('visitor_logs')
                ->where('occurred_at', '>=', $fromDate)
                ->where('is_bot', false);

            $dailyRows = (clone $base())
                ->selectRaw('DATE(occurred_at) as date, COUNT(*) as pageviews, COUNT(DISTINCT ip_hash) as uniques, COALESCE(SUM(response_ms), 0) as total_response_ms')
                ->groupBy(DB::raw('DATE(occurred_at)'))
                ->orderBy('date')
                ->get();

            $dates = $dailyRows->pluck('date')->map(fn ($d) => is_string($d) ? $d : $d->format('Y-m-d'))->all();
            $pageviews = $dailyRows->pluck('pageviews')->all();
            $uniques = $dailyRows->pluck('uniques')->all();
            $totalPageviews = $dailyRows->sum('pageviews');
            $totalResponseMs = $dailyRows->sum('total_response_ms');

            $totalsRow = (clone $base())
                ->selectRaw('COUNT(*) as pageviews, COUNT(DISTINCT ip_hash) as uniques, COALESCE(SUM(response_ms), 0) as total_response_ms')
                ->first();
            $totals = [
                'pageviews' => (int) ($totalsRow->pageviews ?? 0),
                'uniques' => (int) ($totalsRow->uniques ?? 0),
                'avg_response_ms' => $totalPageviews > 0 ? (int) round($totalResponseMs / $totalPageviews) : null,
                'days' => count($dates),
            ];

            $topPages = $this->aggregateFromDb($base(), 'path', 10);
            $referrers = $this->aggregateFromDb($base(), 'referrer_host', 10, 'Direct');
            $countries = $this->aggregateFromDb($base(), 'country_code', 10, 'Unknown');
            $devices = $this->aggregateFromDb($base(), 'device_type', 10, 'Unknown');
            $browsers = $this->aggregateFromDb($base(), 'browser_family', 10, 'Other');
            $oses = $this->aggregateFromDb($base(), 'os_family', 10, 'Other');
            $searchEngines = $this->aggregateFromDb((clone $base())->whereNotNull('search_engine')->where('search_engine', '!=', ''), 'search_engine', 10, 'Unknown');
            $searchTerms = $this->aggregateFromDb((clone $base())->whereNotNull('search_terms')->where('search_terms', '!=', ''), 'search_terms', 10);
            $statusCodes = $this->aggregateFromDb($base(), 'status_code', 10);

            $lastDate = (clone $base())->selectRaw('MAX(occurred_at) as last_at')->value('last_at');
            $lastRollupDate = $lastDate ? \Illuminate\Support\Carbon::parse($lastDate)->startOfDay() : null;
        }

        $analyticsData = [
            'dates' => $dates,
            'pageviews' => $pageviews,
            'uniques' => $uniques,
            'referrers' => $referrers,
            'countries' => $countries,
            'devices' => $devices,
            'browsers' => $browsers,
            'oses' => $oses,
            'search_engines' => $searchEngines,
            'search_terms' => $searchTerms,
            'top_pages' => $topPages,
            'status_codes' => $statusCodes,
        ];

        return view('admin.visitors.index', [
            'range' => $range,
            'showBots' => $showBots,
            'totals' => $totals,
            'analyticsData' => $analyticsData,
            'lastRollupDate' => $lastRollupDate,
            'error' => null,
        ]);
    }

    private function sumBuckets(Collection $stats, string $field, int $limit): array
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

        arsort($totals);

        if ($limit > 0 && count($totals) > $limit) {
            $totals = array_slice($totals, 0, $limit, true);
        }

        return $totals;
    }

    private function weightedAverageResponseMs(Collection $stats): ?int
    {
        $totalResponseMs = 0;
        $totalRequests = 0;

        foreach ($stats as $stat) {
            $totalResponseMs += ($stat->total_response_ms ?? 0);
            $totalRequests += ($stat->pageviews ?? 0);
        }

        if ($totalRequests === 0) {
            return null;
        }

        return (int) round($totalResponseMs / $totalRequests);
    }

    private function aggregateFromLogs(Collection $logs, string $field, int $limit, ?string $defaultKey = null): array
    {
        $counts = $logs->groupBy($field)->map->count();

        $result = [];
        foreach ($counts as $key => $count) {
            $label = $key;
            if ($label === null || $label === '') {
                if ($defaultKey === null) {
                    continue;
                }
                $label = $defaultKey;
            }

            $result[$label] = (int) $count;
        }

        arsort($result);

        if ($limit > 0 && count($result) > $limit) {
            $result = array_slice($result, 0, $limit, true);
        }

        return $result;
    }

    /**
     * Run aggregation on visitor_logs in the DB (avoids loading all rows).
     */
    private function aggregateFromDb(\Illuminate\Database\Query\Builder $query, string $field, int $limit, ?string $defaultKey = null): array
    {
        $rows = (clone $query)
            ->selectRaw($field . ' as label, COUNT(*) as cnt')
            ->groupBy($field)
            ->orderByDesc('cnt')
            ->when($limit > 0, fn ($q) => $q->limit($limit * 2))
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
            $result[$label] = (int) $row->cnt;
        }

        arsort($result);
        if ($limit > 0 && count($result) > $limit) {
            $result = array_slice($result, 0, $limit, true);
        }

        return $result;
    }
}
