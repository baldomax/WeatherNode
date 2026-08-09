@if(\App\Support\DockerDatabaseLayout::isLegacy())
    <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl" role="status">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                    {{ __('Docker data volume uses the old layout') }}
                </h3>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                    {{ __('Your database is at :path, inside the application directory. A volume mounted there also covers the migrations folder, which Docker only copies from the image once, so upgrades used to stop applying database changes.', ['path' => \App\Support\DockerDatabaseLayout::currentPath()]) }}
                </p>
                <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">
                    {{ __('Nothing is broken: migrations are applied from a copy inside the image that no volume can cover. Correcting the layout is optional cleanup. In docker-compose.yml:') }}
                </p>
                <pre class="mt-2 p-3 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-xs text-amber-900 dark:text-amber-200 overflow-x-auto"><code>volumes:
  - db_data:/var/lib/weathernode

environment:
  DB_DATABASE: "{{ \App\Support\DockerDatabaseLayout::RECOMMENDED_PATH }}"</code></pre>
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                    {{ __('Nothing is copied or moved. It is the same volume mounted at a different path.') }}
                </p>
            </div>
        </div>
    </div>
@endif
