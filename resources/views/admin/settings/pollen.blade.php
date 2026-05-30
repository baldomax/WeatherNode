@extends('layouts.admin')

@section('title', __('Pollen Forecast Settings'))

@section('content')
@php
    use App\Models\Setting;
    $s = $settings->keyBy('key');

    $openMeteoEnabled = (bool) ($s->get('pollen.openmeteo_enabled')?->getCastedValue() ?? true);
    $googleEnabled    = (bool) ($s->get('pollen.google_enabled')?->getCastedValue() ?? false);
    $ambeeEnabled     = (bool) ($s->get('pollen.ambee_enabled')?->getCastedValue() ?? false);
    $cacheMinutes     = (int)  ($s->get('pollen.cache_minutes')?->getCastedValue() ?? 60);

    // Google key — show placeholder if already stored
    $googleKeyStored = !empty($s->get('pollen.google_api_key')?->value);
    $ambeeKeyStored  = !empty($s->get('pollen.ambee_api_key')?->value);
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                {{ __('Pollen Forecast') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Configure pollen data sources for the Air & Pollen page') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="text-gray-400 hover:text-white transition-colors">
            ← {{ __('Back to Settings') }}
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-lg border border-emerald-700/50 bg-emerald-900/30 px-4 py-3 text-emerald-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-700/50 bg-red-900/30 px-4 py-3 text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('admin.settings.update', 'pollen') }}" method="POST" class="space-y-6">
        @csrf

        {{-- ── Open-Meteo (free baseline) ──────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-green-900/60 text-green-400 text-xs rounded font-mono uppercase">Free</span>
                        {{ __('Open-Meteo Pollen') }}
                    </h2>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ __('Free global pollen forecast (alder, birch, grass, mugwort, olive, ragweed) — no API key required.') }}
                        <a href="https://open-meteo.com/en/docs/air-quality-api" target="_blank" class="text-blue-400 hover:underline ml-1">{{ __('Documentation') }} ↗</a>
                    </p>
                </div>
            </div>

            <label class="flex items-center gap-4 cursor-pointer mt-4">
                <input type="hidden" name="pollen_openmeteo_enabled" value="0">
                <div class="relative">
                    <input type="checkbox" name="pollen_openmeteo_enabled" value="1" id="pollen_openmeteo_enabled"
                           {{ $openMeteoEnabled ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-600 peer-checked:bg-green-600 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-green-400"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </div>
                <div>
                    <p class="font-medium text-white">{{ __('Enable Open-Meteo Pollen') }}</p>
                    <p class="text-xs text-gray-400">{{ __('Provides raw pollen counts (grains/m³) and risk classification. Recommended as always-on baseline.') }}</p>
                </div>
            </label>
        </div>

        {{-- ── Google Pollen API ────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-blue-900/60 text-blue-400 text-xs rounded font-mono uppercase">API Key</span>
                        {{ __('Google Pollen API') }}
                    </h2>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ __('Accurate daily risk index per category + per-plant breakdown. Requires a Google Maps Platform API key with the Pollen API enabled. Free tier available.') }}
                        <a href="https://developers.google.com/maps/documentation/pollen" target="_blank" class="text-blue-400 hover:underline ml-1">{{ __('Documentation') }} ↗</a>
                    </p>
                </div>
            </div>

            <div class="space-y-4 mt-4">
                <label class="flex items-center gap-4 cursor-pointer">
                    <input type="hidden" name="pollen_google_enabled" value="0">
                    <div class="relative">
                        <input type="checkbox" name="pollen_google_enabled" value="1" id="pollen_google_enabled"
                               {{ $googleEnabled ? 'checked' : '' }}
                               class="sr-only peer"
                               onchange="toggleSection('google-key-section', this.checked)">
                        <div class="w-11 h-6 bg-gray-600 peer-checked:bg-blue-600 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-blue-400"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="font-medium text-white">{{ __('Enable Google Pollen API') }}</p>
                        <p class="text-xs text-gray-400">{{ __('Adds plant-level breakdown and health recommendations.') }}</p>
                    </div>
                </label>

                <div id="google-key-section" class="{{ $googleEnabled ? '' : 'hidden' }} space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Google API Key') }}</label>
                        <input type="password"
                               name="pollen_google_api_key"
                               autocomplete="new-password"
                               placeholder="{{ $googleKeyStored ? __('(configured — enter new value to change)') : __('AIza...') }}"
                               class="w-full bg-gray-900 border border-gray-600 text-white text-sm rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 font-mono">
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __('Create a key at') }}
                            <a href="https://console.cloud.google.com/apis/library/pollen.googleapis.com" target="_blank" class="text-blue-400 hover:underline">console.cloud.google.com</a>
                            {{ __('and enable the "Pollen API".') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Ambee Pollen API ─────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-amber-900/60 text-amber-400 text-xs rounded font-mono uppercase">Paid</span>
                        {{ __('Ambee Pollen API') }}
                    </h2>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ __('Rich pollen counts, risk levels, and species breakdown for grass, tree, and weed categories. 120-hour (5-day) forecast. Paid subscription required.') }}
                        <a href="https://www.getambee.com" target="_blank" class="text-blue-400 hover:underline ml-1">{{ __('getambee.com') }} ↗</a>
                    </p>
                </div>
            </div>

            <div class="space-y-4 mt-4">
                <label class="flex items-center gap-4 cursor-pointer">
                    <input type="hidden" name="pollen_ambee_enabled" value="0">
                    <div class="relative">
                        <input type="checkbox" name="pollen_ambee_enabled" value="1" id="pollen_ambee_enabled"
                               {{ $ambeeEnabled ? 'checked' : '' }}
                               class="sr-only peer"
                               onchange="toggleSection('ambee-key-section', this.checked)">
                        <div class="w-11 h-6 bg-gray-600 peer-checked:bg-amber-600 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-amber-400"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="font-medium text-white">{{ __('Enable Ambee Pollen API') }}</p>
                        <p class="text-xs text-gray-400">{{ __('Highest-quality species-level data. Overrides risk levels from other sources.') }}</p>
                    </div>
                </label>

                <div id="ambee-key-section" class="{{ $ambeeEnabled ? '' : 'hidden' }} space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Ambee API Key') }}</label>
                        <input type="password"
                               name="pollen_ambee_api_key"
                               autocomplete="new-password"
                               placeholder="{{ $ambeeKeyStored ? __('(configured — enter new value to change)') : __('Your Ambee API key') }}"
                               class="w-full bg-gray-900 border border-gray-600 text-white text-sm rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500 font-mono">
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __('Sign up at') }}
                            <a href="https://www.getambee.com" target="_blank" class="text-blue-400 hover:underline">getambee.com</a>
                            {{ __('and find your key in the API dashboard.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Cache settings ───────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Cache & Polling') }}</h2>
            <p class="text-sm text-gray-400 mb-4">{{ __('Pollen data is polled by the weather:poll-external --source=pollen command and cached until the next poll. Pollen levels change slowly — hourly is usually sufficient.') }}</p>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Cache duration (minutes)') }}</label>
                <select name="pollen_cache_minutes"
                        class="bg-gray-900 border border-gray-600 text-white text-sm rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    @foreach([30 => '30 min', 60 => '60 min (recommended)', 120 => '2 hours', 240 => '4 hours'] as $val => $label)
                        <option value="{{ $val }}" {{ $cacheMinutes == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ── Data source comparison ───────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Source comparison') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-700">
                            <th class="py-2 pr-4">{{ __('Feature') }}</th>
                            <th class="py-2 px-4 text-green-400">Open-Meteo</th>
                            <th class="py-2 px-4 text-blue-400">Google</th>
                            <th class="py-2 px-4 text-amber-400">Ambee</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        @foreach([
                            ['Feature', 'Cost',                    'Free',          'Free tier / paid', 'Paid'],
                            ['Feature', 'Coverage',                'Global',        'Global',            'Global (no S.Am.)'],
                            ['Feature', 'Plant types',             '6 species',     '3 categories + plants', '3 categories + species'],
                            ['Feature', 'Risk levels',             'Calculated',    'UPI 0–5',           'Low–Very High'],
                            ['Feature', 'Raw counts',              'grains/m³ ✓',   '–',                 'count ✓'],
                            ['Feature', 'Species breakdown',       '–',             'Plant names ✓',     'Full ✓'],
                            ['Feature', 'Health advice',           '–',             '✓',                 '–'],
                            ['Feature', 'Forecast days',           '5 days',        '5 days',            '5 days'],
                            ['Feature', 'Update frequency',        'Hourly',        'Daily',             'Hourly'],
                        ] as [$_, $feature, $om, $gg, $am])
                        <tr class="text-gray-300">
                            <td class="py-2 pr-4 text-gray-400">{{ $feature }}</td>
                            <td class="py-2 px-4">{{ $om }}</td>
                            <td class="py-2 px-4">{{ $gg }}</td>
                            <td class="py-2 px-4">{{ $am }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                {{ __('When multiple sources are enabled, the highest-priority source wins for risk levels: Ambee > Google > Open-Meteo.') }}
            </p>
        </div>

        {{-- ── Save ─────────────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('pollen') }}" target="_blank"
               class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors border border-gray-600">
                {{ __('View pollen page') }} ↗
            </a>

            <button type="submit"
                    class="px-5 py-2 bg-green-600 hover:bg-green-500 text-white font-medium rounded-lg transition-colors">
                {{ __('Save') }}
            </button>
        </div>
    </form>

</div>

<script>
function toggleSection(id, show) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden', !show);
}
</script>
@endsection
