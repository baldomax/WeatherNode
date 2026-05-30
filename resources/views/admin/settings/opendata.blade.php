@extends('layouts.admin')

@section('title', __('Open Data Sources'))

@section('content')
@php
    $providers = $providers ?? [];
    $isInNetherlands = $isInNetherlands ?? false;
@endphp

<div class="w-full">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Open Data Sources') }}</li>
        </ol>
    </nav>

    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Open Data Sources') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Manage open data sources from meteorological agencies worldwide. Enable data sources to access weather visualizations and forecasts.') }}</p>
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

    <form action="{{ route('admin.settings.opendata.update') }}" method="POST" class="space-y-6">
        @csrf

        <!-- Providers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($providers as $provider)
                @php
                    $isImplemented = $provider->isImplemented();
                    $isEnabled = $provider->isEnabled();
                    $status = $provider->getStatus();
                    $countryCode = strtolower($provider->getCountry());
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700 {{ !$isImplemented ? 'opacity-75' : '' }}">
                    <!-- Header -->
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $provider->getName() }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $provider->getCoverageArea() }}</p>
                            </div>
                            <div>
                                @if($isImplemented)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ __('Available') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ __('Coming Soon') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">{{ $provider->getDescription() }}</p>
                        
                        <!-- Features -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach($provider->getFeatures() as $feature)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    {{ ucfirst(str_replace('_', ' ', $feature)) }}
                                </span>
                            @endforeach
                        </div>

                        <!-- Location Warning (for KNMI if outside Netherlands) -->
                        @if($provider->getSettingsKey() === 'knmi' && !$isInNetherlands)
                            <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                <div class="flex items-start">
                                    <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-xs text-yellow-800 dark:text-yellow-200">
                                        {{ __('Note: KNMI data covers the Netherlands region. Your station appears to be outside this area, so the data may not be relevant for your location.') }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="p-5">
                        @if($isImplemented)
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-gray-900 dark:text-white">
                                        {{ __('Enabled') }}
                                    </label>
                                    <x-toggle-switch
                                        :enabled="$isEnabled"
                                        name="opendata_{{ $provider->getSettingsKey() }}_enabled"
                                        :labelEnabled="__('Enabled')"
                                        :labelDisabled="__('Disabled')"
                                    />
                                </div>
                                @if($provider->getApiUrl())
                                    <a href="{{ $provider->getApiUrl() }}" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center">
                                        {{ __('API Documentation') }}
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="space-y-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                                    {{ __('To be implemented') }}
                                </p>
                                @if($provider->getApiUrl())
                                    <a href="{{ $provider->getApiUrl() }}" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center">
                                        {{ __('View API Documentation') }}
                                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                @endif
                                <button type="button" onclick="suggestPriority('{{ $provider->getSettingsKey() }}', '{{ $provider->getName() }}')" class="w-full text-xs px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition">
                                    {{ __('Suggest Implementation Priority') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Suggest New Data Source Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex items-start space-x-4">
                <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('Suggest New Data Source') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ __('Know of another open data initiative from a meteorological agency? We\'d love to hear about it!') }}
                    </p>
                    <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-4 mb-4">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Please provide:') }}</p>
                        <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                            <li>{{ __('Agency name and country') }}</li>
                            <li>{{ __('Link to API documentation') }}</li>
                            <li>{{ __('Coverage area') }}</li>
                            <li>{{ __('Available data types (WMS, radar, forecasts, etc.)') }}</li>
                        </ul>
                    </div>
                    <a href="https://github.com/centauri/WeatherNode/issues/new?template=data-source-suggestion.md" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition text-sm">
                        {{ __('Submit Suggestion') }}
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function suggestPriority(providerKey, providerName) {
    const title = encodeURIComponent(`Priority Request: ${providerName}`);
    const body = encodeURIComponent(`I would like to request priority implementation for ${providerName}.\n\nProvider Key: ${providerKey}\n\nAdditional notes:\n`);
    const url = `https://github.com/centauri/WeatherNode/issues/new?title=${title}&body=${body}`;
    window.open(url, '_blank', 'noopener,noreferrer');
}
</script>
@endpush
@endsection
