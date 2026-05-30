import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin.js',
                'resources/js/pages/dashboard.js',
                'resources/js/pages/day-charts.js',
                'resources/js/pages/history-charts.js',
                'resources/js/pages/statistics-charts.js',
                'resources/js/pages/aviation.js',
                'resources/js/pages/fire-weather-charts.js',
                'resources/js/pages/pollen-charts.js',
                'resources/js/pages/tide-charts.js',
                'resources/js/pages/wave-charts.js',
            ],
            refresh: true,
        }),
    ],
});
