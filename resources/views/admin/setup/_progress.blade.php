<ol class="flex items-center gap-3 text-sm" aria-label="{{ __('Setup progress') }}">
    @foreach([1 => __('Station'), 2 => __('Data source')] as $number => $label)
        <li class="flex items-center gap-2">
            <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                {{ $step === $number
                    ? 'bg-blue-600 text-white'
                    : ($step > $number ? 'bg-green-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400') }}">
                {{ $step > $number ? '✓' : $number }}
            </span>
            <span class="{{ $step === $number ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $label }}
            </span>
        </li>
        @if($number === 1)
            <li aria-hidden="true" class="w-8 border-t border-gray-300 dark:border-gray-600"></li>
        @endif
    @endforeach
</ol>
