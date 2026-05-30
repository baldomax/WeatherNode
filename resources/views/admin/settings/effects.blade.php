@extends('layouts.admin')

@section('title', __('Weather Effects'))

@php
    $activeUnits = $activeUnits ?? 'metric';
@endphp

@section('content')
<div x-data="effectsManager()" class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/star.svg') }}" class="w-8 h-8" alt="">
                {{ __('Weather Visual Effects') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Configure animated weather effects on the dashboard') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="text-gray-400 hover:text-white transition-colors">
            ← {{ __('Back to Settings') }}
        </a>
    </div>

    <form action="{{ route('admin.settings.effects.update') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- Global Controls -->
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Global Controls') }}</h2>
            
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Master Toggle -->
                <div class="flex items-center justify-between p-4 bg-gray-900/50 rounded-lg">
                    <div>
                        <h3 class="font-medium text-white">{{ __('Enable All Effects') }}</h3>
                        <p class="text-sm text-gray-400">{{ __('Master switch for all weather animations') }}</p>
                    </div>
                    <button type="button" 
                            @click="globalEnabled = !globalEnabled"
                            :class="globalEnabled ? 'bg-emerald-500' : 'bg-gray-600'"
                            class="relative w-14 h-7 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                        <span :class="globalEnabled ? 'translate-x-7' : 'translate-x-1'"
                              class="absolute top-0.5 left-0 w-6 h-6 bg-white rounded-full shadow transition-transform duration-200"></span>
                    </button>
                    <input type="hidden" name="effects_enabled" :value="globalEnabled ? '1' : '0'">
                </div>

                <!-- Test Mode -->
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-amber-900/30 to-orange-900/30 rounded-lg border border-amber-700/50">
                    <div>
                        <h3 class="font-medium text-amber-300">🧪 {{ __('Test Mode') }}</h3>
                        <p class="text-sm text-amber-200/70">{{ __('Preview effects regardless of weather') }}</p>
                    </div>
                    <button type="button" 
                            @click="testMode = !testMode"
                            :class="testMode ? 'bg-amber-500' : 'bg-gray-600'"
                            class="relative w-14 h-7 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                        <span :class="testMode ? 'translate-x-7' : 'translate-x-1'"
                              class="absolute top-0.5 left-0 w-6 h-6 bg-white rounded-full shadow transition-transform duration-200"></span>
                    </button>
                    <input type="hidden" name="test_mode" :value="testMode ? '1' : '0'">
                </div>
            </div>

            <!-- Test Effect Selector (shown when test mode is on) -->
            <div x-show="testMode" x-transition class="mt-4 p-4 bg-amber-900/20 rounded-lg border border-amber-700/30">
                <label class="block text-sm font-medium text-amber-200 mb-2">{{ __('Effect to Preview') }}</label>
                <select name="test_effect" x-model="testEffect"
                        class="bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2 w-full md:w-auto">
                    <option value="rain">{{ __('Rain') }}</option>
                    <option value="snow">{{ __('Snow') }}</option>
                    <option value="wind">{{ __('Wind') }}</option>
                    <option value="lightning">{{ __('Lightning') }}</option>
                    <option value="sun">{{ __('Sun Rays') }}</option>
                    <option value="fog">{{ __('Fog') }}</option>
                    <option value="all">{{ __('All Effects') }}</option>
                </select>
                <p class="text-xs text-amber-200/60 mt-2">{{ __('Visit the dashboard to see the selected effect in action') }}</p>
            </div>
        </div>

        <!-- Individual Effects -->
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Individual Effects') }}</h2>
            
            <div class="grid gap-4">
                @foreach($effectSettings as $key => $effect)
                <div class="bg-gray-900/50 rounded-lg p-4 border border-gray-700/50 hover:border-gray-600 transition-colors"
                     x-data="{ 
                         effectEnabled: {{ $effect['enabled'] ? 'true' : 'false' }},
                         @if($key === 'rain')
                         rainShowForecast: {{ ($effect['show_forecast'] ?? true) ? 'true' : 'false' }},
                         rainThresholdType: '{{ $effect['forecast_threshold_type'] ?? 'absolute' }}',
                         @endif
                     }"
                     :class="{ 'opacity-50': !globalEnabled }">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            @if(isset($effect['svg_icon']))
	                                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/' . $effect['svg_icon'] . '.svg') }}" class="w-10 h-10" alt="{{ $effect['label'] }}">
                            @else
                                <span class="text-3xl">{{ $effect['icon'] }}</span>
                            @endif
                            <div>
                                <h3 class="font-medium text-white">{{ $effect['label'] }}</h3>
                                <p class="text-sm text-gray-400">{{ $effect['description'] }}</p>
                            </div>
                        </div>
                        <button type="button"
                                @click="if(globalEnabled) effectEnabled = !effectEnabled"
                                :class="effectEnabled ? 'bg-cyan-500' : 'bg-gray-600'"
                                x-bind:disabled="!globalEnabled"
                                class="relative w-12 h-6 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:cursor-not-allowed">
                            <span :class="effectEnabled ? 'translate-x-6' : 'translate-x-1'"
                                  class="absolute top-0.5 left-0 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"></span>
                        </button>
                        <input type="hidden" name="{{ $key }}_enabled" :value="effectEnabled ? '1' : '0'">
                    </div>

                    @if(isset($effect['intensity']))
                    <div class="mt-4 pt-4 border-t border-gray-700/50">
                        <label class="block text-sm text-gray-400 mb-2">{{ __('Intensity') }}</label>
                        <div class="flex items-center gap-4">
                            <input type="range" name="{{ $key }}_intensity" 
                                   min="10" max="100" step="10" value="{{ $effect['intensity'] }}"
                                   class="flex-1 h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-cyan-500"
                                   x-bind:disabled="!globalEnabled">
                            <span class="text-sm text-gray-300 w-12 text-right">{{ $effect['intensity'] }}%</span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>{{ __('Light') }}</span>
                            <span>{{ __('Heavy') }}</span>
                        </div>
                    </div>
                    @endif

                    @if($key === 'rain')
                        @if(isset($effect['splash_on_cards']))
                        <div class="mt-4 pt-4 border-t border-gray-700/50">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="rain_splash_on_cards" 
                                       class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-cyan-500 focus:ring-cyan-500"
                                       {{ $effect['splash_on_cards'] ? 'checked' : '' }} x-bind:disabled="!globalEnabled">
                                <span class="text-sm text-gray-300">{{ __('Show splash effects on cards') }}</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1 ml-7">{{ __('Creates subtle water splash animations when rain hits card surfaces') }}</p>
                        </div>
                        @endif
                        
                        <div class="mt-4 pt-4 border-t border-gray-700/50 space-y-4">
                            <div>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="rain_show_forecast" 
                                           x-model="rainShowForecast"
                                           class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-cyan-500 focus:ring-cyan-500"
                                           {{ ($effect['show_forecast'] ?? true) ? 'checked' : '' }} x-bind:disabled="!globalEnabled">
                                    <span class="text-sm text-gray-300">{{ __('Show rain from forecast') }}</span>
                                </label>
                                <p class="text-xs text-gray-500 mt-1 ml-7">{{ __('Enable rain effect when forecast predicts rain, even if not currently raining') }}</p>
                            </div>
                            
                            <div x-show="rainShowForecast" 
                                 x-transition
                                 class="ml-7 space-y-3">
                                <div>
                                    <label class="block text-sm text-gray-400 mb-2">{{ __('Forecast Threshold Type') }}</label>
                                    <select name="rain_forecast_threshold_type" 
                                            x-model="rainThresholdType"
                                            class="bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2 w-full md:w-auto"
                                            x-bind:disabled="!globalEnabled">
                                        <option value="absolute">{{ __('Absolute (mm)') }}</option>
                                        <option value="percentage">{{ __('Percentage (%)') }}</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">{{ __('Choose how to measure forecast precipitation') }}</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm text-gray-400 mb-2">
                                        <span x-text="rainThresholdType === 'percentage' ? '{{ __('Precipitation Probability Threshold (%)') }}' : '{{ __('Precipitation Amount Threshold (mm)') }}'"></span>
                                    </label>
                                    <div class="flex items-center gap-4">
                                        <input type="number" 
                                               name="rain_forecast_threshold_value" 
                                               min="0" 
                                               :step="rainThresholdType === 'percentage' ? '1' : '0.1'"
                                               value="{{ $effect['forecast_threshold_value'] ?? 0.5 }}"
                                               class="bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2 w-32"
                                               x-bind:disabled="!globalEnabled">
                                        <span class="text-sm text-gray-400">
                                            <span x-text="rainThresholdType === 'percentage' ? '%' : 'mm'"></span>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <span x-text="rainThresholdType === 'percentage' ? '{{ __('Only show rain when forecast probability exceeds this percentage') }}' : '{{ __('Only show rain when forecast precipitation exceeds this amount') }}'"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- Effect Triggers Info -->
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('When Effects Trigger') }}</h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div class="p-3 bg-gray-900/50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span>🌧️</span>
                        <span class="font-medium text-gray-200">{{ __('Rain') }}</span>
                    </div>
                    <p class="text-gray-400">
                        {{ __('Rain rate') }} &gt; {{ $unit->rainRate(0, $activeUnits) }}<br>
                        <span x-show="true">{{ __('or forecast (if enabled)') }}</span>
                    </p>
                </div>
                <div class="p-3 bg-gray-900/50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span>❄️</span>
                        <span class="font-medium text-gray-200">{{ __('Snow') }}</span>
                    </div>
                    <p class="text-gray-400">{{ __('Temperature') }} &lt; {{ $unit->temperature(2, $activeUnits) }}<br>{{ __('and precipitation forecast') }}</p>
                </div>
                <div class="p-3 bg-gray-900/50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span>💨</span>
                        <span class="font-medium text-gray-200">{{ __('Wind') }}</span>
                    </div>
                    <p class="text-gray-400">{{ __('Wind speed') }} &gt; {{ $unit->wind(20, $activeUnits) }}<br>{{ __('or gusts') }} &gt; {{ $unit->wind(30, $activeUnits) }}</p>
                </div>
                <div class="p-3 bg-gray-900/50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span>⚡</span>
                        <span class="font-medium text-gray-200">{{ __('Lightning') }}</span>
                    </div>
                    <p class="text-gray-400">{{ __('Thunderstorm in forecast') }}<br>{{ __('or lightning detected') }}</p>
                </div>
                <div class="p-3 bg-gray-900/50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span>☀️</span>
                        <span class="font-medium text-gray-200">{{ __('Sun Rays') }}</span>
                    </div>
                    <p class="text-gray-400">{{ __('Solar radiation') }} &gt; 200 W/m²<br>{{ __('and not raining') }}</p>
                </div>
                <div class="p-3 bg-gray-900/50 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <span>🌫️</span>
                        <span class="font-medium text-gray-200">{{ __('Fog') }}</span>
                    </div>
                    <p class="text-gray-400">{{ __('Humidity') }} ≥ 98%<br>{{ __('or visibility reported low') }}</p>
                </div>
            </div>
        </div>

        <!-- Performance Note -->
        <div class="bg-blue-900/30 rounded-xl border border-blue-700/50 p-4">
            <div class="flex items-start gap-3">
                <span class="text-2xl">💡</span>
                <div>
                    <h3 class="font-medium text-blue-200">{{ __('Performance Note') }}</h3>
                    <p class="text-sm text-blue-200/70 mt-1">
                        {{ __('All effects use CSS animations and are GPU-accelerated for minimal CPU impact.') }}
                        {{ __('On older devices, you may want to reduce intensity or disable some effects.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-400 hover:to-blue-400 
                           text-white font-medium rounded-lg transition-all transform hover:scale-105 
                           flex items-center gap-2 shadow-lg shadow-cyan-500/30">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ __('Save Effect Settings') }}
            </button>
        </div>
    </form>

    <!-- Live Preview Section -->
    <div class="bg-gray-800/50 rounded-xl border border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <span>👁️</span>
                {{ __('Live Preview') }}
            </h2>
            <div class="flex gap-2">
                <button type="button" @click="previewEffect = 'rain'" 
                        :class="previewEffect === 'rain' ? 'bg-cyan-600' : 'bg-gray-700 hover:bg-gray-600'"
                        class="px-3 py-1 text-sm rounded transition-colors">🌧️ {{ __('Rain') }}</button>
                <button type="button" @click="previewEffect = 'snow'" 
                        :class="previewEffect === 'snow' ? 'bg-cyan-600' : 'bg-gray-700 hover:bg-gray-600'"
                        class="px-3 py-1 text-sm rounded transition-colors">❄️ {{ __('Snow') }}</button>
                <button type="button" @click="previewEffect = 'wind'" 
                        :class="previewEffect === 'wind' ? 'bg-cyan-600' : 'bg-gray-700 hover:bg-gray-600'"
                        class="px-3 py-1 text-sm rounded transition-colors">💨 {{ __('Wind') }}</button>
                <button type="button" @click="triggerLightning()" 
                        class="px-3 py-1 text-sm bg-yellow-600 hover:bg-yellow-500 rounded transition-colors">⚡ {{ __('Flash') }}</button>
                <button type="button" @click="previewEffect = 'fog'" 
                        :class="previewEffect === 'fog' ? 'bg-cyan-600' : 'bg-gray-700 hover:bg-gray-600'"
                        class="px-3 py-1 text-sm rounded transition-colors">🌫️ {{ __('Fog') }}</button>
                <button type="button" @click="previewEffect = null" 
                        class="px-3 py-1 text-sm bg-gray-700 hover:bg-gray-600 rounded transition-colors">✕ {{ __('None') }}</button>
            </div>
        </div>
        
        <div class="relative h-64 bg-gradient-to-br from-gray-900 via-slate-800 to-gray-900 overflow-hidden" 
             x-ref="previewContainer">
            
            <!-- Sun rays preview -->
            <div x-show="previewEffect === 'sun'" x-transition
                 class="absolute -top-20 -right-20 w-64 h-64 opacity-30">
                <div class="w-full h-full bg-gradient-radial from-amber-400/50 via-orange-300/20 to-transparent rounded-full animate-pulse"></div>
            </div>

            <!-- Fog preview -->
            <div x-show="previewEffect === 'fog'" x-transition
                 class="absolute inset-0 bg-gradient-to-t from-gray-400/30 via-gray-300/20 to-transparent animate-pulse"></div>

            <!-- Lightning flash -->
            <div x-show="lightningFlash" x-transition.opacity.duration.100ms
                 class="absolute inset-0 bg-white/80 z-50"></div>

            <!-- Preview cards -->
            <div class="absolute inset-4 flex gap-4 items-center justify-center">
                <div class="bg-gray-800/80 backdrop-blur-sm rounded-lg p-4 border border-gray-600/50 w-40">
                    <div class="text-xs text-gray-400 mb-1">{{ __('Temperature') }}</div>
                    <div class="text-2xl font-bold text-white">{{ $unit->temperature(12.5, $activeUnits) }}</div>
                </div>
                <div class="bg-gray-800/80 backdrop-blur-sm rounded-lg p-4 border border-gray-600/50 w-40">
                    <div class="text-xs text-gray-400 mb-1">{{ __('Wind') }}</div>
                    <div class="text-2xl font-bold text-white">{{ $unit->wind(15, $activeUnits) }}</div>
                </div>
                <div class="bg-gray-800/80 backdrop-blur-sm rounded-lg p-4 border border-gray-600/50 w-40">
                    <div class="text-xs text-gray-400 mb-1">{{ __('Humidity') }}</div>
                    <div class="text-2xl font-bold text-white">78%</div>
                </div>
            </div>

            <!-- Effect containers will be populated by JS -->
            <div x-ref="rainPreview" class="absolute inset-0 pointer-events-none overflow-hidden"></div>
            <div x-ref="snowPreview" class="absolute inset-0 pointer-events-none overflow-hidden"></div>
            <div x-ref="windPreview" class="absolute inset-0 pointer-events-none overflow-hidden"></div>
        </div>
    </div>
</div>

<style>
    /* Rain effect */
    .preview-raindrop {
        position: absolute;
        width: 2px;
        background: linear-gradient(to bottom, transparent, rgba(174, 194, 224, 0.6), rgba(174, 194, 224, 0.9));
        border-radius: 0 0 2px 2px;
        animation: previewFall linear infinite;
    }
    
    @keyframes previewFall {
        0% { transform: translateY(-20px); opacity: 0; }
        10% { opacity: 1; }
        100% { transform: translateY(280px); opacity: 0; }
    }

    /* Snow effect */
    .preview-snowflake {
        position: absolute;
        background: white;
        border-radius: 50%;
        animation: previewSnowfall linear infinite;
        box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
    }
    
    @keyframes previewSnowfall {
        0% { transform: translateY(-10px) rotate(0deg); opacity: 0; }
        10% { opacity: 0.9; }
        100% { transform: translateY(280px) rotate(360deg); opacity: 0; }
    }

    /* Wind effect */
    .preview-wind {
        position: absolute;
        height: 1px;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.3), transparent);
        animation: previewWindBlow linear infinite;
    }
    
    @keyframes previewWindBlow {
        0% { transform: translateX(-100px); opacity: 0; }
        50% { opacity: 1; }
        100% { transform: translateX(500px); opacity: 0; }
    }

    /* Radial gradient for sun */
    .bg-gradient-radial {
        background: radial-gradient(circle, var(--tw-gradient-from) 0%, var(--tw-gradient-via) 50%, var(--tw-gradient-to) 100%);
    }
</style>

<script>
function effectsManager() {
    return {
        globalEnabled: {{ $globalEnabled ? 'true' : 'false' }},
        testMode: {{ $testMode ? 'true' : 'false' }},
        testEffect: '{{ $testEffect }}',
        previewEffect: null,
        lightningFlash: false,
        intervals: [],

        init() {
            this.$watch('previewEffect', (value) => {
                this.clearAllPreviews();
                if (value === 'rain') this.startRainPreview();
                if (value === 'snow') this.startSnowPreview();
                if (value === 'wind') this.startWindPreview();
            });
        },

        clearAllPreviews() {
            this.intervals.forEach(i => clearInterval(i));
            this.intervals = [];
            if (this.$refs.rainPreview) this.$refs.rainPreview.innerHTML = '';
            if (this.$refs.snowPreview) this.$refs.snowPreview.innerHTML = '';
            if (this.$refs.windPreview) this.$refs.windPreview.innerHTML = '';
        },

        startRainPreview() {
            const container = this.$refs.rainPreview;
            const interval = setInterval(() => {
                const drop = document.createElement('div');
                drop.className = 'preview-raindrop';
                drop.style.left = Math.random() * 100 + '%';
                drop.style.height = (15 + Math.random() * 15) + 'px';
                drop.style.animationDuration = (0.5 + Math.random() * 0.3) + 's';
                container.appendChild(drop);
                setTimeout(() => drop.remove(), 1000);
            }, 30);
            this.intervals.push(interval);
        },

        startSnowPreview() {
            const container = this.$refs.snowPreview;
            const interval = setInterval(() => {
                const flake = document.createElement('div');
                flake.className = 'preview-snowflake';
                flake.style.left = Math.random() * 100 + '%';
                flake.style.width = flake.style.height = (3 + Math.random() * 4) + 'px';
                flake.style.animationDuration = (3 + Math.random() * 2) + 's';
                container.appendChild(flake);
                setTimeout(() => flake.remove(), 5000);
            }, 100);
            this.intervals.push(interval);
        },

        startWindPreview() {
            const container = this.$refs.windPreview;
            const interval = setInterval(() => {
                const streak = document.createElement('div');
                streak.className = 'preview-wind';
                streak.style.top = Math.random() * 100 + '%';
                streak.style.width = (50 + Math.random() * 100) + 'px';
                streak.style.animationDuration = (0.8 + Math.random() * 0.5) + 's';
                container.appendChild(streak);
                setTimeout(() => streak.remove(), 1500);
            }, 80);
            this.intervals.push(interval);
        },

        triggerLightning() {
            this.lightningFlash = true;
            setTimeout(() => this.lightningFlash = false, 100);
            setTimeout(() => {
                this.lightningFlash = true;
                setTimeout(() => this.lightningFlash = false, 50);
            }, 150);
        }
    }
}
</script>
@endsection
