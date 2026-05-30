@extends('weather.layout')

@section('title', __('Earthquakes') . ' - ' . \App\Models\Setting::stationName())

@section('meta_description', __('Earthquakes page meta description', ['location' => $stationLocation]))
@section('og_image', route('og.generic', ['page' => 'earthquakes']))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">🌍 {{ __('Earthquakes') }}</h1>
            <p class="text-gray-400">{{ __('Earthquakes page intro', ['location' => $stationLocation]) }}</p>
        </div>
        <a href="{{ route('home') }}" class="text-sm text-blue-400 hover:text-blue-300">
            ← {{ __('Back to dashboard') }}
        </a>
    </div>

    <!-- Sort Options -->
    <div class="flex items-center gap-4 text-sm">
        <span class="text-gray-400">{{ __('Sort by') }}:</span>
        <a href="?sort=time" class="px-3 py-1 rounded-lg {{ $sort === 'time' ? 'bg-blue-500/20 text-blue-400' : 'text-gray-400 hover:text-white' }}">
            {{ __('Time') }}
        </a>
        <a href="?sort=magnitude" class="px-3 py-1 rounded-lg {{ $sort === 'magnitude' ? 'bg-blue-500/20 text-blue-400' : 'text-gray-400 hover:text-white' }}">
            {{ __('Magnitude') }}
        </a>
        <a href="?sort=distance" class="px-3 py-1 rounded-lg {{ $sort === 'distance' ? 'bg-blue-500/20 text-blue-400' : 'text-gray-400 hover:text-white' }}">
            {{ __('Distance') }}
        </a>
    </div>

    @if(count($earthquakes) > 0)
        <!-- Earthquakes Table -->
        <div class="bg-weather-card rounded-2xl border border-white/10 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-gray-400 text-sm border-b border-white/10">
                            <th class="px-4 py-3">{{ __('Magnitude') }}</th>
                            <th class="px-4 py-3">{{ __('Depth') }}</th>
                            <th class="px-4 py-3">{{ __('Distance') }}</th>
                            <th class="px-4 py-3">{{ __('Time') }}</th>
                            <th class="px-4 py-3">{{ __('Location') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($earthquakes as $eq)
                            @php
                                $magnitude = $eq['magnitude'] ?? 0;
                                $magnitudeColor = match(true) {
                                    $magnitude < 2 => 'bg-gray-500/20 text-gray-300',
                                    $magnitude < 3 => 'bg-blue-500/20 text-blue-400',
                                    $magnitude < 4 => 'bg-cyan-500/20 text-cyan-400',
                                    $magnitude < 5 => 'bg-yellow-500/20 text-yellow-400',
                                    $magnitude < 6 => 'bg-orange-500/20 text-orange-400',
                                    $magnitude < 7 => 'bg-red-500/20 text-red-400',
                                    default => 'bg-purple-500/20 text-purple-400',
                                };
                                $magnitudeClass = match(true) {
                                    $magnitude < 3 => __('Minor'),
                                    $magnitude < 4 => __('Light'),
                                    $magnitude < 5 => __('Moderate'),
                                    $magnitude < 6 => __('Strong'),
                                    $magnitude < 7 => __('Major'),
                                    default => __('Great'),
                                };
                                $time = $eq['time'] ?? $eq['date_time'] ?? null;
                                $isToday = $time && \Carbon\Carbon::parse($time)->isToday();
                                $isYesterday = $time && \Carbon\Carbon::parse($time)->isYesterday();
                            @endphp
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-14 h-14 rounded-lg flex items-center justify-center font-bold text-lg {{ $magnitudeColor }}">
                                            {{ number_format($magnitude, 1) }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $magnitudeClass }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $eq['depth'] ?? '--' }} km
                                </td>
                                <td class="px-4 py-3">
                                    {{ round($eq['distance'] ?? 0) }} km
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        @if($isToday)
                                            <span class="text-green-400">{{ __('Today') }}</span>
                                        @elseif($isYesterday)
                                            <span class="text-yellow-400">{{ __('Yesterday') }}</span>
                                        @else
                                            {{ $time ? \Carbon\Carbon::parse($time)->format('d M Y') : '--' }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $time ? \Carbon\Carbon::parse($time)->format('H:i') : '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="max-w-xs truncate">{{ $eq['place'] ?? $eq['location'] ?? $eq['title'] ?? '--' }}</div>
                                    @if(isset($eq['link']))
                                        <a href="{{ $eq['link'] }}" target="_blank" rel="noopener" class="text-xs text-blue-400 hover:text-blue-300">
                                            {{ __('Details') }} →
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- No Earthquakes -->
        <div class="bg-weather-card rounded-2xl p-12 border border-white/10 text-center">
            <div class="text-6xl mb-4">✓</div>
            <h2 class="text-xl font-semibold mb-2">{{ __('No recent earthquakes') }}</h2>
            <p class="text-gray-400">{{ __('No seismic activity detected in the past 24 hours.') }}</p>
        </div>
    @endif

    <!-- Source Attribution -->
    <div class="text-center text-sm text-gray-500">
        {{ __('Data source') }}:
        <a href="https://www.emsc-csem.org/" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">
            EMSC (European-Mediterranean Seismological Centre)
        </a>
    </div>

    <!-- About earthquakes (scientific) -->
    <article class="bg-weather-card rounded-2xl border border-white/10 p-6 md:p-8" aria-labelledby="earthquakes-about-heading">
        <h2 id="earthquakes-about-heading" class="text-xl font-semibold mb-4">{{ __('Earthquakes page about heading') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-gray-300 space-y-4">
            <p>{{ __('Earthquakes page about body 1') }}</p>
            <p>{{ __('Earthquakes page about body 2') }}</p>
            <p>{{ __('Earthquakes page about body 3') }}</p>
        </div>
        <footer class="mt-6 pt-4 border-t border-white/10">
            <p class="text-xs text-gray-500">{{ __('Earthquakes page sources') }}</p>
        </footer>
    </article>
</div>
@endsection
