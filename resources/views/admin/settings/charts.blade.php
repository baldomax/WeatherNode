@extends('layouts.admin')

@section('title', __('History Charts'))

@section('content')
<div class="w-full" x-data="chartManager()">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('History Charts') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('History Charts') }}</h1>
        <p class="mt-1 text-gray-500 dark:text-gray-400">{{ __('Choose which charts appear on the daily history page. Sensor charts only appear when your station has the corresponding sensor connected.') }}</p>
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

    <form action="{{ route('admin.settings.charts.update') }}" method="POST" id="chartForm">
        @csrf

        <!-- Core Charts Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Core Charts') }}</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Always available') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($availableCharts as $chartId => $chart)
                    @if($chart['category'] === 'core')
                        <div class="group flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-xl border transition-all"
                             :class="isEnabled('{{ $chartId }}') ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'">

                            <!-- Chart Icon -->
                            <div class="flex-shrink-0 p-2 rounded-lg transition-colors"
                                 :class="isEnabled('{{ $chartId }}') ? 'bg-violet-100 dark:bg-violet-800' : 'bg-gray-100 dark:bg-gray-700'">
                                @include('admin.settings._chart-icon', ['icon' => $chart['icon'], 'chartId' => $chartId])
                            </div>

                            <!-- Chart Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ __($chart['label']) }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-snug">{{ __($chart['description']) }}</p>
                            </div>

                            <!-- Toggle Switch -->
                            <div class="flex-shrink-0">
                                <button type="button"
                                        @click="toggleChart('{{ $chartId }}')"
                                        :class="isEnabled('{{ $chartId }}') ? 'bg-violet-600' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                                    <span class="sr-only">{{ __('Toggle') }} {{ __($chart['label']) }}</span>
                                    <span :class="isEnabled('{{ $chartId }}') ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                        <span :class="isEnabled('{{ $chartId }}') ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                              class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span :class="isEnabled('{{ $chartId }}') ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                              class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                                            <svg class="h-3 w-3 text-violet-600" fill="currentColor" viewBox="0 0 12 12">
                                                <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                            </svg>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Sensor Charts Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Sensor Charts') }}</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Conditional on sensor data') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($availableCharts as $chartId => $chart)
                    @if($chart['category'] === 'sensor')
                        <div class="group flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-xl border transition-all"
                             :class="isEnabled('{{ $chartId }}') ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'">

                            <!-- Chart Icon -->
                            <div class="flex-shrink-0 p-2 rounded-lg transition-colors"
                                 :class="isEnabled('{{ $chartId }}') ? 'bg-violet-100 dark:bg-violet-800' : 'bg-gray-100 dark:bg-gray-700'">
                                @include('admin.settings._chart-icon', ['icon' => $chart['icon'], 'chartId' => $chartId])
                            </div>

                            <!-- Chart Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ __($chart['label']) }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-snug">{{ __($chart['description']) }}</p>
                            </div>

                            <!-- Toggle Switch -->
                            <div class="flex-shrink-0">
                                <button type="button"
                                        @click="toggleChart('{{ $chartId }}')"
                                        :class="isEnabled('{{ $chartId }}') ? 'bg-violet-600' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                                    <span class="sr-only">{{ __('Toggle') }} {{ __($chart['label']) }}</span>
                                    <span :class="isEnabled('{{ $chartId }}') ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                        <span :class="isEnabled('{{ $chartId }}') ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                              class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span :class="isEnabled('{{ $chartId }}') ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                              class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity">
                                            <svg class="h-3 w-3 text-violet-600" fill="currentColor" viewBox="0 0 12 12">
                                                <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                            </svg>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Hidden inputs for form submission -->
        <template x-for="chartId in enabledCharts" :key="'input-' + chartId">
            <input type="hidden" name="enabled_charts[]" :value="chartId">
        </template>

        <!-- Actions -->
        <div class="mt-8 flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                &larr; {{ __('Back to Settings') }}
            </a>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-500 dark:text-gray-400" x-show="hasChanges">
                    {{ __('Unsaved changes') }}
                </span>
                <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg transition shadow-sm">
                    {{ __('Save Chart Configuration') }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function chartManager() {
    return {
        enabledCharts: @json($enabledCharts),
        hasChanges: false,

        isEnabled(chartId) {
            return this.enabledCharts.includes(chartId);
        },

        toggleChart(chartId) {
            const index = this.enabledCharts.indexOf(chartId);
            if (index > -1) {
                this.enabledCharts.splice(index, 1);
            } else {
                this.enabledCharts.push(chartId);
            }
            this.hasChanges = true;
        }
    };
}
</script>
@endsection
