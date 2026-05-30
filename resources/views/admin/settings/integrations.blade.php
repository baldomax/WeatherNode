@extends('layouts.admin')

@section('title', __('Head Code & Integrations'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                {{ __('Head Code & Integrations') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Inject custom code into the <head> section of every public page.') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="text-gray-400 hover:text-white transition-colors">
            &larr; {{ __('Back to Settings') }}
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-700/50 bg-emerald-900/30 px-4 py-3 text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-lg border border-blue-700/50 bg-blue-900/20 px-4 py-3 text-blue-200 text-sm">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-1 1v3a1 1 0 102 0V7a1 1 0 00-1-1zm0 8a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-medium">{{ __('Use cases') }}</p>
                <ul class="mt-1 list-disc pl-5 space-y-0.5 text-blue-300/90">
                    <li>{{ __('Google AdSense auto ads (in-page ads script)') }}</li>
                    <li>{{ __('Google Analytics / Tag Manager') }}</li>
                    <li>{{ __('Meta/Facebook Pixel') }}</li>
                    <li>{{ __('Any other tracking, verification, or third-party script') }}</li>
                </ul>
                <p class="mt-2 text-xs text-blue-400/70">{{ __('The code is inserted right before the closing </head> tag on all public pages.') }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-amber-700/50 bg-amber-900/20 px-4 py-3 text-amber-200 text-sm">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-amber-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="font-medium">{{ __('Do not load scripts more than once!') }}</p>
                <p class="mt-1 text-amber-300/90">{{ __('Ad networks and analytics providers require their base script to be loaded only once. If you already have an ad widget configured under Dashboard Widgets, do not paste the same script here again.') }}</p>
                <ul class="mt-2 list-disc pl-5 space-y-0.5 text-amber-300/80 text-xs">
                    <li><strong>Google AdSense:</strong> {{ __('The adsbygoogle.js script should appear only once across your entire site. Use this field for the auto ads / in-page ads script, and the Widgets ad code field for individual ad unit blocks — they share the same base script, so do not duplicate it.') }}</li>
                    <li><strong>Media.net / Adsterra / Ezoic:</strong> {{ __('Same rule — include the provider\'s main loader script in one place only (here or in the widget), not both.') }}</li>
                    <li><strong>Google Analytics / Tag Manager:</strong> {{ __('Only include the gtag.js or GTM snippet once. Duplicate loading causes inflated page views and incorrect analytics data.') }}</li>
                    <li><strong>Meta / Facebook Pixel:</strong> {{ __('Loading the pixel twice will double-count events and corrupt your conversion data.') }}</li>
                </ul>
                <p class="mt-2 text-xs text-amber-400/70">{{ __('When in doubt: this field is for base/loader scripts that belong in <head>. The ad widget code field under Dashboard Widgets is for the display block that renders an ad unit in a specific spot.') }}</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.settings.integrations.update') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-2">{{ __('Custom Head Code') }}</h2>
            <p class="text-sm text-gray-400 mb-4">{{ __('Paste any HTML, JavaScript, or meta tags you want to include in the <head> of your site. This is output as-is, so make sure the code is correct.') }}</p>

            <textarea name="head_code"
                      rows="12"
                      class="w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono focus:ring-2 focus:ring-amber-500 focus:border-amber-500 placeholder-gray-500"
                      placeholder="{{ __("<!-- Example: Google AdSense auto ads -->\n<script async src=\"https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX\" crossorigin=\"anonymous\"></script>\n\n<!-- Example: Google Analytics -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX\"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', 'G-XXXXXXXXXX');\n</script>") }}">{{ $headCode }}</textarea>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-400 hover:text-white transition-colors">
                &larr; {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>
@endsection
