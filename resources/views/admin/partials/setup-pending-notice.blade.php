{{-- Not on the setup pages themselves: telling somebody to go where they
     already are is just noise. --}}
@if(\App\Support\FirstRunSetup::unfinished() && !request()->routeIs('admin.setup.*'))
    @php($setupRoute = \App\Support\FirstRunSetup::nextRoute() ?? 'admin.setup.station')
    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl" role="status">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                    {{ __('Finish setting up your station') }}
                </h3>
                <p class="mt-1 text-sm text-blue-800 dark:text-blue-300">
                    {{ __('This install does not know where it is yet, so forecasts, sunrise times and warnings have nothing to go on. It takes a minute.') }}
                </p>
                <a href="{{ route($setupRoute) }}"
                   class="mt-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors">
                    {{ __('Set up the station') }}
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </div>
@endif
