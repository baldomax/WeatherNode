@extends('layouts.admin')

@section('title', __('Set up your station'))

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="space-y-3">
        @include('admin.setup._progress')
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Where is your station?') }}</h1>
        <p class="text-gray-600 dark:text-gray-300">
            {{ __('Forecasts, sunrise and sunset, warnings and tides all start from this. Drag the marker or use your own location, and the numbers fill themselves in.') }}
        </p>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <ul class="text-sm text-red-800 dark:text-red-300 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.setup.station.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Station name') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Shown in the header, the page titles and the footer.') }}</p>
                <input type="text" name="name" id="name" required maxlength="255"
                       value="{{ old('name', $name) }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="location" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Place') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('The town or area, written the way you would say it.') }}</p>
                <input type="text" name="location" id="location" maxlength="255"
                       value="{{ old('location', $location) }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="server_url" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Site address') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Filled in from where you are reading this. Change it if the site is reached at a different address from outside.') }}</p>
                <input type="url" name="server_url" id="server_url" maxlength="255"
                       value="{{ old('server_url', $serverUrl) }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Position') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Click the map or drag the marker. You can also type the numbers.') }}</p>
                </div>
                {{-- Hidden unless the browser will actually answer. Geolocation
                     needs a secure context, and plenty of self-hosters reach
                     their site over plain HTTP on a LAN address. --}}
                <button type="button" id="setup-locate" hidden
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ __('Use my location') }}
                </button>
            </div>

            {{-- The map is an aid. Map tiles need the internet, and somebody
                 setting up on an isolated network still has to be able to type
                 two numbers, so the fields below work on their own. --}}
            <div id="setup-map" data-zoom="{{ $nothingChosenYet ? 2 : 11 }}"
                 class="w-full h-72 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-900"></div>
            <p id="setup-locate-error" hidden class="text-xs text-amber-700 dark:text-amber-400"></p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="latitude" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Latitude') }}</label>
                    <input type="number" name="latitude" id="latitude" step="any" min="-90" max="90" required
                           value="{{ old('latitude', $latitude) }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="longitude" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Longitude') }}</label>
                    <input type="number" name="longitude" id="longitude" step="any" min="-180" max="180" required
                           value="{{ old('longitude', $longitude) }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="elevation" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Height above sea level') }}</label>
                    <input type="number" name="elevation" id="elevation" step="any" min="-500" max="9000"
                           value="{{ old('elevation', $elevation) }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('In metres. Used for pressure at sea level.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <label for="timezone" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Timezone') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Every time on the site is shown in this zone.') }}</p>
            <select name="timezone" id="timezone" required
                    @if($nothingChosenYet && !old('timezone')) data-suggest="1" @endif
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                @foreach($timezones as $tz)
                    <option value="{{ $tz }}" {{ old('timezone', $timezone) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap">
            <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors">
                {{ __('Save and continue') }}
            </button>
            <button type="submit" form="setup-skip"
                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                {{ __('I will do this later') }}
            </button>
        </div>
    </form>

    <form id="setup-skip" method="POST" action="{{ route('admin.setup.skip') }}" class="hidden">@csrf</form>
</div>
@endsection

@push('scripts')
{{-- The admin layout has one stack, at the end of the body. Leaflet is pushed
     first so it has run by the time the script below asks for L. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const latField = document.getElementById('latitude');
    const lonField = document.getElementById('longitude');
    const tzField = document.getElementById('timezone');

    // The browser knows its own zone, and answering that needs no network and
    // no secure context. Only suggest it while nothing has been chosen, so an
    // owner who picked a zone keeps it.
    if (tzField && tzField.dataset.suggest) {
        try {
            const guess = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (guess && [...tzField.options].some(o => o.value === guess)) {
                tzField.value = guess;
            }
        } catch (e) { /* older browser: leave the list alone */ }
    }

    const round = (n) => Math.round(n * 10000) / 10000;
    const readField = (field, fallback) => {
        const value = parseFloat(field.value);
        return Number.isFinite(value) ? value : fallback;
    };

    const mapEl = document.getElementById('setup-map');
    if (!mapEl || typeof L === 'undefined') {
        return; // No tiles and no library: the number fields still work.
    }

    const start = [readField(latField, 0), readField(lonField, 0)];
    const map = L.map(mapEl).setView(start, parseInt(mapEl.dataset.zoom || '9', 10));

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    const marker = L.marker(start, { draggable: true }).addTo(map);

    const fieldsFromMarker = (latlng) => {
        latField.value = round(latlng.lat);
        lonField.value = round(latlng.lng);
    };

    marker.on('dragend', () => fieldsFromMarker(marker.getLatLng()));
    map.on('click', (e) => {
        marker.setLatLng(e.latlng);
        fieldsFromMarker(e.latlng);
    });

    // Typing moves the marker, so the two never disagree.
    const markerFromFields = () => {
        const lat = parseFloat(latField.value);
        const lon = parseFloat(lonField.value);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
        if (lat < -90 || lat > 90 || lon < -180 || lon > 180) return;
        marker.setLatLng([lat, lon]);
        map.panTo([lat, lon]);
    };
    latField.addEventListener('change', markerFromFields);
    lonField.addEventListener('change', markerFromFields);

    const locateButton = document.getElementById('setup-locate');
    const locateError = document.getElementById('setup-locate-error');
    if (locateButton && window.isSecureContext && navigator.geolocation) {
        locateButton.hidden = false;
        locateButton.addEventListener('click', () => {
            locateButton.disabled = true;
            locateError.hidden = true;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    locateButton.disabled = false;
                    const here = [position.coords.latitude, position.coords.longitude];
                    marker.setLatLng(here);
                    map.setView(here, 13);
                    fieldsFromMarker(marker.getLatLng());
                },
                () => {
                    locateButton.disabled = false;
                    locateError.hidden = false;
                    locateError.textContent = @json(__('Your browser would not share a location. Drag the marker instead.'));
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }
})();
</script>
@endpush
