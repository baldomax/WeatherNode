@extends('layouts.admin')

@section('title', __('Satellite Imagery'))

@section('content')
@php
    $enabled = (bool) \App\Models\Setting::getValue('satellite.enabled', true);
    $provider = \App\Models\Setting::getValue('satellite.provider', 'knmi');
    $displayRegion = \App\Models\Setting::getValue('satellite.display_region', 'europe');
    $nasaMode = \App\Models\Setting::getValue('satellite.nasa.mode', 'nrt');
    $nasaImagery = \App\Models\Setting::getValue('satellite.nasa.imagery', 'truecolor');
    $all = [
        'knmi' => [
            'europe_url' => \App\Models\Setting::getValue('satellite.sources.knmi.europe_url', ''),
            'world_url' => \App\Models\Setting::getValue('satellite.sources.knmi.world_url', ''),
            'zoom' => (int) \App\Models\Setting::getValue('satellite.sources.knmi.zoom', 4),
        ],
        'nasa' => [
            'europe_url' => \App\Models\Setting::getValue('satellite.sources.nasa.europe_url', ''),
            'world_url' => \App\Models\Setting::getValue('satellite.sources.nasa.world_url', ''),
            'zoom' => (int) \App\Models\Setting::getValue('satellite.sources.nasa.zoom', 4),
        ],
        'custom' => [
            'europe_url' => \App\Models\Setting::getValue('satellite.sources.custom.europe_url', ''),
            'world_url' => \App\Models\Setting::getValue('satellite.sources.custom.world_url', ''),
            'zoom' => (int) \App\Models\Setting::getValue('satellite.sources.custom.zoom', 4),
        ],
    ];

    $current = $all[$provider] ?? $all['knmi'];
@endphp

