@php
    $pendingUpdateVersion = \App\Support\UpdateAvailability::pendingVersion();
@endphp

@if($pendingUpdateVersion !== null)
    <div class="mb-6 p-4 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 rounded-xl" role="status">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
            </svg>
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-200">
                    {{ __('WeatherNode :version is available', ['version' => $pendingUpdateVersion]) }}
                </h3>
                <p class="mt-1 text-sm text-violet-800 dark:text-violet-300">
                    {{ __('You are running :current.', ['current' => \App\Services\VersionService::getAppVersion()]) }}
                    <a href="{{ route('admin.settings.updates') }}" class="underline decoration-dotted hover:decoration-solid">{{ __('See what changed') }}</a>
                </p>
            </div>
        </div>
    </div>
@endif
