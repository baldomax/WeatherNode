@extends('layouts.admin')

@section('title', __('Weather Alerts Settings'))

@section('content')
<div class="w-full" x-data="alertsSettings()">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Weather Alerts') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-red-100 dark:bg-red-900/30">
                <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Weather Alerts') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure worldwide weather warning services') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.settings.alerts.update') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- Global Enable Toggle -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enable Weather Alerts') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Display severe weather warnings on dashboard') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="$settings['enabled']"
                        name="alerts_enabled"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>
            </div>
        </div>

        <!-- Alert Source Selection -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Alert Service') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Choose the weather alert service based on your location') }}</p>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($sources as $key => $source)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="source" value="{{ $key }}" 
                               x-model="selectedSource"
                               class="sr-only peer">
                        <div class="p-3 rounded-lg border transition-all duration-200
                                    peer-checked:border-red-500 peer-checked:bg-red-50 dark:peer-checked:bg-red-900/20
                                    border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">
                                    @switch($key)
                                        @case('europe') 🇪🇺 @break
                                        @case('usa') 🇺🇸 @break
                                        @case('canada') 🇨🇦 @break
                                        @case('uk') 🇬🇧 @break
                                        @case('australia') 🇦🇺 @break
                                    @endswitch
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $source['name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $source['description'] }}</div>
                                </div>
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Region Configuration Panels -->
        <!-- Europe (Meteoalarm) -->
        <div x-show="selectedSource === 'europe'" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🇪🇺</span>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Meteoalarm Configuration') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('35 European countries supported') }}</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Region Code') }}</label>
                    <input type="text" name="region_code" value="{{ $settings['region_code'] }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500"
                           placeholder="{{ __('e.g., NL011, DE031, FR075') }}">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Find your region code at') }} <a href="https://saratoga-weather.org/meteoalarm-map/" target="_blank" rel="noopener noreferrer" class="text-red-500 hover:underline">the MeteoAlarm region map</a></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Region name (optional)') }}</label>
                    <input type="text" name="region_name" value="{{ $settings['region_name'] ?? '' }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500"
                           placeholder="{{ __('e.g., Noord-Holland') }}">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Shown on the alerts widget instead of the region code. Leave empty to use the built-in name.') }}</p>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                    <h4 class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Common Region Codes:') }}</h4>
                    <div class="grid grid-cols-2 gap-1 text-xs text-gray-500 dark:text-gray-400">
                        <span>🇳🇱 NL011 - Noord-Holland</span>
                        <span>🇩🇪 DE031 - Berlin</span>
                        <span>🇫🇷 FR075 - Paris</span>
                        <span>🇧🇪 BE004 - Antwerp</span>
                        <span>🇪🇸 ES110 - Madrid</span>
                        <span>🇮🇹 IT089 - Rome</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- USA (NWS) -->
        <div x-show="selectedSource === 'usa'" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🇺🇸</span>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('NWS Configuration') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('National Weather Service - All 50 US states') }}</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('State Code') }}</label>
                        <select name="us_state" 
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500">
                            @foreach($sources['usa']['regions'] as $code => $name)
                                <option value="{{ $code }}" {{ $settings['us_state'] === $code ? 'selected' : '' }}>{{ $code }} - {{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Zone Code (Optional)') }}</label>
                        <input type="text" name="us_zone" value="{{ $settings['us_zone'] }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500"
                               placeholder="{{ __('e.g., NYZ072') }}">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Leave empty for all state alerts. Find zones at') }} <a href="https://alerts.weather.gov" target="_blank" class="text-red-500 hover:underline">alerts.weather.gov</a></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canada (Environment Canada) -->
        <div x-show="selectedSource === 'canada'" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🇨🇦</span>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Environment Canada Configuration') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('All Canadian provinces and territories') }}</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Province/Territory') }}</label>
                        <select name="province" 
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500">
                            @foreach($sources['canada']['regions'] as $code => $name)
                                <option value="{{ $code }}" {{ $settings['province'] === $code ? 'selected' : '' }}>{{ $code }} - {{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Region Code') }}</label>
                        <input type="text" name="ca_region_code" value="{{ $settings['ca_region_code'] }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500"
                               placeholder="{{ __('e.g., on-143') }}">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Find at') }} <a href="https://weather.gc.ca/warnings/index_e.html" target="_blank" class="text-red-500 hover:underline">weather.gc.ca</a></p>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                    <h4 class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Common Region Codes:') }}</h4>
                    <div class="grid grid-cols-2 gap-1 text-xs text-gray-500 dark:text-gray-400">
                        <span>on-143 - Toronto</span>
                        <span>bc-74 - Vancouver</span>
                        <span>qc-147 - Montreal</span>
                        <span>ab-52 - Calgary</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- UK (Met Office) -->
        <div x-show="selectedSource === 'uk'" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🇬🇧</span>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Met Office Configuration') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('UK weather warnings by region') }}</p>
                    </div>
                </div>
            </div>
            <div class="p-5">
                <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('Region') }}</label>
                <select name="uk_region" 
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500">
                    @foreach($sources['uk']['regions'] as $code => $name)
                        <option value="{{ $code }}" {{ $settings['uk_region'] === $code ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Australia (BOM) -->
        <div x-show="selectedSource === 'australia'" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🇦🇺</span>
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Bureau of Meteorology Configuration') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Australian state/territory warnings') }}</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 dark:text-white mb-1">{{ __('State/Territory') }}</label>
                    <select name="au_state" 
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-red-500 focus:border-red-500">
                        @foreach($sources['australia']['regions'] as $code => $name)
                            <option value="{{ $code }}" {{ $settings['au_state'] === $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-lg">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-medium text-amber-800 dark:text-amber-400">{{ __('Limited Availability') }}</h4>
                            <p class="text-xs text-amber-700 dark:text-amber-300/80 mt-1">{{ __('BOM restricts automated access. For critical warnings, check') }} <a href="http://www.bom.gov.au/warnings/" target="_blank" class="underline">bom.gov.au</a> {{ __('directly.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Status -->
        @if($testResult)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Connection Status') }}</label>
            </div>
            <div class="p-5">
                @if($testResult['success'])
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800/50 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium text-green-800 dark:text-green-400">{{ __('Service Connected') }}</span>
                            <span class="text-xs text-green-700 dark:text-green-300">{{ $testResult['count'] }} {{ __('alerts found') }}</span>
                        </div>
                        
                        @if(count($testResult['alerts']) > 0)
                            <div class="space-y-2 mt-3">
                                @foreach($testResult['alerts'] as $alert)
                                    <div class="p-2 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $alert['severity_color'] ?? '#FBEA55' }}"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs text-gray-900 dark:text-white truncate">{{ Str::limit($alert['title'], 50) }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $alert['warning_type'] ?? __('General') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-green-700 dark:text-green-300 mt-1">{{ __('No active warnings for your region - all clear!') }} 🎉</p>
                        @endif
                    </div>
                @else
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium text-red-800 dark:text-red-400">{{ __('Connection Failed') }}</span>
                        </div>
                        @if(isset($testResult['error']))
                            <p class="text-xs text-red-700 dark:text-red-300 mt-1">{{ $testResult['error'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

<script>
function alertsSettings() {
    return {
        selectedSource: '{{ $settings['source'] }}',
    }
}
</script>
@endsection
