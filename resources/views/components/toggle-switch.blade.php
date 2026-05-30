@props([
    'enabled' => false,
    'name' => '',
    'labelEnabled' => 'Enabled',
    'labelDisabled' => 'Disabled',
    'showLabel' => true,
])

<div x-data="{
    toggleEnabled: {{ $enabled ? 'true' : 'false' }}
}" class="flex items-center">
    <input type="hidden" name="{{ $name }}" x-bind:value="toggleEnabled ? '1' : '0'">

    <!-- iOS-Style Toggle Button -->
    <button
        type="button"
        @click="toggleEnabled = !toggleEnabled"
        :class="toggleEnabled ? 'bg-green-500 dark:bg-green-600' : 'bg-gray-300 dark:bg-gray-600'"
        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-500 dark:focus:ring-green-400 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
        role="switch"
        :aria-checked="toggleEnabled.toString()"
        :aria-label="toggleEnabled ? '{{ $labelEnabled }}' : '{{ $labelDisabled }}'"
    >
        <!-- Slider Circle -->
        <span
            :class="toggleEnabled ? 'translate-x-5' : 'translate-x-0'"
            class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow-lg ring-0 transition duration-300 ease-in-out"
        ></span>
    </button>

    <!-- Label Text -->
    @if($showLabel)
        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300"
              x-text="toggleEnabled ? '{{ $labelEnabled }}' : '{{ $labelDisabled }}'">
        </span>
    @endif
</div>
