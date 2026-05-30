@extends('layouts.admin')

@section('title', __('Appearance'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                </svg>
                {{ __('Appearance') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Site theme (FX vs Flat)') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="text-gray-400 hover:text-white transition-colors">
            ← {{ __('Back to Settings') }}
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-700/50 bg-emerald-900/30 px-4 py-3 text-emerald-200">
            {{ session('success') }}
            <p class="mt-2 text-sm text-emerald-300/90">{{ __('Open or refresh the public site (home/dashboard) to see the change.') }}</p>
        </div>
    @endif

    <form action="{{ route('admin.settings.appearance.update') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ __('Site theme') }}</h2>
            <p class="text-sm text-gray-400 mb-4">{{ __('With Flat, the entire public site uses a simplified design: no glass effects, blur, or animations; data and functionality stay the same.') }}</p>
            <p class="text-xs text-gray-500 mb-4">{{ __('Applies to the public weather pages (dashboard, login, etc.); the admin panel is unchanged.') }}</p>

            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-3 p-4 rounded-lg border cursor-pointer transition-colors {{ ($siteTheme ?? 'fx') === 'fx' ? 'border-cyan-500 bg-cyan-900/20' : 'border-gray-600 hover:border-gray-500' }}">
                    <input type="radio" name="appearance_theme" value="fx" {{ ($siteTheme ?? 'fx') === 'fx' ? 'checked' : '' }} class="w-4 h-4 text-cyan-500 focus:ring-cyan-500">
                    <span class="font-medium text-white">{{ __('FX (rich)') }}</span>
                </label>
                <label class="flex items-center gap-3 p-4 rounded-lg border cursor-pointer transition-colors {{ ($siteTheme ?? 'fx') === 'flat' ? 'border-cyan-500 bg-cyan-900/20' : 'border-gray-600 hover:border-gray-500' }}">
                    <input type="radio" name="appearance_theme" value="flat" {{ ($siteTheme ?? 'fx') === 'flat' ? 'checked' : '' }} class="w-4 h-4 text-cyan-500 focus:ring-cyan-500">
                    <span class="font-medium text-white">{{ __('Flat (simplified)') }}</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-medium rounded-lg transition-colors">
                {{ __('Save') }}
            </button>
        </div>
    </form>
</div>
@endsection
