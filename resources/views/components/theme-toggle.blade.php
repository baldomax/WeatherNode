@props(['serverTheme' => 'dark'])

<div x-data="{
    darkMode: false,
    serverTheme: '{{ $serverTheme }}',

    init() {
        // Priority: localStorage > server setting > system preference
        const storedTheme = localStorage.getItem('theme');

        if (storedTheme) {
            // User has manually overridden via toggle
            this.darkMode = storedTheme === 'dark';
        } else if (this.serverTheme === 'user') {
            // Server says follow system preference
            this.darkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
        } else {
            // Server has explicit dark/light setting
            this.darkMode = this.serverTheme === 'dark';
        }

        this.updateTheme();

        // Watch for system theme changes when in 'user' mode and no localStorage override
        if (this.serverTheme === 'user') {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    this.darkMode = e.matches;
                    this.updateTheme();
                }
            });
        }
    },

    toggleTheme() {
        this.darkMode = !this.darkMode;
        this.updateTheme();
        // Save user override (takes precedence over server setting)
        localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
    },

    updateTheme() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
}" x-init="init()" class="flex items-center">
    <button
        @click="toggleTheme()"
        type="button"
        class="p-2 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
        :aria-label="darkMode ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
    >
        <!-- Sun Icon (visible in dark mode) -->
        <svg
            x-show="darkMode"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-75"
            x-transition:enter-end="opacity-100 scale-100"
            class="w-6 h-6 text-yellow-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>

        <!-- Moon Icon (visible in light mode) -->
        <svg
            x-show="!darkMode"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-75"
            x-transition:enter-end="opacity-100 scale-100"
            class="w-6 h-6 text-gray-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
    </button>
</div>
