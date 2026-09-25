@extends('layouts.admin')

@section('title', __('Choose your data source'))

@section('content')
<div class="max-w-3xl space-y-6">

    <div class="space-y-3">
        @include('admin.setup._progress')
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Where do the readings come from?') }}</h1>
        <p class="text-gray-600 dark:text-gray-300">
            {{ __('Pick the station or software that sends the data. The next page asks for whatever that one needs, such as a key or an address.') }}
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

    <form method="POST" action="{{ route('admin.setup.source.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($formats as $key => $label)
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-500 cursor-pointer transition-colors">
                        <input type="radio" name="format" value="{{ $key }}"
                               {{ old('format', $current) === $key ? 'checked' : '' }}
                               class="mt-1 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-900 dark:text-white">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                {{ __('Not sure yet? Anything here can be changed later under Settings, Live Data.') }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div>
                <h2 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Which kit is it?') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Optional, and only shown to others if you put your station on the community map.') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="manufacturer" class="block text-sm text-gray-700 dark:text-gray-200 mb-1">{{ __('Maker') }}</label>
                    <select name="manufacturer" id="manufacturer"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        @foreach($manufacturers as $key => $label)
                            <option value="{{ $key }}" {{ old('manufacturer', $manufacturer) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="hardware" class="block text-sm text-gray-700 dark:text-gray-200 mb-1">{{ __('Model') }}</label>
                    <input type="text" name="hardware" id="hardware" maxlength="255"
                           value="{{ old('hardware', $hardware) }}"
                           placeholder="{{ __('for example WS2900 or Vantage Pro2') }}"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.setup.station') }}"
                   class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    {{ __('Back') }}
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors">
                    {{ __('Finish') }}
                </button>
            </div>
            <button type="submit" form="setup-skip"
                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                {{ __('I will do this later') }}
            </button>
        </div>
    </form>

    <form id="setup-skip" method="POST" action="{{ route('admin.setup.skip') }}" class="hidden">@csrf</form>
</div>

@push('scripts')
<script>
(function () {
    // Picking a format is a decent guess at the maker, so fill it in. Only
    // while the field is still untouched: plenty of people run one company's
    // hardware through another's software.
    const makerFor = @json($manufacturerForFormat);
    const maker = document.getElementById('manufacturer');
    if (!maker || maker.value !== '') return;

    document.querySelectorAll('input[name="format"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (maker.dataset.touched) return;
            const guess = makerFor[radio.value] ?? '';
            if ([...maker.options].some((o) => o.value === guess)) {
                maker.value = guess;
            }
        });
    });

    maker.addEventListener('change', () => { maker.dataset.touched = '1'; });
})();
</script>
@endpush
@endsection
