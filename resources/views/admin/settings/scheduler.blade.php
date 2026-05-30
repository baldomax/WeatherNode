@extends('layouts.admin')

@section('title', __($groupInfo['label']))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __($groupInfo['label']) }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-{{ $groupInfo['color'] }}-100 dark:bg-{{ $groupInfo['color'] }}-900/30">
                <svg class="w-8 h-8 text-{{ $groupInfo['color'] }}-600 dark:text-{{ $groupInfo['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __($groupInfo['label']) }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __($groupInfo['description']) }}</p>
            </div>
        </div>
    </div>

    @php
        $status = $schedulerStatus['status'] ?? 'never';
        $statusColor = $status === 'running' ? 'green' : ($status === 'stale' ? 'yellow' : 'red');
        $statusLabel = $status === 'running' ? __('Running') : ($status === 'stale' ? __('Stale') : __('Not running'));
    @endphp

    <div class="mb-6 p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Scheduler Status') }}</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $statusLabel }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Last run') }}:
                    {{ ($schedulerStatus['last_run'] ?? null) ? $schedulerStatus['last_run']->diffForHumans() : __('Never') }}
                </p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800 dark:bg-{{ $statusColor }}-900/30 dark:text-{{ $statusColor }}-300">
                {{ $statusLabel }}
            </span>
        </div>
        <div class="mt-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Cron entry (required for scheduler):') }}</p>
            <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-900/40 text-gray-900 dark:text-gray-100 rounded-md p-3 overflow-x-auto">{{ $schedulerStatus['cron_line'] ?? '' }}</pre>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Scheduled Jobs') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Overview of background tasks and their settings.') }}</p>
            <div class="mt-3 space-y-3">
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <p class="text-xs text-blue-800 dark:text-blue-200">
                        <strong>💡 {{ __('Smart Interval Tracking') }}:</strong>
                        {{ __('External poller commands (forecast, air quality, etc.) use smart interval tracking. The scheduler runs every minute, but each service only polls when its interval has passed (15, 30, or 60 minutes). If you see "not due yet" in logs, the service was recently polled and is waiting for its interval. Use') }}
                        <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">--force</code>
                        {{ __('to bypass intervals and poll immediately.') }}
                    </p>
                </div>
                <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <p class="text-xs text-green-800 dark:text-green-200">
                        <strong>🔄 {{ __('Self-Healing Health Check') }}:</strong>
                        {{ __('A health check runs every 5 minutes to detect missing or invalid cache data (forecast, astronomy, air quality, pollen, tide, aurora). If data is missing or invalid, it automatically fetches it immediately. This ensures missing fields are restored within 5 minutes maximum.') }}
                    </p>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Job') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Schedule') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Last Run') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Command') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Log') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($schedulerTasks ?? [] as $task)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                <div class="font-medium">{{ __($task['name']) }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __($task['description']) }}</div>
                                @if(!empty($task['settings_group']) && isset($allGroups[$task['settings_group']]))
                                    <a href="{{ route('admin.settings.group', $task['settings_group']) }}" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ __('Open settings') }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ __($task['schedule']) }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($task['enabled'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        {{ __('Enabled') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ __('Disabled') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ $task['last_run'] ? $task['last_run']->diffForHumans() : __('Never') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                <code class="text-xs">{{ $task['command'] }}</code>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                <code class="text-xs">{{ $task['log'] }}</code>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">
                                {{ __('No scheduled jobs configured.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
