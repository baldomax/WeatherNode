@extends('layouts.admin')

@section('title', __('Social Sharing Cards'))

@section('content')
@php
    use App\Models\Setting;
    use App\Services\OgImageService;
    $drivers      = OgImageService::availableDrivers();
    $resolved     = OgImageService::resolvedDriver();
    $settings     = $settings->keyBy('key');
    $enabled      = (bool)($settings->get('og.enabled')?->getCastedValue() ?? false);
    $driver       = $settings->get('og.driver')?->value ?? 'auto';
    $bothAvail    = $drivers['gd'] && $drivers['imagick'];
    $anyAvail     = $drivers['gd'] || $drivers['imagick'];
    $primaryIcao  = Setting::getValue('metar.primary_icao', null);
    $metarEnabled = (bool) Setting::getValue('metar.enabled', false);
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                {{ __('Social Sharing Cards') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Dynamic Open Graph images for Twitter/X, WhatsApp and Facebook previews') }}</p>
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

    {{-- PHP extension status panel --}}
    <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">{{ __('PHP Extension Status') }}</h2>
        <p class="text-sm text-gray-400 mb-5">{{ __('OG image generation requires at least one image-processing PHP extension. Imagick produces slightly sharper text; GD is lighter-weight and available on almost every shared host.') }}</p>

        <div class="flex flex-wrap gap-4 mb-4">
            {{-- GD --}}
            <div class="flex items-center gap-3 p-4 rounded-lg border {{ $drivers['gd'] ? 'border-emerald-700 bg-emerald-900/20' : 'border-gray-600 bg-gray-900/20' }}">
                <div class="w-3 h-3 rounded-full {{ $drivers['gd'] ? 'bg-emerald-400' : 'bg-red-400' }}"></div>
                <div>
                    <p class="font-semibold text-white">GD</p>
                    <p class="text-xs text-gray-400">{{ $drivers['gd'] ? __('Available') : __('Not installed') }}</p>
                </div>
                @if($drivers['gd'])
                    <span class="ml-2 text-xs bg-emerald-800/60 text-emerald-300 px-2 py-0.5 rounded">✓ {{ __('Ready') }}</span>
                @endif
            </div>

            {{-- Imagick --}}
            <div class="flex items-center gap-3 p-4 rounded-lg border {{ $drivers['imagick'] ? 'border-emerald-700 bg-emerald-900/20' : 'border-gray-600 bg-gray-900/20' }}">
                <div class="w-3 h-3 rounded-full {{ $drivers['imagick'] ? 'bg-emerald-400' : 'bg-gray-500' }}"></div>
                <div>
                    <p class="font-semibold text-white">Imagick</p>
                    <p class="text-xs text-gray-400">{{ $drivers['imagick'] ? __('Available') : __('Not installed') }}</p>
                </div>
                @if($drivers['imagick'])
                    <span class="ml-2 text-xs bg-emerald-800/60 text-emerald-300 px-2 py-0.5 rounded">✓ {{ __('Ready') }}</span>
                @endif
            </div>
        </div>

        @if(!$anyAvail)
            <div class="rounded-lg border border-yellow-700/50 bg-yellow-900/20 px-4 py-3 text-yellow-200 text-sm">
                <strong>{{ __('No image driver available.') }}</strong>
                {{ __('Install the GD or Imagick PHP extension to enable OG images. On Debian/Ubuntu: ') }}
                <code class="bg-gray-800 px-1 rounded">sudo apt install php-gd</code>
                {{ __(' or ') }}
                <code class="bg-gray-800 px-1 rounded">sudo apt install php-imagick</code>.
            </div>
        @endif
    </div>

    {{-- Settings form --}}
    <form action="{{ route('admin.settings.update', 'og') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Enable toggle --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Enable Dynamic OG Images') }}</h2>
            <p class="text-sm text-gray-400 mb-5">{{ __('When enabled, sharing any page URL on social media will show a branded weather card with live data instead of a generic link preview.') }}</p>

            <label class="flex items-center gap-4 cursor-pointer {{ !$anyAvail ? 'opacity-50 pointer-events-none' : '' }}">
                <input type="hidden" name="og_enabled" value="0">
                <div class="relative">
                    <input type="checkbox" name="og_enabled" value="1" id="og_enabled"
                           {{ $enabled ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-600 peer-checked:bg-violet-600 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-violet-400"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </div>
                <div>
                    <p class="font-medium text-white">{{ __('Generate OG images') }}</p>
                    <p class="text-xs text-gray-400">{{ __('Adds /og/*.png endpoints; layout uses them automatically when a page is shared.') }}</p>
                </div>
            </label>
        </div>

        {{-- Driver selector (only shown if both are available) --}}
        @if($bothAvail)
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Image Driver') }}</h2>
            <p class="text-sm text-gray-400 mb-5">{{ __('Both GD and Imagick are available. Auto-detect picks Imagick first (slightly better text rendering). Switch to GD if you experience compatibility issues.') }}</p>

            <div class="flex flex-wrap gap-4">
                @foreach(['auto' => 'Auto-detect (recommended)', 'imagick' => 'Imagick', 'gd' => 'GD'] as $val => $label)
                    <label class="flex items-center gap-3 p-4 rounded-lg border cursor-pointer transition-colors
                        {{ $driver === $val ? 'border-violet-500 bg-violet-900/20' : 'border-gray-600 hover:border-gray-500' }}">
                        <input type="radio" name="og_driver" value="{{ $val }}"
                               {{ $driver === $val ? 'checked' : '' }}
                               class="w-4 h-4 text-violet-500 focus:ring-violet-500">
                        <div>
                            <span class="font-medium text-white">{{ $label }}</span>
                            @if($val === 'auto')
                                <p class="text-xs text-gray-400">{{ __('Imagick → GD fallback') }}</p>
                            @elseif($val === 'imagick')
                                <p class="text-xs text-gray-400">{{ __('Higher quality text rendering') }}</p>
                            @else
                                <p class="text-xs text-gray-400">{{ __('Lighter-weight, widely supported') }}</p>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
        @else
            {{-- Hidden field to preserve driver setting when only one driver is available --}}
            <input type="hidden" name="og_driver" value="{{ $driver }}">
        @endif

        {{-- Preview section --}}
        @if($enabled && $anyAvail)
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-2">{{ __('Preview') }}</h2>
            <p class="text-sm text-gray-400 mb-5">{{ __('Open a card URL in a new tab to verify the output. Social crawlers cache images aggressively — append ?v=2 to bust a cached preview.') }}</p>

            <div class="flex flex-wrap gap-3">
                @php
                    $previewCards = [
                        ['Home',        route('og.home'),                                          'bg-blue-800/40 text-blue-300'],
                        ['Forecast',    route('og.forecast'),                                      'bg-cyan-800/40 text-cyan-300'],
                        ['Statistics',  route('og.statistics', ['year' => date('Y')]),             'bg-amber-800/40 text-amber-300'],
                        ['Fire Weather',route('og.fire-weather'),                                  'bg-red-800/40 text-red-300'],
                        ['Air Quality', route('og.air-quality'),                                   'bg-teal-800/40 text-teal-300'],
                        ['Astronomy',   route('og.astronomy'),                                     'bg-cyan-800/40 text-cyan-300'],
                    ];
                    if ($metarEnabled && $primaryIcao) {
                        $previewCards[] = [
                            'Aviation — ' . strtoupper($primaryIcao),
                            route('og.aviation', ['icao' => strtolower($primaryIcao)]),
                            'bg-indigo-800/40 text-indigo-300',
                        ];
                    }
                @endphp
                @foreach($previewCards as [$lbl, $url, $cls])
                    <a href="{{ $url }}" target="_blank"
                       class="px-3 py-2 rounded-lg text-sm font-medium {{ $cls }} hover:opacity-80 transition-opacity">
                        {{ $lbl }} ↗
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex items-center justify-between">
            {{-- Regenerate all cards --}}
            @if($enabled && $anyAvail)
            <form action="{{ route('admin.settings.og.clear-cache') }}" method="POST">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors border border-gray-600"
                        onclick="return confirm('Clear all cached OG images? Each card will regenerate on next visit.')">
                    ↺ {{ __('Regenerate all cards') }}
                </button>
            </form>
            @else
            <span></span>
            @endif

            <button type="submit" class="px-5 py-2 bg-violet-600 hover:bg-violet-500 text-white font-medium rounded-lg transition-colors"
                {{ !$anyAvail ? 'disabled' : '' }}>
                {{ __('Save') }}
            </button>
        </div>
    </form>

</div>
@endsection
