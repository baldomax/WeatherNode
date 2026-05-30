@extends('layouts.admin')

@section('title', __('Visitor Analytics'))

@section('content')
@php
    $topReferrer = array_key_first($analyticsData['referrers'] ?? []);
    $topCountry = array_key_first($analyticsData['countries'] ?? []);
    $labelMap = [
        'Direct' => __('Direct'),
        'Unknown' => __('Unknown'),
        'Other' => __('Other'),
    ];
    $chartStrings = [
        'no_data' => __('No data available'),
        'pageviews' => __('Pageviews'),
        'unique_visitors' => __('Unique Visitors'),
        'count' => __('Count'),
        'label_map' => $labelMap,
    ];
@endphp

<div class="space-y-6">
    @if(!empty($error))
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
            <p class="font-medium">{{ __('Error') }}</p>
            <p class="mt-1 text-sm">{{ $error }}</p>
        </div>
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Aggregated data for the last') }} {{ $range }} {{ __('days') }}
                @if(!$showBots)
                    <span class="text-xs text-blue-600 dark:text-blue-400">({{ __('Bots excluded') }})</span>
                @endif
            </p>
            @if($lastRollupDate)
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    {{ __('Last rollup') }}: {{ $lastRollupDate->format('Y-m-d') }}
                </p>
            @endif
        </div>
        <form method="GET" class="flex items-center gap-3">
            <input type="hidden" name="range" value="{{ $range }}">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="show_bots" value="1" @checked($showBots) onchange="this.form.submit()" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Show bot traffic') }}</span>
            </label>
            <label for="range" class="text-sm text-gray-600 dark:text-gray-300">{{ __('Range') }}</label>
            <select id="range" name="range" class="rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-sm" onchange="this.form.submit()">
                <option value="30" @selected($range === 30)>30 {{ __('days') }}</option>
                <option value="90" @selected($range === 90)>90 {{ __('days') }}</option>
                <option value="365" @selected($range === 365)>365 {{ __('days') }}</option>
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Pageviews') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totals['pageviews']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Unique Visitors') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totals['uniques']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Avg Response') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $totals['avg_response_ms'] ? $totals['avg_response_ms'].' ms' : __('n/a') }}
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Top Referrer') }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $labelMap[$topReferrer] ?? $topReferrer ?? __('Direct') }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Top Country') }}: {{ $labelMap[$topCountry] ?? $topCountry ?? __('Unknown') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Traffic') }}</h2>
            <div id="chart-traffic" class="h-72"></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Devices') }}</h2>
            <div id="chart-devices" class="h-72"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Referrers') }}</h2>
            <div id="chart-referrers" class="h-72"></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Countries') }}</h2>
            <div id="chart-countries" class="h-72"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Search Engines') }}</h2>
            <div id="chart-search-engines" class="h-72"></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Browsers') }}</h2>
            <div id="chart-browsers" class="h-72"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Top Pages') }}</h2>
            @if(count($analyticsData['top_pages']))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Path') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Views') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($analyticsData['top_pages'] as $path => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $path }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Referrer Hosts') }}</h2>
            @if(count($analyticsData['referrers']))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Host') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Visits') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($analyticsData['referrers'] as $host => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $labelMap[$host] ?? $host }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Search Terms') }}</h2>
            @if(count($analyticsData['search_terms']))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Term') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Searches') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($analyticsData['search_terms'] as $term => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $term }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No search terms captured.') }}</p>
            @endif
            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                {{ __('Search terms are only available when the referrer URL includes the query. Many browsers and search engines omit it for privacy, so only a fraction of search visits show terms.') }}
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Status Codes') }}</h2>
            @if(count($analyticsData['status_codes']))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Count') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($analyticsData['status_codes'] as $status => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $status }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Operating Systems') }}</h2>
            @if(count($analyticsData['oses']))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('OS') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Visits') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($analyticsData['oses'] as $os => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $labelMap[$os] ?? $os }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Search Engines (Table)') }}</h2>
            @if(count($analyticsData['search_engines']))
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Engine') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('Visits') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($analyticsData['search_engines'] as $engine => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $labelMap[$engine] ?? $engine }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
            @endif
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Raw IP logs are retained for 90 days; aggregated totals are stored indefinitely. IP addresses are encrypted at rest.') }}
    </p>
</div>

<script type="application/json" id="visitor-analytics-data">
{!! json_encode($analyticsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
<script type="application/json" id="visitor-analytics-strings">
{!! json_encode($chartStrings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
@endsection
