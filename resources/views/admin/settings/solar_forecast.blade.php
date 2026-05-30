@extends('layouts.admin')

@section('title', __('Solar Radiation Forecast Settings'))

@section('content')
@php
    $enabled = (bool) \App\Models\Setting::getValue('solar_forecast.enabled', false);
    $provider = \App\Models\Setting::getValue('solar_forecast.provider', 'open_meteo');
    $forecastHours = (int) \App\Models\Setting::getValue('solar_forecast.forecast_hours', 48);
    $updateInterval = (int) \App\Models\Setting::getValue('solar_forecast.update_interval', 30);
    $solcastApiKey = \App\Models\Setting::getValue('solar_forecast.solcast_api_key', '');
@endphp

<div class="w-full">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Solar Radiation Forecast Settings') }}</li>
        </ol>
    </nav>

    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                <svg class="w-8 h-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Solar Radiation Forecast Settings') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Choose a solar forecast provider and configure cache. Data is shown on the satellite page.') }}</p>
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

    <form action="{{ route('admin.settings.update', 'solar_forecast') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enable Solar Radiation Forecast') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Show solar radiation forecast on satellite page') }}</p>
                    </div>
                    <x-toggle-switch
                        :enabled="$enabled"
                        name="solar_forecast_enabled"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>
            </div>

            @if($enabled)
            <div class="p-5">
                <label for="solar_forecast_provider" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Solar Forecast Provider') }}</label>
                <select name="solar_forecast_provider"
                        id="solar_forecast_provider"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                    <option value="open_meteo" {{ $provider === 'open_meteo' ? 'selected' : '' }}>{{ __('Open-Meteo (Free - Global irradiance)') }}</option>
                    <option value="forecast_solar" {{ $provider === 'forecast_solar' ? 'selected' : '' }}>{{ __('Forecast.Solar (Free - PV estimate)') }}</option>
                    <option value="open_quartz" {{ $provider === 'open_quartz' ? 'selected' : '' }}>{{ __('Open Quartz Solar (Free - PV estimate)') }}</option>
                    <option value="solcast" {{ $provider === 'solcast' ? 'selected' : '' }}>{{ __('Solcast (API key required - Premium irradiance)') }}</option>
                </select>
            </div>

            <div class="p-5" id="solar_forecast_solcast_key" style="{{ $provider === 'solcast' ? '' : 'display:none' }}">
                <label for="solar_forecast_solcast_api_key" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Solcast API Key') }}</label>
                <input type="password"
                       name="solar_forecast_solcast_api_key"
                       id="solar_forecast_solcast_api_key"
                       value="{{ $solcastApiKey }}"
                       placeholder="{{ $solcastApiKey ? '••••••••' : '' }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                       autocomplete="off">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Get a free API key at Solcast Toolkit. Leave blank to keep existing key.') }}</p>
            </div>

            <div class="p-5">
                <label for="solar_forecast_forecast_hours" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Forecast Hours') }}</label>
                <input type="number"
                       name="solar_forecast_forecast_hours"
                       id="solar_forecast_forecast_hours"
                       value="{{ $forecastHours }}"
                       min="1"
                       max="48"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Number of hours ahead to show (1–48, default: 24). Only daylight is shown on the chart.') }}</p>
            </div>

            <div class="p-5">
                <label for="solar_forecast_update_interval" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Update Interval') }} ({{ __('minutes') }})</label>
                <input type="number"
                       name="solar_forecast_update_interval"
                       id="solar_forecast_update_interval"
                       value="{{ $updateInterval }}"
                       min="15"
                       max="120"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('How often to refresh forecast data (default: 30 minutes)') }}</p>
            </div>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                {{ __('Save') }}
            </button>
        </div>
    </form>

    <script>
        document.getElementById('solar_forecast_provider').addEventListener('change', function() {
            var el = document.getElementById('solar_forecast_solcast_key');
            el.style.display = this.value === 'solcast' ? '' : 'none';
        });
    </script>
@endsection
