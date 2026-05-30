@switch($icon)
    @case('thermometer')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        @break
    @case('wind')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
        </svg>
        @break
    @case('sun')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        @break
    @case('droplet')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
        </svg>
        @break
    @case('plant')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19.5v-15m0 0a8.38 8.38 0 00-3.5.76m3.5-.76a8.38 8.38 0 013.5.76m.97 5.74a7.5 7.5 0 01-8.94 0"/>
        </svg>
        @break
    @case('leaf')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 21c2-3 7-7 13-8M5 21c0-4.5 2-9.5 7-12.5C17 5.5 19 3 20 2c0 3-1 7-4.5 10.5S9 20 5 21z"/>
        </svg>
        @break
    @case('cloud')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
        </svg>
        @break
    @case('gauge')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10l4-4"/>
        </svg>
        @break
    @case('zap')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        @break
    @case('waves')
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 10s3-3 5-3 4 6 7 6 5-3 5-3M2 16s3-3 5-3 4 6 7 6 5-3 5-3"/>
        </svg>
        @break
    @default
        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $chartId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
        </svg>
@endswitch
