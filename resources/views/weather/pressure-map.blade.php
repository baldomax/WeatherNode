@extends('weather.layout')

@section('title', __('Pressure Map') . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __('Pressure map page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))

@section('content')
<style>
    .map-button.active {
        background: rgba(59, 130, 246, 0.5);
        border-color: rgba(59, 130, 246, 0.7);
    }
</style>

<div class="max-w-6xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('Pressure Map') }}</h1>
            <p class="text-gray-400">{{ __('Pressure map page intro', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]) }}</p>
            <p class="text-sm text-gray-500 mt-1">{{ __('Source') }}: NOAA/NWS</p>
        </div>
    </div>

    <div class="bg-weather-card rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-4 py-3 border-b border-white/10">
            <div class="flex flex-wrap gap-2">
                <button class="map-button text-sm px-3 py-2 rounded-lg border border-white/20 bg-white/10 hover:bg-white/20 transition"
                        data-map="atlantic"
                        onclick="changeMap('atlantic')">
                    {{ __('Atlantic Ocean') }}
                </button>
                <button class="map-button text-sm px-3 py-2 rounded-lg border border-white/20 bg-white/10 hover:bg-white/20 transition"
                        data-map="pacific"
                        onclick="changeMap('pacific')">
                    {{ __('Pacific Ocean') }}
                </button>
                <button class="map-button text-sm px-3 py-2 rounded-lg border border-white/20 bg-white/10 hover:bg-white/20 transition"
                        data-map="us"
                        onclick="changeMap('us')">
                    {{ __('United States') }}
                </button>
                <button class="map-button text-sm px-3 py-2 rounded-lg border border-white/20 bg-white/10 hover:bg-white/20 transition"
                        data-map="europe"
                        onclick="changeMap('europe')">
                    {{ __('Europe') }}
                </button>
            </div>
        </div>

        <div class="relative bg-black/20" style="height: clamp(360px, 66vh, 740px); height: clamp(360px, 66dvh, 740px);">
            <div class="loading absolute inset-0 flex items-center justify-center text-white text-lg">{{ __('Loading') }}...</div>
            <img id="pressureMapImage"
                 class="absolute inset-0 w-full h-full object-contain"
                 src=""
                 alt="{{ __('Pressure Map') }}"
                 style="display: none;"
                 onload="this.style.display='block'; document.querySelector('.loading').style.display='none';"
                 onerror="this.style.display='none'; document.querySelector('.loading').textContent='{{ __('Failed to load map') }}';">
        </div>
    </div>

    <article class="bg-weather-card rounded-2xl p-6 border border-white/10 prose prose-invert prose-sm max-w-none">
        <h2 class="text-lg font-semibold mb-3">{{ __('Pressure map page about heading') }}</h2>
        <p class="text-gray-300 mb-3">{{ __('Pressure map page about body 1') }}</p>
        <p class="text-gray-300 mb-3">{{ __('Pressure map page about body 2') }}</p>
        <p class="text-gray-300 mb-3">{{ __('Pressure map page about body 3') }}</p>
        <footer class="text-xs text-gray-500 mt-4 pt-4 border-t border-white/10">{{ __('Pressure map page sources') }}</footer>
    </article>
</div>

<script>
        // Served through this app rather than hotlinked: the charts are fetched
        // once per refresh window, downscaled, and cached on disk.
        const maps = @json($mapUrls);
        const mapOrder = @json($mapOrder);

        let currentMap = @json($defaultMap ?? 'atlantic');

        function setActiveButton(mapType) {
            document.querySelectorAll('.map-button').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-map') === mapType);
            });
        }

        function changeMap(mapType, fromError) {
            currentMap = mapType;
            if (!fromError) setActiveButton(mapType);

            const img = document.getElementById('pressureMapImage');
            const loading = document.querySelector('.loading');
            img.style.display = 'none';
            loading.style.display = 'block';
            loading.textContent = '{{ __('Loading') }}...';

            img.onerror = function() {
                const nextIdx = mapOrder.indexOf(currentMap) + 1;
                if (nextIdx < mapOrder.length) {
                    currentMap = mapOrder[nextIdx];
                    setActiveButton(currentMap);
                    loading.textContent = '{{ __('Loading') }}... (' + currentMap + ')';
                    img.onerror = null;
                    img.src = maps[currentMap] + '?_' + Date.now();
                } else {
                    loading.textContent = '{{ __('Failed to load map') }}';
                    img.style.display = 'none';
                }
            };
            img.onload = function() {
                img.style.display = 'block';
                loading.style.display = 'none';
            };
            img.src = maps[mapType] + '?_' + Date.now();
        }

        // Load initial map (from server: station location or ?map=)
        setActiveButton(currentMap);
        changeMap(currentMap);

        // Refresh map every 15 minutes
        setInterval(() => {
            const img = document.getElementById('pressureMapImage');
            const loading = document.querySelector('.loading');
            img.style.display = 'none';
            loading.style.display = 'block';
            loading.textContent = '{{ __('Loading') }}...';
            img.onerror = null;
            img.src = maps[currentMap] + '?_' + Date.now();
        }, 15 * 60 * 1000);
</script>
@endsection
