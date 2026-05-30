@php
    $menuFeatures = \App\Support\MenuFeatureMap::all();
@endphp

<!-- Navigation Tabs - Hidden on mobile, shown on large screens -->
<nav class="glass border-b border-white/5 relative z-40 hidden lg:block" google-side-rail-overlap="false">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex gap-1 overflow-x-auto py-2 text-sm">
            <a href="{{ route('home') }}" class="px-4 py-2 {{ request()->routeIs('home') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg font-medium transition-colors">{{ __('Home') }}</a>
            @if($menuFeatures['forecast'] ?? true)
                <a href="{{ route('forecast') }}" class="px-4 py-2 {{ request()->routeIs('forecast') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Forecast') }}</a>
            @endif
            @if($menuFeatures['history'] ?? true)
                <a href="{{ route('history') }}" class="px-4 py-2 {{ request()->routeIs('history*') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('History') }}</a>
            @endif
            @if($menuFeatures['statistics'] ?? true)
                <a href="{{ route('statistics') }}" class="px-4 py-2 {{ request()->routeIs('statistics') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Statistics') }}</a>
            @endif
            @if($menuFeatures['radar'] ?? true)
                <a href="{{ route('radar') }}" class="px-4 py-2 {{ request()->routeIs('radar') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Radar') }}</a>
            @endif
            @if($menuFeatures['satellite'] ?? true)
                <a href="{{ route('satellite') }}" class="px-4 py-2 {{ request()->routeIs('satellite') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Satellite') }}</a>
            @endif
            @if($menuFeatures['air_pollen'] ?? true)
                <a href="{{ route('airquality') }}" class="px-4 py-2 {{ request()->routeIs('airquality') || request()->routeIs('pollen') || request()->routeIs('noise') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Air & Pollen') }}</a>
            @endif
            @if($menuFeatures['astronomy'] ?? true)
                <a href="{{ route('astronomy') }}" class="px-4 py-2 {{ request()->routeIs('astronomy') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Astronomy') }}</a>
            @endif
            @if($menuFeatures['sky_water'] ?? true)
                <a href="{{ route('aviation') }}" class="px-4 py-2 {{ request()->routeIs('aviation*') || request()->routeIs('water') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Sky & Water') }}</a>
            @endif
            @if($menuFeatures['fire_weather'] ?? true)
                <a href="{{ route('fire-weather') }}" class="px-4 py-2 {{ request()->routeIs('fire-weather') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Fire Weather') }}</a>
            @endif
            <a href="{{ route('weather.community-stations') }}" class="px-4 py-2 {{ request()->routeIs('weather.community-stations') ? 'bg-blue-600 shadow-lg shadow-blue-600/30' : 'hover:bg-white/10 text-gray-300' }} rounded-lg transition-colors">{{ __('Community Stations') }}</a>
        </div>
    </div>
</nav>

@if(session('feature_disabled'))
    <div class="max-w-7xl mx-auto px-4 pt-3">
        <div class="rounded-lg border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-sm text-blue-200">
            {{ session('feature_disabled') }}
        </div>
    </div>
@endif
