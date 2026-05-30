@extends('layouts.admin')

@section('title', __('Dashboard Widgets'))

@section('content')
<div class="w-full" x-data="widgetManager()">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Dashboard Widgets') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Dashboard Widgets') }}</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">{{ __('Choose which widgets appear on your weather dashboard. Toggle them on or off and drag to reorder.') }}</p>
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

    <form action="{{ route('admin.settings.widgets.update') }}" method="POST" id="widgetForm">
        @csrf
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Available Widgets -->
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Available Widgets') }}</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="enabledCount"></span> {{ __('widgets enabled') }}
                    </span>
                </div>
                
                <!-- Widget List -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($availableWidgets as $widgetId => $widget)
                        @php
                            $isEnabled = in_array($widgetId, $enabledWidgets);
                            $requiredFeature = $widgetFeatureRequirements[$widgetId] ?? null;
                            $requiredFeatureLabel = $requiredFeature !== null
                                ? ($widgetFeatureLabels[$requiredFeature] ?? ucwords(str_replace('_', ' ', $requiredFeature)))
                                : null;
                            $isPageFeatureDisabled = $requiredFeature !== null && !($menuFeatures[$requiredFeature] ?? true);
                        @endphp
                        <div class="widget-item group flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-xl border transition-all"
                             :class="isEnabled('{{ $widgetId }}') ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                             data-widget="{{ $widgetId }}">
                            
                            <!-- Widget Icon -->
                            <div class="flex-shrink-0 p-2 rounded-lg transition-colors"
                                 :class="isEnabled('{{ $widgetId }}') ? 'bg-violet-100 dark:bg-violet-800' : 'bg-gray-100 dark:bg-gray-700'">
                                @switch($widget['icon'])
                                    @case('thermometer')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                        @break
                                    @case('calendar')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        @break
                                    @case('clock')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        @break
                                    @case('wind')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                                        </svg>
                                        @break
                                    @case('droplet')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                        </svg>
                                        @break
                                    @case('sun')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                        @break
                                    @case('moon')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                        </svg>
                                        @break
                                    @case('zap')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        @break
                                    @case('sparkles')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                        </svg>
                                        @break
                                    @case('stars')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                        </svg>
                                        @break
                                    @case('rocket')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                        </svg>
                                        @break
                                    @case('seedling')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19.5v-15m0 0a8.38 8.38 0 00-3.5.76m3.5-.76a8.38 8.38 0 013.5.76m.97 5.74a7.5 7.5 0 01-8.94 0"/>
                                        </svg>
                                        @break
                                    @case('battery')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2v-2m-7-8v2m0 8v2"/>
                                        </svg>
                                        @break
                                    @case('cloud')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                                        </svg>
                                        @break
                                    @case('ad')
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                        </svg>
                                        @break
                                    @default
                                        <svg class="w-5 h-5 transition-colors" :class="isEnabled('{{ $widgetId }}') ? 'text-violet-600 dark:text-violet-300' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                        </svg>
                                @endswitch
                            </div>
                            
                            <!-- Widget Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ $widget['label'] }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-snug">{{ $widget['description'] }}</p>
                                @if($isPageFeatureDisabled)
                                    <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400 leading-snug">
                                        {{ __('Linked page is disabled in Navigation settings (:feature).', ['feature' => $requiredFeatureLabel]) }}
                                        <a href="{{ route('admin.settings.group', 'navigation') }}" class="underline decoration-dotted hover:decoration-solid">{{ __('Manage') }}</a>
                                    </p>
                                @endif
                            </div>
                            
                            <!-- Toggle Switch -->
                            <div class="flex-shrink-0">
                                <button type="button" 
                                        @click="toggleWidget('{{ $widgetId }}')"
                                        :class="isEnabled('{{ $widgetId }}') ? 'bg-violet-600' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                                    <span class="sr-only">{{ __('Toggle') }} {{ $widget['label'] }}</span>
                                    <span :class="isEnabled('{{ $widgetId }}') ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                        <span :class="isEnabled('{{ $widgetId }}') ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                              class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span :class="isEnabled('{{ $widgetId }}') ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                              class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                                            <svg class="h-3 w-3 text-violet-600" fill="currentColor" viewBox="0 0 12 12">
                                                <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                            </svg>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <!-- Preview & Settings Sidebar -->
            <div class="space-y-6">
                <!-- Quick Templates -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Quick Templates') }}</h3>
                    
                    <!-- Basic Templates -->
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Basic') }}</p>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <button type="button" @click="applyTemplate('minimal')"
                                class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/partly-cloudy-day.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Minimal') }}
                        </button>
                        <button type="button" @click="applyTemplate('standard')"
                                class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/cloudy.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Standard') }}
                        </button>
                        <button type="button" @click="applyTemplate('complete')"
                                class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/clear-day.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Complete') }}
                        </button>
                        <button type="button" @click="applyTemplate('everything')"
                                class="px-3 py-2 text-sm bg-violet-100 dark:bg-violet-900/30 hover:bg-violet-200 dark:hover:bg-violet-800/40 text-violet-700 dark:text-violet-300 rounded-lg transition font-medium flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/star.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Everything') }}
                        </button>
                    </div>
                    
                    <!-- Themed Templates -->
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Themed') }}</p>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <button type="button" @click="applyTemplate('weather_pro')"
                                class="px-3 py-2 text-sm bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-800/30 text-blue-700 dark:text-blue-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/thunderstorms.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Weather Pro') }}
                        </button>
                        <button type="button" @click="applyTemplate('astronomy')"
                                class="px-3 py-2 text-sm bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-800/30 text-indigo-700 dark:text-indigo-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moon-waxing-crescent.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Astronomy') }}
                        </button>
                        <button type="button" @click="applyTemplate('environmental')"
                                class="px-3 py-2 text-sm bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-800/30 text-green-700 dark:text-green-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/humidity.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Environmental') }}
                        </button>
                        <button type="button" @click="applyTemplate('smart_home')"
                                class="px-3 py-2 text-sm bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-800/30 text-amber-700 dark:text-amber-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/thermometer.svg') }}" class="w-4 h-4" alt="">
                            {{ __('Smart Home') }}
                        </button>
                    </div>
                    
                    <!-- Sensor Templates -->
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Sensors') }}</p>
                    <div class="grid grid-cols-1 gap-2">
                        <button type="button" @click="applyTemplate('sensors')"
                                class="px-3 py-2 text-sm bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 dark:hover:bg-orange-800/30 text-orange-700 dark:text-orange-300 rounded-lg transition flex items-center gap-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/barometer.svg') }}" class="w-4 h-4" alt="">
                            {{ __('All Sensors (Indoor, Soil, PM2.5, CO2, Leak, Battery)') }}
                        </button>
                    </div>
                </div>

                <!-- Layout Options -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ __('Bottom row layout') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Affects the bottom row on the dashboard') }}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Bottom row columns') }}</label>
                            <div class="flex gap-2">
                                <button type="button" @click="gridCols = 3" 
                                        :class="gridCols == 3 ? 'bg-violet-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                        class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition">
                                    {{ __('3 Columns') }}
                                </button>
                                <button type="button" @click="gridCols = 4" 
                                        :class="gridCols == 4 ? 'bg-violet-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                        class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition">
                                    {{ __('4 Columns') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="grid_cols" :value="gridCols">
                </div>
                
                <!-- Rain Widget Visualization Style -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700" x-show="isEnabled('rain')">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Rain Visualization Style') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Choose an artistic visualization for the rain widget') }}</p>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="rainVisualization === 'ripple' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="rain_visualization" value="ripple" x-model="rainVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="3" opacity="0.9"/>
                                    <circle cx="12" cy="12" r="6" opacity="0.6"/>
                                    <circle cx="12" cy="12" r="9" opacity="0.3"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Ripple Pond') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Concentric ripples like raindrops hitting water') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="rainVisualization === 'mountain' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="rain_visualization" value="mountain" x-model="rainVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center overflow-hidden">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                                    <path d="M2 20L8 10L14 16L22 6" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 20H22V22H2V20Z" fill="rgba(59,130,246,0.5)"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Mountain Lake') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Water level rises against mountain silhouette') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="rainVisualization === 'tree' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="rain_visualization" value="tree" x-model="rainVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L8 8H10V10L7 10L12 18L17 10H14V8H16L12 2Z"/>
                                    <rect x="11" y="18" width="2" height="4" fill="currentColor" opacity="0.7"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Growing Tree') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Tree grows and flourishes with rainfall') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="rainVisualization === 'none' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="rain_visualization" value="none" x-model="rainVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="4" y="12" width="4" height="8" rx="1"/>
                                    <rect x="10" y="8" width="4" height="12" rx="1"/>
                                    <rect x="16" y="4" width="4" height="16" rx="1"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Classic Bars') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Standard precipitation bars only') }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Pressure Widget Visualization Style -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700" x-show="isEnabled('pressure')">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Pressure Visualization Style') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Choose an artistic visualization for the pressure widget') }}</p>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="pressureVisualization === 'sky' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="pressure_visualization" value="sky" x-model="pressureVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-400 to-amber-400 flex items-center justify-center overflow-hidden">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                                    <circle cx="18" cy="8" r="4" fill="rgba(251,191,36,0.9)"/>
                                    <ellipse cx="8" cy="14" rx="5" ry="3" fill="rgba(148,163,184,0.6)"/>
                                    <ellipse cx="6" cy="13" rx="3" ry="2" fill="rgba(148,163,184,0.7)"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Sky Scene') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dynamic sky with sun, clouds, and birds based on pressure') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="pressureVisualization === 'none' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="pressure_visualization" value="none" x-model="pressureVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="8"/>
                                    <path d="M12 8v4l2 2"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Gauge Only') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show only the pressure gauge without background') }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Wind Widget Visualization Style -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700" x-show="isEnabled('wind')">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Wind Visualization Style') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Choose an artistic visualization for the wind widget') }}</p>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="windVisualization === 'streams' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="wind_visualization" value="streams" x-model="windVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-400 to-cyan-400 flex items-center justify-center overflow-hidden">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12h20M8 8l-4 4 4 4M16 8l4 4-4 4"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Wind Streams') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Animated flow lines showing wind direction and intensity') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="windVisualization === 'particles' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="wind_visualization" value="particles" x-model="windVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-400 to-purple-400 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="8" cy="8" r="2"/>
                                    <circle cx="16" cy="12" r="2"/>
                                    <circle cx="12" cy="16" r="2"/>
                                    <circle cx="20" cy="8" r="1.5"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Particles') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Animated particles moving in the wind direction') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="windVisualization === 'sky' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="wind_visualization" value="sky" x-model="windVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-slate-400 to-blue-400 flex items-center justify-center overflow-hidden">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                                    <ellipse cx="8" cy="12" rx="5" ry="3" fill="rgba(148,163,184,0.6)"/>
                                    <ellipse cx="16" cy="10" rx="4" ry="2.5" fill="rgba(148,163,184,0.5)"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Sky Scene') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dynamic sky with clouds that move based on wind speed') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="windVisualization === 'none' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="wind_visualization" value="none" x-model="windVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="8"/>
                                    <path d="M12 4v8l4 4"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Compass Only') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show only the compass rose without background') }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Temperature Widget Visualization Style -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700" x-show="isEnabled('current')">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Temperature Visualization Style') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Choose an artistic visualization for the temperature widget') }}</p>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="tempVisualization === 'gradient' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="temp_visualization" value="gradient" x-model="tempVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-400 via-purple-400 to-orange-400 flex items-center justify-center overflow-hidden">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                                    <rect x="2" y="4" width="20" height="16" rx="2" fill="url(#tempGradPreview)"/>
                                    <defs>
                                        <linearGradient id="tempGradPreview" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" style="stop-color:#3b82f6"/>
                                            <stop offset="100%" style="stop-color:#f97316"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Weather Sky') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Realistic sky with sun, moon, and colors that follow weather and day/night cycle') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="tempVisualization === 'thermometer' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="temp_visualization" value="thermometer" x-model="tempVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 4v10.54a4 4 0 1 1-4 0V4M14 4h-4M14 4h2M12 4h-2"/>
                                    <circle cx="12" cy="18" r="3"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Thermometer Scene') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Artistic thermometer with gradient that changes based on temperature') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-600"
                               :class="tempVisualization === 'none' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600'">
                            <input type="radio" name="temp_visualization" value="none" x-model="tempVisualization" class="hidden">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v4M12 18v4M4 12h4M16 12h4"/>
                                    <circle cx="12" cy="12" r="4"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Classic Display') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show only the temperature without background') }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Temperature Chart Options (Hourly widget) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700" x-show="isEnabled('hourly')">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ __('Temperature chart options') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Optional enhancements for the 24h temperature chart') }}</p>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Show current time line') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Shows a vertical marker for the current time') }}</div>
                            </div>
                            <div x-data="{ on: @json($tempChartNowLine ?? true) }" class="flex items-center">
                                <input type="hidden" name="temp_chart_now_line" :value="on ? '1' : '0'">
                                <button type="button"
                                        @click="on = !on; hasChanges = true"
                                        :class="on ? 'bg-violet-600' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                                        role="switch"
                                        :aria-checked="on.toString()"
                                        :aria-label="on ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}'">
                                    <span :class="on ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-300 ease-in-out"></span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Show observed temperature') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Adds a station-measured line for the past 12 hours and shows forecast for the next 12 hours') }}</div>
                            </div>
                            <div x-data="{ on: @json($tempChartObserved ?? false) }" class="flex items-center">
                                <input type="hidden" name="temp_chart_observed" :value="on ? '1' : '0'">
                                <button type="button"
                                        @click="on = !on; hasChanges = true"
                                        :class="on ? 'bg-violet-600' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                                        role="switch"
                                        :aria-checked="on.toString()"
                                        :aria-label="on ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}'">
                                    <span :class="on ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-300 ease-in-out"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ad Configuration (only show if ads widget is enabled) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700" x-show="isEnabled('ads')">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Dashboard Advertisement Widget') }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Ad Company') }}</label>
                            <select name="ad_company" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                                <option value="">{{ __('None') }}</option>
                                <option value="google_adsense" {{ $adCompany === 'google_adsense' ? 'selected' : '' }}>Google AdSense</option>
                                <option value="media_net" {{ $adCompany === 'media_net' ? 'selected' : '' }}>Media.net</option>
                                <option value="propeller_ads" {{ $adCompany === 'propeller_ads' ? 'selected' : '' }}>PropellerAds</option>
                                <option value="adsterra" {{ $adCompany === 'adsterra' ? 'selected' : '' }}>Adsterra</option>
                                <option value="ezoic" {{ $adCompany === 'ezoic' ? 'selected' : '' }}>Ezoic</option>
                                <option value="custom" {{ $adCompany === 'custom' ? 'selected' : '' }}>{{ __('Custom Code') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Ad Code') }}</label>
                            <textarea name="ad_code" rows="4" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 font-mono text-xs" placeholder="{{ __('Paste your ad code here...') }}">{{ $adCode }}</textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Paste the HTML/JavaScript code from your ad network. This will be displayed in the ads widget on your dashboard.') }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Ad consent behavior') }}</label>
                            <select name="ads_consent_mode" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                                <option value="auto" {{ ($adsConsentMode ?? 'auto') === 'auto' ? 'selected' : '' }}>{{ __('Auto (EEA/UK/CH require consent first)') }}</option>
                                <option value="always_show_ads" {{ ($adsConsentMode ?? 'auto') === 'always_show_ads' ? 'selected' : '' }}>{{ __('Always show ads immediately') }}</option>
                                <option value="always_require_consent" {{ ($adsConsentMode ?? 'auto') === 'always_require_consent' ? 'selected' : '' }}>{{ __('Always require consent before ads') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Choose how ad consent is enforced for visitors. This setting can affect legal compliance in your region.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Inline Page Ad Units (non-dashboard pages) -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">{{ __('Inline Page Ad Unit Settings') }}</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ __('Enable inline page ad unit') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Shows a dedicated ad section on public pages (history, aviation, radar, etc.).') }}</div>
                            </div>
                            <div x-data="{ on: @json((bool) ($pageAdEnabled ?? false)) }" class="flex items-center">
                                <input type="hidden" name="page_ad_enabled" :value="on ? '1' : '0'">
                                <button type="button"
                                        @click="on = !on; hasChanges = true"
                                        :class="on ? 'bg-violet-600' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                                        role="switch"
                                        :aria-checked="on.toString()"
                                        :aria-label="on ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}'">
                                    <span :class="on ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-300 ease-in-out"></span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Ad Unit Type') }}</label>
                            <select name="page_ad_unit_type" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" @change="hasChanges = true">
                                <option value="display" {{ ($pageAdUnitType ?? 'display') === 'display' ? 'selected' : '' }}>{{ __('Display ads') }}</option>
                                <option value="in_feed" {{ ($pageAdUnitType ?? 'display') === 'in_feed' ? 'selected' : '' }}>{{ __('In-feed ads') }}</option>
                                <option value="in_article" {{ ($pageAdUnitType ?? 'display') === 'in_article' ? 'selected' : '' }}>{{ __('In-article ads') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Choose the ad unit type that matches the snippet you paste below.') }}</p>
                        </div>

                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Ad Company') }}</label>
                            <select name="page_ad_company" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" @change="hasChanges = true">
                                <option value="">{{ __('None') }}</option>
                                <option value="google_adsense" {{ ($pageAdCompany ?? '') === 'google_adsense' ? 'selected' : '' }}>Google AdSense</option>
                                <option value="media_net" {{ ($pageAdCompany ?? '') === 'media_net' ? 'selected' : '' }}>Media.net</option>
                                <option value="propeller_ads" {{ ($pageAdCompany ?? '') === 'propeller_ads' ? 'selected' : '' }}>PropellerAds</option>
                                <option value="adsterra" {{ ($pageAdCompany ?? '') === 'adsterra' ? 'selected' : '' }}>Adsterra</option>
                                <option value="ezoic" {{ ($pageAdCompany ?? '') === 'ezoic' ? 'selected' : '' }}>Ezoic</option>
                                <option value="custom" {{ ($pageAdCompany ?? '') === 'custom' ? 'selected' : '' }}>{{ __('Custom Code') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('Display Ad Unit Code') }}</label>
                            <textarea name="page_ad_code_display" rows="5" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 font-mono text-xs" placeholder="{{ __('Paste your display ad unit code here...') }}" @input="hasChanges = true">{{ $pageAdCodeDisplay ?? ($pageAdCode ?? '') }}</textarea>
                        </div>

                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('In-feed Ad Unit Code') }}</label>
                            <textarea name="page_ad_code_in_feed" rows="5" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 font-mono text-xs" placeholder="{{ __('Paste your in-feed ad unit code here...') }}" @input="hasChanges = true">{{ $pageAdCodeInFeed ?? '' }}</textarea>
                        </div>

                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-400 mb-2 block">{{ __('In-article Ad Unit Code') }}</label>
                            <textarea name="page_ad_code_in_article" rows="5" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 font-mono text-xs" placeholder="{{ __('Paste your in-article ad unit code here...') }}" @input="hasChanges = true">{{ $pageAdCodeInArticle ?? '' }}</textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('The selected Ad Unit Type above decides which snippet is rendered on public pages. Keep the global adsbygoogle.js script in Integrations (head code) so it loads once site-wide.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hidden inputs for form submission -->
        <template x-for="widgetId in enabledWidgets" :key="'input-' + widgetId">
            <input type="hidden" name="enabled_widgets[]" :value="widgetId">
        </template>
        
        <!-- Actions -->
        <div class="mt-8 flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-500 dark:text-gray-400" x-show="hasChanges">
                    {{ __('Unsaved changes') }}
                </span>
                <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg transition shadow-sm">
                    {{ __('Save Widget Configuration') }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const widgetLabels = @json($availableWidgets);
const initialEnabled = @json($enabledWidgets);
const initialGridCols = parseInt('{{ $layout['grid_cols'] ?? 3 }}', 10);
const initialRainVisualization = '{{ $rainVisualization ?? 'ripple' }}';
const initialPressureVisualization = '{{ $pressureVisualization ?? 'sky' }}';
const initialWindVisualization = '{{ $windVisualization ?? 'streams' }}';
const initialTempVisualization = '{{ $tempVisualization ?? 'gradient' }}';

const templates = {
    // Basic templates
    minimal: ['current', 'forecast', 'wind'],
    standard: ['current', 'forecast', 'hourly', 'wind', 'rain', 'sun', 'moon'],
    complete: ['current', 'forecast', 'hourly', 'wind', 'rain', 'sun', 'moon', 'airquality', 'uv', 'solar', 'pressure', 'metar', 'radar', 'webcam', 'lightning', 'indoor', 'alerts', 'earthquakes'],
    
    // All sensors - includes ALL available sensor widgets
    sensors: ['current', 'forecast', 'wind', 'rain', 'lightning', 'indoor', 'extra_temps', 'soil', 'pm25', 'co2', 'leak', 'battery', 'uv', 'solar', 'pressure'],
    
    // Themed templates
    astronomy: ['current', 'forecast', 'sun', 'moon', 'aurora', 'iss', 'uv', 'solar'],
    weather_pro: ['current', 'forecast', 'hourly', 'wind', 'rain', 'pressure', 'lightning', 'radar', 'metar', 'alerts'],
    environmental: ['current', 'airquality', 'pm25', 'co2', 'uv', 'solar', 'rain'],
    smart_home: ['current', 'indoor', 'extra_temps', 'soil', 'leak', 'battery', 'lightning', 'alerts'],
    
    // Everything - truly all widgets
    everything: Object.keys(widgetLabels)
};

function widgetManager() {
    return {
        enabledWidgets: [...initialEnabled],
        gridCols: initialGridCols,
        rainVisualization: initialRainVisualization,
        pressureVisualization: initialPressureVisualization,
        windVisualization: initialWindVisualization,
        tempVisualization: initialTempVisualization,
        hasChanges: false,
        
        get enabledCount() {
            return this.enabledWidgets.length;
        },
        
        init() {
            // No widget ordering here anymore; cards are reordered on the dashboard itself.
        },
        
        isEnabled(widgetId) {
            return this.enabledWidgets.includes(widgetId);
        },
        
        toggleWidget(widgetId) {
            if (this.isEnabled(widgetId)) {
                this.enabledWidgets = this.enabledWidgets.filter(id => id !== widgetId);
            } else {
                // Keep a stable order matching the visible list.
                const all = Object.keys(widgetLabels);
                const next = [...this.enabledWidgets, widgetId];
                next.sort((a, b) => all.indexOf(a) - all.indexOf(b));
                this.enabledWidgets = next;
            }
            this.hasChanges = true;
        },
        
        getWidgetLabel(widgetId) {
            return widgetLabels[widgetId]?.label || widgetId;
        },
        
        applyTemplate(templateName) {
            const template = templates[templateName];
            if (!template) return;
            
            this.enabledWidgets = [...template];
            this.hasChanges = true;
        }
    };
}
</script>
@endsection
