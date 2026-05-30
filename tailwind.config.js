import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                weather: {
                    dark: '#0f1419',
                    card: '#1a2332',
                    accent: '#3b82f6',
                    warm: '#f59e0b',
                    cold: '#06b6d4',
                    rain: '#6366f1',
                }
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['JetBrains Mono', 'monospace'],
            },
        },
    },

    plugins: [forms],
};