<div class="w-full">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Satellite Imagery') }}</li>
        </ol>
    </nav>

    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-cyan-100 dark:bg-cyan-900/30">
                <svg class="w-8 h-8 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A8 8 0 1110.745 3H12a8 8 0 009 10.255z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Satellite Imagery') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure satellite imagery sources and URLs per provider.') }}</p>
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

    <form action="{{ route('admin.settings.update', 'satellite') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            <div class="p-5">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Enabled') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Enable satellite imagery display') }}</p>
                    <div class="w-full">
                        <x-toggle-switch
                            :enabled="$enabled"
                            name="satellite_enabled"
                            :labelEnabled="__('Enabled')"
                            :labelDisabled="__('Disabled')"
                        />
                    </div>
                </div>
            </div>

            <div class="p-5">
                <div class="space-y-2">
                    <label for="satellite_provider" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Provider') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Choose a provider first; the corresponding fields will appear below.') }}</p>
                    <select name="satellite_provider"
                            id="satellite_provider"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                        <option value="knmi" {{ $provider === 'knmi' ? 'selected' : '' }}>{{ __('KNMI (Local)') }}</option>
                        <option value="nasa" {{ $provider === 'nasa' ? 'selected' : '' }}>{{ __('NASA Worldview (Worldwide)') }}</option>
                        <option value="custom" {{ $provider === 'custom' ? 'selected' : '' }}>{{ __('Custom URL') }}</option>
                    </select>
                </div>
            </div>

            <div class="p-5">
                <div class="space-y-2">
                    <label for="satellite_display_region" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Radar satellite view') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Which satellite layer to show on /radar.') }}</p>
                    <select name="satellite_display_region"
                            id="satellite_display_region"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                        <option value="europe" {{ $displayRegion === 'europe' ? 'selected' : '' }}>{{ __('Local') }}</option>
                        <option value="world" {{ $displayRegion === 'world' ? 'selected' : '' }}>{{ __('Worldwide') }}</option>
                    </select>
                </div>
            </div>

            {{-- NASA options (only relevant when provider = NASA) --}}
            <div class="p-5" id="satellite-nasa-options">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="satellite_nasa_mode" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('NASA mode') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Near real-time is more recent; daily mosaic is more complete.') }}</p>
                        <select name="satellite_nasa_mode"
                                id="satellite_nasa_mode"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            <option value="nrt" {{ $nasaMode === 'nrt' ? 'selected' : '' }}>{{ __('Near real-time') }}</option>
                            <option value="daily" {{ $nasaMode === 'daily' ? 'selected' : '' }}>{{ __('Daily mosaic') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="satellite_nasa_imagery" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('NASA imagery') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Choose true color or infrared.') }}</p>
                        <select name="satellite_nasa_imagery"
                                id="satellite_nasa_imagery"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            <option value="truecolor" {{ $nasaImagery === 'truecolor' ? 'selected' : '' }}>{{ __('True color') }}</option>
                            <option value="infrared" {{ $nasaImagery === 'infrared' ? 'selected' : '' }}>{{ __('Infrared (thermal)') }}</option>
                        </select>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Changing these options will update the NASA provider URLs below.') }}
                </p>
            </div>

            <div class="p-5" id="satellite-field-europe">
                <div class="space-y-2">
                    <label for="satellite_ui_europe_url" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Local URL') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        {{ __('Image URL (jpg/png/gif) or tile URL template (must include {z}/{y}/{x}).') }}
                    </p>
                    <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                        <div class="font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Tip') }}</div>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>{{ __('For NASA GIBS WMTS tiles, the path order is {z}/{y}/{x} (TileMatrix/TileRow/TileCol).') }}</li>
                            <li>{{ __('If the map looks like the wrong part of the world, check Station Info latitude/longitude and the tile order.') }}</li>
                        </ul>
                    </div>
                    <input type="text"
                           name="satellite_ui_europe_url"
                           id="satellite_ui_europe_url"
                           value="{{ $current['europe_url'] }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                           placeholder="https://.../tile/{z}/{y}/{x}.jpg or https://.../image.jpg" />
                </div>
            </div>

            <div class="p-5" id="satellite-field-world">
                <div class="space-y-2">
                    <label for="satellite_ui_world_url" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Worldwide URL') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        {{ __('Tile URL template (must include {z}/{y}/{x}). You may also use {time} (YYYY-MM-DD), {time_today}, {time_yesterday}, {time_auto}, or {datetime} (UTC ISO timestamp).') }}
                    </p>
                    <input type="text"
                           name="satellite_ui_world_url"
                           id="satellite_ui_world_url"
                           value="{{ $current['world_url'] }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                           placeholder="https://.../{time}/.../{z}/{y}/{x}.jpg" />
                </div>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4" id="satellite-field-zoom">
                    <div>
                        <label for="satellite_zoom" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Zoom') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Map zoom level') }}</p>
                        <input type="number"
                               name="satellite_ui_zoom"
                               id="satellite_zoom"
                               value="{{ $current['zoom'] }}"
                               min="1"
                               max="12"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400" />
                    </div>
                </div>
            </div>
        </div>

        {{-- KNMI WMS Layers --}}
        @php
            $wmsEnabled = \App\Models\Setting::getValue('satellite.wms_enabled', false);
            $wmsDefaultLayer = \App\Models\Setting::getValue('satellite.wms_default_layer', 'lwe_precipitation_rate');
            $wmsDefaultStyle = \App\Models\Setting::getValue('satellite.wms_default_style', 'precip-rainbow/nearest');
            $wmsDefaultOpacity = \App\Models\Setting::getValue('satellite.wms_default_opacity', 70);
            $wmsAnimationSpeed = \App\Models\Setting::getValue('satellite.wms_animation_speed', 0.5);
            $wmsAutoRefresh = \App\Models\Setting::getValue('satellite.wms_auto_refresh', 15);
            $isInNetherlands = \App\Models\Setting::isStationInNetherlands();
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700 mt-6">
            <div class="p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('KNMI WMS Satellite Layers') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    {{ __('Interactive satellite-derived weather data overlays from KNMI (Netherlands). Includes cloud top height, precipitation rate, cloud phase, solar radiation, and more with time-lapse animations.') }}
                </p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-4">
                    {{ __('Note: This provides 7 days of historical satellite observations, not forecasts.') }}
                </p>
                
                @if(!$isInNetherlands)
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

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('Enable WMS Layers') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ __('Show interactive WMS satellite layers on radar page') }}
                            </p>
                        </div>
                        <x-toggle-switch
                            :enabled="$wmsEnabled"
                            name="satellite_wms_enabled"
                            :labelEnabled="__('Enabled')"
                            :labelDisabled="__('Disabled')"
                        />
                    </div>

                    @if($wmsEnabled)
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
                            <div>
                                <label for="satellite_wms_default_layer" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    {{ __('Default Layer') }}
                                </label>
                                <select name="satellite_wms_default_layer" 
                                        id="satellite_wms_default_layer"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    <option value="lwe_precipitation_rate" {{ $wmsDefaultLayer === 'lwe_precipitation_rate' ? 'selected' : '' }}>Precipitation Rate</option>
                                    <option value="height_at_cloud_top" {{ $wmsDefaultLayer === 'height_at_cloud_top' ? 'selected' : '' }}>Cloud Top Height</option>
                                    <option value="air_temperature_at_cloud_top" {{ $wmsDefaultLayer === 'air_temperature_at_cloud_top' ? 'selected' : '' }}>Cloud Top Temperature</option>
                                    <option value="surface_downwelling_shortwave_flux_in_air" {{ $wmsDefaultLayer === 'surface_downwelling_shortwave_flux_in_air' ? 'selected' : '' }}>Solar Radiation</option>
                                    <option value="thermodynamic_phase_of_cloud_water_particles_at_cloud_top_defined_by_near_infrared_radiance" {{ $wmsDefaultLayer === 'thermodynamic_phase_of_cloud_water_particles_at_cloud_top_defined_by_near_infrared_radiance' ? 'selected' : '' }}>Cloud Phase</option>
                                    <option value="cloud_area_fraction" {{ $wmsDefaultLayer === 'cloud_area_fraction' ? 'selected' : '' }}>Cloud Cover</option>
                                </select>
                            </div>

                            <div>
                                <label for="satellite_wms_default_style" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    {{ __('Default Style') }}
                                </label>
                                <input type="text" 
                                       name="satellite_wms_default_style" 
                                       id="satellite_wms_default_style"
                                       value="{{ $wmsDefaultStyle }}"
                                       placeholder="precip-rainbow/nearest"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('Style name for the selected layer (e.g., precip-rainbow/nearest for precipitation)') }}
                                </p>
                            </div>

                            <div>
                                <label for="satellite_wms_default_opacity" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    {{ __('Default Opacity') }} (%)
                                </label>
                                <input type="number" 
                                       name="satellite_wms_default_opacity" 
                                       id="satellite_wms_default_opacity"
                                       value="{{ $wmsDefaultOpacity }}"
                                       min="0"
                                       max="100"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('Initial opacity for WMS overlays (0-100%)') }}
                                </p>
                            </div>

                            <div>
                                <label for="satellite_wms_animation_speed" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    {{ __('Animation Speed') }} ({{ __('seconds per frame') }})
                                </label>
                                <input type="number" 
                                       name="satellite_wms_animation_speed" 
                                       id="satellite_wms_animation_speed"
                                       value="{{ $wmsAnimationSpeed }}"
                                       min="0.1"
                                       max="2"
                                       step="0.1"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('Playback speed for time-lapse animation (default: 0.5 seconds)') }}
                                </p>
                            </div>

                            <div>
                                <label for="satellite_wms_auto_refresh" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    {{ __('Auto-refresh Interval') }} ({{ __('minutes') }})
                                </label>
                                <input type="number" 
                                       name="satellite_wms_auto_refresh" 
                                       id="satellite_wms_auto_refresh"
                                       value="{{ $wmsAutoRefresh }}"
                                       min="5"
                                       max="60"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('How often to check for new data (default: 15 minutes)') }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Hidden provider-specific fields that actually get saved --}}
        @php
            $providers = ['knmi', 'nasa', 'custom'];
            $fields = ['europe_url', 'world_url', 'zoom'];
        @endphp
        @foreach($providers as $p)
            @foreach($fields as $f)
                @php
                    $key = "satellite.sources.{$p}.{$f}";
                    $formKey = str_replace('.', '_', $key);
                    $val = $all[$p][$f] ?? '';
                @endphp
                <input type="hidden"
                       id="hidden_{{ $formKey }}"
                       name="{{ $formKey }}"
                       value="{{ $val }}" />
            @endforeach
        @endforeach

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
(function () {
    const STORE = @json($all);

    function formKeyFor(provider, field) {
        return `satellite_sources_${provider}_${field}`;
    }

    function getHidden(provider, field) {
        const id = `hidden_${formKeyFor(provider, field)}`;
        const el = document.getElementById(id);
        return el ? el.value : (STORE?.[provider]?.[field] ?? '');
    }

    function loadProviderIntoUi(provider) {
        document.getElementById('satellite_ui_europe_url').value = getHidden(provider, 'europe_url') || '';
        document.getElementById('satellite_ui_world_url').value = getHidden(provider, 'world_url') || '';
        document.getElementById('satellite_zoom').value = getHidden(provider, 'zoom') || 4;
    }

    function saveUiIntoProvider(provider) {
        const map = {
            europe_url: document.getElementById('satellite_ui_europe_url').value,
            world_url: document.getElementById('satellite_ui_world_url').value,
            zoom: document.getElementById('satellite_zoom').value,
        };

        for (const [field, value] of Object.entries(map)) {
            const id = `hidden_${formKeyFor(provider, field)}`;
            const el = document.getElementById(id);
            if (el) el.value = value ?? '';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const providerSelect = document.getElementById('satellite_provider');
        const form = providerSelect?.closest('form');
        const uiEurope = document.getElementById('satellite_ui_europe_url');
        const uiWorld = document.getElementById('satellite_ui_world_url');
        const uiZoom = document.getElementById('satellite_zoom');
        const elEurope = document.getElementById('satellite-field-europe');
        const elWorld = document.getElementById('satellite-field-world');
        const elZoom = document.getElementById('satellite-field-zoom');
        const nasaOptions = document.getElementById('satellite-nasa-options');
        const nasaMode = document.getElementById('satellite_nasa_mode');
        const nasaImagery = document.getElementById('satellite_nasa_imagery');

        let currentProvider = providerSelect?.value || 'knmi';
        loadProviderIntoUi(currentProvider);

        function updateVisibility(provider) {
            // Match the radar page UX: show/hide fields per provider
            // - KNMI: only Europe URL (world/zoom hidden)
            // - NASA: Europe + World + Zoom
            // - Custom: Europe + World + Zoom
            const showWorld = provider === 'nasa' || provider === 'custom';
            const showZoom = provider === 'nasa' || provider === 'custom';

            if (elEurope) elEurope.style.display = 'block';
            if (elWorld) elWorld.style.display = showWorld ? 'block' : 'none';
            if (elZoom) elZoom.style.display = showZoom ? 'grid' : 'none';

            if (nasaOptions) nasaOptions.style.display = provider === 'nasa' ? 'block' : 'none';
        }

        updateVisibility(currentProvider);

        // When UI changes, write into the currently selected provider's hidden fields
        function onUiChange() {
            saveUiIntoProvider(currentProvider);
        }

        uiEurope?.addEventListener('input', onUiChange);
        uiWorld?.addEventListener('input', onUiChange);
        uiZoom?.addEventListener('input', onUiChange);

        // When provider changes: persist current provider, then load new provider
        providerSelect?.addEventListener('change', function () {
            saveUiIntoProvider(currentProvider);
            currentProvider = providerSelect.value || 'knmi';
            loadProviderIntoUi(currentProvider);
            updateVisibility(currentProvider);
        });

        function applyNasaPreset() {
            if ((providerSelect?.value || 'knmi') !== 'nasa') return;

            const mode = nasaMode?.value || 'nrt';
            const imagery = nasaImagery?.value || 'truecolor';

            // True color:
            // True color:
            // Use VIIRS daily mosaic. For "Near real-time" we auto-pick today's mosaic when it's available,
            // otherwise fall back to yesterday (more complete) to avoid black/no-data areas.
            const nrtTrueColor = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/VIIRS_SNPP_CorrectedReflectance_TrueColor/default/{time_auto}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg';
            const dailyTrueColor = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/VIIRS_SNPP_CorrectedReflectance_TrueColor/default/{time_yesterday}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpg';

            // Infrared (thermal-style) imagery: use MODIS cloud-top temperature (global, WMTS supported).
            // - NRT: daily, but lower latency
            // - Daily mosaic: stable daily layer
            const nrtIr = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/all/MODIS_Terra_Cloud_Top_Temp_Night_v6.1_NRT/default/{time_today}/GoogleMapsCompatible_Level6/{z}/{y}/{x}.png';
            const dailyIr = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/MODIS_Terra_Cloud_Top_Temp_Night/default/{time_yesterday}/GoogleMapsCompatible_Level6/{z}/{y}/{x}.png';

            let localUrl = '';
            let worldUrl = '';

            if (imagery === 'infrared') {
                localUrl = mode === 'nrt' ? nrtIr : dailyIr;
                worldUrl = mode === 'nrt' ? nrtIr : dailyIr;
            } else if (mode === 'nrt') {
                localUrl = nrtTrueColor;
                worldUrl = nrtTrueColor;
            } else {
                localUrl = dailyTrueColor;
                worldUrl = dailyTrueColor;
            }

            uiEurope.value = localUrl;
            uiWorld.value = worldUrl;
            saveUiIntoProvider('nasa');
        }

        nasaMode?.addEventListener('change', applyNasaPreset);
        nasaImagery?.addEventListener('change', applyNasaPreset);
        // Run once on load if provider is NASA
        applyNasaPreset();

        // Ensure latest UI value is persisted before submit (even if user didn't blur)
        form?.addEventListener('submit', function () {
            saveUiIntoProvider(currentProvider);
        });
    });
})();
</script>
@endpush
@endsection
