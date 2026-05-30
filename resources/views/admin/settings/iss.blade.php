@extends('layouts.admin')

@section('content')
<div class="w-full py-8 px-4">
    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('admin.settings.index') }}" class="hover:text-gray-900 dark:hover:text-gray-200">{{ __('Settings') }}</a>
        <span>/</span>
        <span class="text-gray-900 dark:text-gray-200">{{ __('ISS / Space Stations') }}</span>
    </nav>

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ __('ISS / Space Stations') }}</h1>
        <p class="text-gray-600 dark:text-gray-400">{{ __('Configure International Space Station and Tiangong tracking settings') }}</p>
    </div>

    <!-- Settings Form -->
    <form method="POST" action="{{ route('admin.settings.update', 'iss') }}" class="space-y-6">
        @csrf

        <!-- Enable/Disable -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('General Settings') }}</h2>
            
            <div class="space-y-6">
                <div class="p-4 bg-white/5 dark:bg-gray-900/30 rounded-lg border border-gray-200/50 dark:border-gray-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enable Space Station Tracking') }}</label>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">{{ __('Show space station cards on the dashboard and astronomy page') }}</p>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('iss.enabled', true)"
                        name="iss_enabled"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>

                <div class="p-4 bg-white/5 dark:bg-gray-900/30 rounded-lg border border-gray-200/50 dark:border-gray-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Show ISS') }}</label>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">{{ __('Display International Space Station data') }}</p>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('iss.show_iss', true)"
                        name="iss_show_iss"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>

                <div class="p-4 bg-white/5 dark:bg-gray-900/30 rounded-lg border border-gray-200/50 dark:border-gray-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Show Tiangong') }}</label>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">{{ __('Display Tiangong Space Station data') }}</p>
                    <x-toggle-switch
                        :enabled="\App\Models\Setting::getValue('iss.show_tiangong', true)"
                        name="iss_show_tiangong"
                        :labelEnabled="__('Enabled')"
                        :labelDisabled="__('Disabled')"
                    />
                </div>
            </div>
        </div>

        <!-- API Settings -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('API Configuration') }}</h2>
            
            <div class="space-y-4">
                <div>
                    <label for="iss_astronauts_api_source" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Astronaut Data API Source') }}
                    </label>
                    <select name="iss_astronauts_api_source" id="iss_astronauts_api_source" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="corquaid" {{ \App\Models\Setting::getValue('iss.astronauts_api_source', 'corquaid') === 'corquaid' ? 'selected' : '' }}>
                            {{ __('corquaid.github.io (Recommended)') }}
                        </option>
                        <option value="open-notify" {{ \App\Models\Setting::getValue('iss.astronauts_api_source', 'corquaid') === 'open-notify' ? 'selected' : '' }}>
                            {{ __('Open Notify API') }}
                        </option>
                        <option value="n2yo" {{ \App\Models\Setting::getValue('iss.astronauts_api_source', 'corquaid') === 'n2yo' ? 'selected' : '' }}>
                            {{ __('N2YO.com API') }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Choose the API source for astronaut data. N2YO requires an API key.') }}
                    </p>
                </div>

                <div>
                    <label for="iss_n2yo_api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('N2YO.com API Key') }}
                    </label>
                    <input type="text" name="iss_n2yo_api_key" id="iss_n2yo_api_key" 
                           value="{{ \App\Models\Setting::getValue('iss.n2yo_api_key', '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                           placeholder="{{ __('Enter your N2YO API key') }}">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Required if using N2YO source. Get your free API key at') }}
                        <a href="https://www.n2yo.com/api/" target="_blank" class="text-blue-500 hover:underline">n2yo.com/api</a>
                    </p>
                </div>

                <div>
                    <label for="iss_astronauts_poll_frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ __('Poll Frequency (minutes)') }}
                    </label>
                    <input type="number" name="iss_astronauts_poll_frequency" id="iss_astronauts_poll_frequency" 
                           value="{{ \App\Models\Setting::getValue('iss.astronauts_poll_frequency', 60) }}"
                           min="15" max="1440"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('How often to poll astronaut data (15-1440 minutes). Default: 60 minutes.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>
</div>
@endsection
