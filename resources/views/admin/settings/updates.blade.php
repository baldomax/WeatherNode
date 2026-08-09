@extends('layouts.admin')

@section('title', __('Updates'))

@section('content')
@php
    // Suppress errors: on hosts with a strict open_basedir, probing the
    // out-of-tree path /.dockerenv raises a warning that Laravel would turn
    // into a 500. @ makes the check fall back to false instead of crashing.
    $isContainerized = @file_exists('/.dockerenv') || env('CONTAINERIZED', false);
@endphp
<div class="w-full" x-data="updateManager()">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Updates') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Application Updates') }}</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">{{ __('Manage application updates and check compatibility.') }}</p>
    </div>

    <!-- Helper Info Box -->
    <div class="mb-8 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-xl p-6">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-indigo-500 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-indigo-900 dark:text-indigo-100 mb-2">{{ __('How Updates Work') }}</h3>
                <div class="space-y-2 text-sm text-indigo-800 dark:text-indigo-200">
                    <p><strong>{{ __('Docker Deployments (Preferred)') }}:</strong> {{ __('If this app runs in Docker, update by pulling the new image and redeploying containers, then run php artisan migrate --force in the app container. This keeps container layers and runtime state consistent.') }}</p>
                    <p><strong>{{ __('Automatic Updates (Tier 1)') }}:</strong> {{ __('If your server supports it, you can update directly from this page. The system will automatically backup your data, download the update, verify it, and deploy it safely with automatic rollback if anything goes wrong.') }}</p>
                    <p><strong>{{ __('Preview Updates') }}:</strong> {{ __('Use the "Preview Update" button to test an update without deploying. This checks compatibility and requirements without making any changes.') }}</p>
                    <p><strong>{{ __('Manual Updates (Tier 0)') }}:</strong> {{ __('If browser updates aren\'t supported, you can download the ZIP file from GitHub and upload it manually. This works on almost all hosting providers.') }}</p>
                    <p><strong>{{ __('Safety Features') }}:</strong> {{ __('All updates include automatic backups, pre-update validation, health checks, and rollback capability. Your data is protected at every step.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Compatibility Check -->
    <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Updater Compatibility') }}</h2>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('This checks if your server supports browser-based updates') }}
            </div>
        </div>
        
        <div class="space-y-4">
            <div class="flex items-start">
                @if($compatibility['supported'])
                    <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                @else
                    <svg class="w-6 h-6 text-yellow-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                @endif
                <div class="flex-1">
                    <p class="text-gray-900 dark:text-white font-medium">
                        @if($compatibility['supported'])
                            {{ __('Browser update supported') }}: {{ __('Yes') }}
                        @else
                            {{ __('Browser update supported') }}: {{ __('No') }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $compatibility['recommendation'] }}
                    </p>
                </div>
            </div>

            <!-- Detailed Checks -->
            <div class="mt-4 space-y-2">
                @foreach($compatibility['checks'] as $checkName => $check)
                    <div class="flex items-center text-sm">
                        @if($check['passed'])
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                        <span class="text-gray-700 dark:text-gray-300">{{ $check['message'] }}</span>
                        @if(isset($check['advice']))
                            <span class="text-gray-500 dark:text-gray-400 ml-2">({{ $check['advice'] }})</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if($isContainerized)
        <div class="mb-8 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-6">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-amber-500 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-amber-900 dark:text-amber-100 mb-2">{{ __('Containerized install detected') }}</h3>
                    <p class="text-sm text-amber-800 dark:text-amber-200 mb-3">
                        {{ __('For Docker deployments, prefer updating via Docker image pull + container redeploy instead of the in-app updater.') }}
                    </p>
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        {{ __('Typical flow: docker compose pull && docker compose up -d --force-recreate && docker compose exec app php artisan migrate --force') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Current Version -->
    <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Current Version') }}</h2>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('Your installed version and active release directory') }}
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-gray-600 dark:text-gray-400">{{ __('Installed Version') }}</span>
            <span class="text-gray-900 dark:text-white font-medium">{{ $currentVersion }}</span>
        </div>
        @if($currentRelease)
            <div class="flex items-center justify-between mt-2">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Active Release') }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ $currentRelease }}</span>
            </div>
        @endif
    </div>

    <!-- Update Available -->
    @if($isUpdateAvailable && $latestRelease)
        <div class="mb-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-blue-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                        {{ __('Update Available') }}
                    </h3>
                    <p class="text-blue-800 dark:text-blue-200 mb-4">
                        {{ __('Latest version') }}: <strong>{{ $latestRelease['tag'] }}</strong>
                        @if($latestRelease['published_at'])
                            ({{ \Carbon\Carbon::parse($latestRelease['published_at'])->diffForHumans() }})
                        @endif
                    </p>
                    
                    @if($releaseNotes && $releaseNotes['has_breaking'])
                        <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 rounded-lg">
                            <p class="text-yellow-800 dark:text-yellow-200 text-sm font-medium">
                                ⚠️ {{ __('This release contains breaking changes. Please review the release notes carefully.') }}
                            </p>
                        </div>
                    @endif
                    
                    @if($releaseNotes && $releaseNotes['summary'])
                        <div class="mb-4 p-3 bg-white dark:bg-gray-800 rounded-lg">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                <strong>{{ __('What\'s new') }}:</strong> {{ $releaseNotes['summary'] }}
                            </p>
                        </div>
                    @endif
                    @if($compatibility['supported'] && config('updater.enabled'))
                        <div class="flex gap-2">
                            <button @click="previewUpdate('{{ $latestRelease['tag'] }}')"
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">
                                {{ __('Preview Update') }}
                            </button>
                            <button @click="deployUpdate('{{ $latestRelease['tag'] }}')"
                                    x-bind:disabled="deploying"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!deploying">{{ __('Update Now') }}</span>
                                <span x-show="deploying">{{ __('Updating...') }}</span>
                            </button>
                        </div>
                    @else
                        <div class="space-y-2">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                @if(!config('updater.enabled'))
                                    {{ __('Updater is disabled. Set UPDATER_ENABLED=true in .env to enable browser updates.') }}
                                @else
                                    {{ __('Browser update is not supported on this server.') }}
                                @endif
                            </p>
                            <a href="https://github.com/{{ config('updater.github_repo') }}/releases/latest" 
                               target="_blank"
                               class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                                {{ __('Download Manual Update ZIP') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif($latestRelease)
        <div class="mb-8 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-6">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">
                    {{ __('You are running the latest version') }} ({{ $latestRelease['tag'] }})
                </p>
            </div>
        </div>
    @else
        <div class="mb-8 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-6">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-amber-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-amber-900 dark:text-amber-100 mb-2">
                        {{ __('No GitHub release data available') }}
                    </h3>
                    <p class="text-amber-800 dark:text-amber-200 mb-4">
                        {{ __('The updater could not fetch a latest release yet. This is expected if your repository has no published GitHub Releases.') }}
                    </p>
                    <div class="space-y-2 text-sm text-amber-800 dark:text-amber-200">
                        <p>{{ __('After you publish your first GitHub Release, it will appear here automatically (cache refresh up to 5 minutes).') }}</p>
                        <p>{{ __('Also verify UPDATER_GITHUB_REPO and (for private repos) UPDATER_GITHUB_TOKEN in your .env file.') }}</p>
                    </div>
                    <a href="https://github.com/{{ config('updater.github_repo') }}/releases"
                       target="_blank"
                       class="inline-block mt-4 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium">
                        {{ __('Open GitHub Releases') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Update checks: useful whether or not the in-app updater is enabled, so
         deliberately outside the config('updater.enabled') gate below. --}}
    <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Update checks') }}</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ __('Once a day, check GitHub for a newer release and show a banner in the admin area when one is available. The check runs on the scheduler, never during a page load.') }}
            @if($lastUpdateCheckAt)
                <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Last checked: :when', ['when' => $lastUpdateCheckAt]) }}</span>
            @endif
        </p>
        <form method="POST" action="{{ route('admin.updates.notifications.update') }}" class="flex items-center gap-4">
            @csrf
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="check_enabled" value="1" @checked($updateCheckEnabled)
                       class="rounded border-gray-300 dark:border-gray-600 text-emerald-600 focus:ring-emerald-500">
                {{ __('Check for new releases') }}
            </label>
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">
                {{ __('Save') }}
            </button>
        </form>
    </div>

    <!-- Retention (auto-prune counts) -->
    @if(config('updater.enabled'))
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Retention') }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('How many old releases and backups to keep automatically after each successful update. Older ones are pruned to reclaim disk space. You can also delete items individually below.') }}</p>

            @if(session('status'))
                <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-sm text-green-800 dark:text-green-200">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.updates.retention.update') }}" class="flex flex-wrap items-end gap-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Keep releases') }}</label>
                    <input type="number" name="keep_releases" min="1" max="50" required value="{{ old('keep_releases', $keepReleases) }}"
                           class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Keep backups') }}</label>
                    <input type="number" name="keep_backups" min="1" max="50" required value="{{ old('keep_backups', $keepBackups) }}"
                           class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">
                    {{ __('Save') }}
                </button>
            </form>
            @error('keep_releases')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            @error('keep_backups')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    @endif

    <!-- Previous Releases (for rollback) -->
    @if(count($releases) > 0)
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Previous Releases') }}</h2>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Rollback to a previous version if needed') }}
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Available for rollback if needed. Rollback is instant and safe - your data is preserved.') }}</p>
            <div class="space-y-2">
                @foreach($releases as $release)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $release['version'] }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                {{ \Carbon\Carbon::createFromTimestamp($release['created_at'])->diffForHumans() }}
                            </span>
                            @isset($release['size'])
                                <span class="text-xs text-gray-400 dark:text-gray-500 ml-2">{{ number_format($release['size'] / 1048576, 1) }} MB</span>
                            @endisset
                            @if($release['version'] === $currentRelease)
                                <span class="ml-2 px-2 py-0.5 text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">
                                    {{ __('Current') }}
                                </span>
                            @endif
                        </div>
                        @if($release['version'] !== $currentRelease && config('updater.enabled'))
                            <div class="flex items-center gap-1">
                                <button @click="rollback('{{ $release['version'] }}')"
                                        class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded">
                                    {{ __('Rollback') }}
                                </button>
                                <button @click="deleteRelease('{{ $release['version'] }}')"
                                        class="px-3 py-1 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Backups -->
    @if(!empty($backups))
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Backups') }}</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Total') }}: {{ number_format(collect($backups)->sum('size') / 1048576, 1) }} MB
                </span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Automatic backups taken before each update (.env, database, storage). Delete old ones to reclaim disk space.') }}</p>
            <div class="space-y-2">
                @foreach($backups as $backup)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="min-w-0 mr-3">
                            <span class="text-sm text-gray-900 dark:text-white font-mono break-all">{{ $backup['filename'] }}</span>
                            <span class="ml-2 px-2 py-0.5 text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded">{{ $backup['type'] }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-2">{{ number_format($backup['size'] / 1048576, 2) }} MB</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-2">{{ \Carbon\Carbon::createFromTimestamp($backup['created_at'])->diffForHumans() }}</span>
                        </div>
                        @if(config('updater.enabled'))
                            <button @click="deleteBackup('{{ $backup['filename'] }}')"
                                    class="px-3 py-1 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded shrink-0">
                                {{ __('Delete') }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Release Notes (if available) -->
    @if($releaseNotes && $latestRelease && !empty($releaseNotes['formatted']))
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Release Notes') }}</h2>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('What\'s new in this release') }}
                </div>
            </div>
            <div class="prose dark:prose-invert max-w-none">
                <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                    @php
                        $parser = app(\App\Services\Update\ReleaseNotesParser::class);
                    @endphp
                    {{-- Safe to render raw here because parser sanitizes HTML input. --}}
                    {!! $parser->toHtml($releaseNotes['formatted']) !!}
                </div>
            </div>
        </div>
    @endif

    <!-- Update History -->
    @if(isset($updateHistory) && $updateHistory->count() > 0)
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Update History') }}</h2>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Complete audit log of all update attempts') }}
                </div>
            </div>
            <div class="space-y-2">
                @foreach($updateHistory as $log)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-900 dark:text-white font-medium">{{ $log->version }}</span>
                                <span class="px-2 py-0.5 text-xs rounded font-medium
                                    @if($log->status === 'success') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                    @elseif($log->status === 'failed') bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                                    @elseif($log->status === 'rolled_back') bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                                    @else bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-gray-200
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                @if($log->deployed_at)
                                    {{ __('Deployed') }}: {{ $log->deployed_at->diffForHumans() }}
                                    @if($log->deployedByUser)
                                        {{ __('by') }} {{ $log->deployedByUser->name }}
                                    @endif
                                @endif
                                @if($log->rollback_at)
                                    | {{ __('Rolled back') }}: {{ $log->rollback_at->diffForHumans() }}
                                    @if($log->rollbackByUser)
                                        {{ __('by') }} {{ $log->rollbackByUser->name }}
                                    @endif
                                @endif
                                @if($log->duration_seconds)
                                    | {{ __('Duration') }}: {{ $log->duration_seconds }}s
                                @endif
                            </div>
                            @if($log->error_message)
                                <div class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    {{ $log->error_message }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Manual Update Instructions -->
    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Manual Update (Tier 0)') }}</h2>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('Fallback method that works on all servers') }}
            </div>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ __('Manual ZIP update works on almost all hosting providers. This is the safest method if browser updates are not available.') }}
        </p>
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <strong>{{ __('Docker note') }}:</strong> {{ __('If you deploy with Docker, prefer image-based updates (pull + redeploy + migrate) rather than ZIP or browser updater flows.') }}
            </p>
        </div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                <strong>{{ __('Important') }}:</strong> {{ __('Always backup your .env file and database before updating manually. The automatic updater does this for you, but manual updates require you to do it yourself.') }}
            </p>
        </div>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700 dark:text-gray-300">
            <li>{{ __('Back up your .env file and database') }}</li>
            <li>{{ __('Download the latest release ZIP from GitHub') }}</li>
            <li>{{ __('Upload and extract it over your current installation') }}</li>
            <li>{{ __('Restore your .env file if it was overwritten') }}</li>
            <li>{{ __('Run migrations:') }} <code class="bg-gray-200 dark:bg-gray-800 px-1 rounded">php artisan migrate --force</code></li>
            <li>{{ __('Clear caches:') }} <code class="bg-gray-200 dark:bg-gray-800 px-1 rounded">php artisan optimize:clear</code></li>
        </ol>
        <a href="https://github.com/{{ config('updater.github_repo') }}/releases" 
           target="_blank"
           class="mt-4 inline-block px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium">
            {{ __('View All Releases on GitHub') }}
        </a>
    </div>

    <!-- Preview Results Modal -->
    <div x-show="previewResults" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="previewResults = null"
         @keydown.escape.window="previewResults = null">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
             @click.stop>
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Update Preview Results') }}</h3>
                    <button @click="previewResults = null" 
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div x-show="previewResults && previewResults.success" class="space-y-4">
                    <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <p class="text-green-800 dark:text-green-200">{{ __('Preview completed successfully. The update appears compatible.') }}</p>
                    </div>
                    <div x-show="previewResults.validation && previewResults.validation.results" class="space-y-2">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ __('Validation Results') }}</h4>
                        <div class="space-y-1 text-sm">
                            <template x-for="(result, key) in Object.entries(previewResults.validation.results || {})" :key="key">
                                <div class="flex items-center">
                                    <span x-show="result[1].passed" class="text-green-500 mr-2">✓</span>
                                    <span x-show="!result[1].passed" class="text-red-500 mr-2">✗</span>
                                    <span class="text-gray-700 dark:text-gray-300" x-text="result[1].message"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div x-show="previewResults && !previewResults.success" class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <p class="text-red-800 dark:text-red-200" x-text="previewResults.message"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateManager() {
    return {
        deploying: false,
        previewing: false,
        previewResults: null,
        
        async previewUpdate(version) {
            if (!confirm('{{ __("Preview update for version") }} ' + version + '? This will download and validate but not deploy.')) {
                return;
            }
            
            this.previewing = true;
            this.previewResults = null;
            
            try {
                const response = await fetch('{{ route("admin.updates.preview") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ version: version }),
                });
                
                const data = await response.json();
                this.previewResults = data;
                
                if (data.success) {
                    alert('{{ __("Preview completed. Check the results below.") }}');
                } else {
                    alert('{{ __("Preview failed:") }} ' + data.message);
                }
            } catch (error) {
                alert('{{ __("Preview failed:") }} ' + error.message);
            } finally {
                this.previewing = false;
            }
        },
        
        async deployUpdate(version) {
            if (!confirm('{{ __("Are you sure you want to update to version") }} ' + version + '?')) {
                return;
            }
            
            this.deploying = true;
            
            try {
                const response = await fetch('{{ route("admin.updates.deploy") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ version: version }),
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('{{ __("Update successful! The page will reload.") }}');
                    window.location.reload();
                } else {
                    alert('{{ __("Update failed:") }} ' + data.message);
                }
            } catch (error) {
                alert('{{ __("Update failed:") }} ' + error.message);
            } finally {
                this.deploying = false;
            }
        },
        
        async rollback(version) {
            if (!confirm('{{ __("Are you sure you want to rollback to version") }} ' + version + '?')) {
                return;
            }
            
            try {
                const response = await fetch('{{ route("admin.updates.rollback") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ version: version }),
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('{{ __("Rollback successful! The page will reload.") }}');
                    window.location.reload();
                } else {
                    alert('{{ __("Rollback failed:") }} ' + data.message);
                }
            } catch (error) {
                alert('{{ __("Rollback failed:") }} ' + error.message);
            }
        },

        async deleteRelease(version) {
            if (!confirm('{{ __("Permanently delete release") }} ' + version + '?')) {
                return;
            }

            try {
                const response = await fetch('{{ route("admin.updates.releases.delete") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ version: version }),
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert('{{ __("Delete failed:") }} ' + data.message);
                }
            } catch (error) {
                alert('{{ __("Delete failed:") }} ' + error.message);
            }
        },

        async deleteBackup(filename) {
            if (!confirm('{{ __("Permanently delete this backup?") }}')) {
                return;
            }

            try {
                const response = await fetch('{{ route("admin.updates.backups.delete") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ filename: filename }),
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert('{{ __("Delete failed:") }} ' + data.message);
                }
            } catch (error) {
                alert('{{ __("Delete failed:") }} ' + error.message);
            }
        },
    };
}
</script>
@endsection
