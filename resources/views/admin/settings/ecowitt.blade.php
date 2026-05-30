@extends('layouts.admin')

@section('title', __($groupInfo['label']))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __($groupInfo['label']) }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-{{ $groupInfo['color'] }}-100 dark:bg-{{ $groupInfo['color'] }}-900/30">
                <svg class="w-8 h-8 text-{{ $groupInfo['color'] }}-600 dark:text-{{ $groupInfo['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __($groupInfo['label']) }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __($groupInfo['description']) }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-.894.553l-3 6A1 1 0 007 14h6a1 1 0 00.894-1.447l-3-6A1 1 0 0010 6zm1 6a1 1 0 10-2 0 1 1 0 002 0zm-1 4a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Settings Form -->
    <form action="{{ route('admin.settings.update', $group) }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($settings as $setting)
                @php
                    $formKey = str_replace('.', '_', $setting->key);
                    $isApi = $setting->type === 'encrypted';
                    $displayLabel = __(ucwords(str_replace(['_', '.'], ' ', basename($setting->key))));
                    $displayDescription = $setting->description ? __($setting->description) : '';
                @endphp

                <div class="p-5">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label for="{{ $formKey }}" class="block text-sm font-medium text-gray-900 dark:text-white">
                                {{ $displayLabel }}
                                @if($isApi)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        {{ __('Encrypted') }}
                                    </span>
                                @endif
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $displayDescription }}</p>

                        <div class="w-full">
                            @switch($setting->type)
                                @case('boolean')
                                    <x-toggle-switch
                                        :enabled="$setting->getCastedValue()"
                                        :name="$formKey"
                                        :labelEnabled="__('Enabled')"
                                        :labelDisabled="__('Disabled')"
                                    />
                                    @break

                                @case('select')
                                    <select name="{{ $formKey }}"
                                            id="{{ $formKey }}"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                        @foreach($setting->getOptionsArray() as $optValue => $optLabel)
                                            <option value="{{ $optValue }}" {{ (string) $setting->value === (string) $optValue ? 'selected' : '' }}>
                                                {{ __($optLabel) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('textarea')
                                    <textarea name="{{ $formKey }}"
                                              id="{{ $formKey }}"
                                              rows="3"
                                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">{{ $setting->value }}</textarea>
                                    @break

                                @case('encrypted')
                                    <div class="relative">
                                        <input type="text"
                                               name="{{ $formKey }}"
                                               id="{{ $formKey }}"
                                               placeholder="{{ $setting->value ? __('(configured - enter new value to change)') : __('Enter API key') }}"
                                               autocomplete="off"
                                               data-lpignore="true"
                                               style="-webkit-text-security: disc; text-security: disc;"
                                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 pr-10 font-mono">
                                    </div>
                                    @if($setting->value)
                                        <p class="mt-1 text-xs text-green-600 dark:text-green-400">
                                            <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ __('Configured (leave empty to keep current value)') }}
                                        </p>
                                    @endif
                                    @break

                                @case('integer')
                                    <input type="number"
                                           name="{{ $formKey }}"
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    @break

                                @case('float')
                                    <input type="number"
                                           name="{{ $formKey }}"
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           step="0.000001"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    @break

                                @default
                                    <input type="text"
                                           name="{{ $formKey }}"
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            @endswitch
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                &larr; {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>

    <!-- Data Migration -->
    <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('Data Migration') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('Import data from an old PWS Dashboard / Ecowitt setup. Upload the .arr file that was generated by the previous installation.') }}
        </p>

        <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <p class="text-sm text-amber-800 dark:text-amber-200">
                {{ __('This imports a single data snapshot containing the latest reading from your old setup. It will be stored as a new weather reading.') }}
            </p>
        </div>

        <form action="{{ route('admin.settings.ecowitt.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="arr_file" class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                        {{ __('Ecowitt .arr File') }}
                    </label>
                    <input type="file"
                           name="arr_file"
                           id="arr_file"
                           accept=".arr"
                           required
                           class="block w-full text-sm text-gray-500 dark:text-gray-400
                                  file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-medium
                                  file:bg-green-50 file:text-green-700
                                  dark:file:bg-green-900/30 dark:file:text-green-400
                                  hover:file:bg-green-100 dark:hover:file:bg-green-900/50
                                  cursor-pointer" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Accepted: .arr files up to 2 MB') }}
                    </p>
                </div>
                <div>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        {{ __('Import Data') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
