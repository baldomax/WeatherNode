@extends('layouts.admin')

@section('title', __('API Keys'))

@section('content')
<div class="mb-6">
    <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ __('API Keys') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Manage API access for the site and external clients.') }}</p>
</div>

@if(session('success'))
    <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-800 dark:text-green-200">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-800 dark:text-red-200">
        {{ session('error') }}
    </div>
@endif

@if(!$tableReady)
    <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl text-sm text-yellow-800 dark:text-yellow-200">
        {{ __('API key table is missing. Run migrations to enable API key management.') }}
    </div>
@else
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700/50 p-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Site API Key') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Used by the browser to call internal APIs. This is auto-generated on first load.') }}
        </p>
        <div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-center">
            <input type="password" id="public_api_key" value="{{ $publicKey ?? '' }}" readonly
                   class="w-full sm:flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <div class="flex gap-2">
                <button type="button" onclick="toggleApiKeyVisibility('public_api_key')"
                        class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">
                    {{ __('Show') }}
                </button>
                <button type="button" onclick="copyApiKey('public_api_key')"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                    {{ __('Copy') }}
                </button>
            </div>
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700/50 p-5">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Create API Key') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Create keys for external clients or scripts. Generated keys are shown only once.') }}
        </p>

        @if(session('created_key'))
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-900 dark:text-blue-100">
                <div class="font-semibold">{{ __('New API Key Created') }}: {{ session('created_name') }}</div>
                <div class="mt-2 flex flex-col sm:flex-row gap-2 sm:items-center">
                    <input type="text" id="created_api_key" value="{{ session('created_key') }}" readonly
                           class="w-full sm:flex-1 rounded-lg border-blue-200 dark:border-blue-700 dark:bg-gray-800 dark:text-white">
                    <button type="button" onclick="copyApiKey('created_api_key')"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">
                        {{ __('Copy') }}
                    </button>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.api-keys.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf
            <div class="md:col-span-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Name') }}</label>
                <input type="text" name="name" required
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
            <div class="md:col-span-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Rate Limit (per minute)') }}</label>
                <input type="number" name="rate_limit_per_minute" min="1" max="6000"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Leave blank for default limit.') }}</p>
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
                <input type="checkbox" id="is_public" name="is_public" value="1"
                       class="rounded border-gray-300 dark:border-gray-600">
                <label for="is_public" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Use for browser (public) requests') }}
                </label>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm">
                    {{ __('Create API Key') }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700/50">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/50">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Existing API Keys') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">{{ __('Name') }}</th>
                        <th class="px-5 py-3 text-left font-medium">{{ __('Prefix') }}</th>
                        <th class="px-5 py-3 text-left font-medium">{{ __('Rate Limit') }}</th>
                        <th class="px-5 py-3 text-left font-medium">{{ __('Last Used') }}</th>
                        <th class="px-5 py-3 text-left font-medium">{{ __('Status') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($keys as $key)
                        <tr>
                            <td class="px-5 py-3 text-gray-900 dark:text-white">
                                {{ $key->name }}
                                @if($key->is_public)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Public') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $key->key_prefix }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                {{ $key->rate_limit_per_minute ? $key->rate_limit_per_minute . ' / min' : __('Default') }}
                            </td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                {{ $key->last_used_at ? $key->last_used_at->diffForHumans() : __('Never') }}
                            </td>
                            <td class="px-5 py-3">
                                @if($key->revoked_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ __('Revoked') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ __('Active') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if(!$key->revoked_at)
                                    <form method="POST" action="{{ route('admin.api-keys.revoke', $key) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-700">
                                            {{ __('Revoke') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-4 text-center text-gray-500 dark:text-gray-400">
                                {{ __('No API keys found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<section class="mt-8 space-y-5" aria-labelledby="api-guide-title">
    <div class="overflow-hidden rounded-xl border border-sky-200 bg-white shadow-sm dark:border-sky-900/70 dark:bg-gray-800">
        <div class="border-b border-sky-100 bg-gradient-to-r from-sky-50 to-blue-50 px-5 py-5 dark:border-sky-900/70 dark:from-sky-950/40 dark:to-blue-950/30">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">
                        <span class="inline-block h-2 w-2 rounded-full bg-sky-500"></span>
                        {{ __('Developer guide') }}
                    </div>
                    <h2 id="api-guide-title" class="text-xl font-bold text-gray-900 dark:text-white">{{ __('Using the WeatherNode API') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        {{ __('Read station data from scripts, dashboards, and home automation tools. Replace weather.example.com with the address of your own installation. Start with a public key. Use a private key only for trusted server-side integrations that need protected data sources.') }}
                    </p>
                </div>
                <a href="https://github.com/{{ config('updater.github_repo') }}/blob/main/docs/API.md"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-medium text-sky-700 transition hover:border-sky-400 hover:bg-sky-50 dark:border-sky-700 dark:bg-gray-800 dark:text-sky-300 dark:hover:bg-sky-950/40">
                    {{ __('Open full API reference') }}
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h4m0 0v4m0-4L10 14M7 7H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2"/>
                    </svg>
                </a>
            </div>
        </div>

        <div class="grid gap-px bg-gray-100 dark:bg-gray-700/60 md:grid-cols-3">
            <div class="bg-white p-5 dark:bg-gray-800">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Base URL') }}</div>
                <code class="mt-2 block break-all text-sm font-medium text-gray-900 dark:text-gray-100">https://weather.example.com/api</code>
            </div>
            <div class="bg-white p-5 dark:bg-gray-800">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Authentication') }}</div>
                <code class="mt-2 block text-sm font-medium text-gray-900 dark:text-gray-100">X-API-Key: YOUR_API_KEY</code>
            </div>
            <div class="bg-white p-5 dark:bg-gray-800">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Default limit') }}</div>
                <div class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">120 {{ __('requests per minute') }}</div>
            </div>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700/50 dark:bg-gray-800 xl:col-span-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Quick test') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Replace the placeholder with one of your keys, then run this command in a terminal.') }}</p>
                </div>
                <button type="button"
                        onclick="copyCode('api-curl-example', this)"
                        class="shrink-0 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    {{ __('Copy') }}
                </button>
            </div>
            <pre id="api-curl-example" class="mt-4 overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>curl --fail --silent --show-error \
  -H "Accept: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  "https://weather.example.com/api/weather/current"</code></pre>
            <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">
                {{ __('A successful response contains the latest reading in the data object. Sensor fields can be null when that sensor is not available.') }}
            </p>
        </div>

        <aside class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/60 dark:bg-amber-950/20 xl:col-span-2">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-200">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.105.895-2 2-2s2 .895 2 2v2m-4-2V7a4 4 0 118 0v4m-9 10h10a2 2 0 002-2v-6a2 2 0 00-2-2H11a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                </svg>
                {{ __('Choose the right key') }}
            </h3>
            <div class="mt-3 space-y-3 text-sm leading-6 text-amber-900/90 dark:text-amber-100/80">
                <p><strong>{{ __('Public key:') }}</strong> {{ __('Use for weather, radar, and public air quality endpoints. This is enough for Home Assistant and most dashboards.') }}</p>
                <p><strong>{{ __('Private key:') }}</strong> {{ __('Use only on trusted systems that need protected provider, telemetry, or forecast narration endpoints.') }}</p>
                <p class="text-xs">{{ __('Create a separate key for each integration so it can be revoked without affecting anything else.') }}</p>
            </div>
        </aside>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700/50 dark:bg-gray-800">
        <details open class="group border-b border-gray-100 dark:border-gray-700/60">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-700/30">
                <span>{{ __('Public endpoint overview') }}</span>
                <svg class="h-5 w-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100 px-5 py-5 dark:border-gray-700/60">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="pb-3 pr-6 font-semibold">{{ __('Endpoint') }}</th>
                                <th class="pb-3 font-semibold">{{ __('Returns') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr><td class="py-3 pr-6"><code>/api/weather/current</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Latest station reading') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/weather/today</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Daily minimum, maximum, rain, and wind summary') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/weather/forecast</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Daily and hourly forecast') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/weather/history</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Sampled historical values for charts and automations') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/weather/air-quality</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Configured air quality readings') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/weather/astronomy</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Sun, moon, meteor, aurora, and space data') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/weather/dashboard</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Combined payload used by the public dashboard') }}</td></tr>
                            <tr><td class="py-3 pr-6"><code>/api/radar/frames</code></td><td class="py-3 text-gray-600 dark:text-gray-300">{{ __('Radar frame metadata') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('The full reference includes all weather, WMS, radar, receiver, data source, telemetry, and narration endpoints.') }}
                </p>
            </div>
        </details>

        <details class="group border-b border-gray-100 dark:border-gray-700/60">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-700/30">
                <span>{{ __('Home Assistant example') }}</span>
                <svg class="h-5 w-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100 px-5 py-5 dark:border-gray-700/60">
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ __('Home Assistant can read WeatherNode with its built-in REST integration. Put the key in secrets.yaml and add this to configuration.yaml.') }}
                </p>
                <div class="mt-4 flex justify-end">
                    <button type="button"
                            onclick="copyCode('home-assistant-example', this)"
                            class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                        {{ __('Copy') }}
                    </button>
                </div>
                <pre id="home-assistant-example" class="mt-2 overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>rest:
  - resource: https://weather.example.com/api/weather/current
    headers:
      X-API-Key: !secret weathernode_api_key
      Accept: application/json
    scan_interval: 60
    sensor:
      - name: WeatherNode temperature
        unique_id: weathernode_temperature
        value_template: "@{{ value_json.data.temperature }}"
        unit_of_measurement: "°C"
        device_class: temperature
        state_class: measurement
      - name: WeatherNode humidity
        unique_id: weathernode_humidity
        value_template: "@{{ value_json.data.humidity }}"
        unit_of_measurement: "%"
        device_class: humidity
        state_class: measurement</code></pre>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <code>secrets.yaml</code>: <code>weathernode_api_key: YOUR_API_KEY</code>
                </p>
            </div>
        </details>

        <details class="group border-b border-gray-100 dark:border-gray-700/60">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-700/30">
                <span>{{ __('Node-RED and Telegraf') }}</span>
                <svg class="h-5 w-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100 px-5 py-5 dark:border-gray-700/60">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Node-RED</h4>
                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ __('Set an HTTP request node to GET the current endpoint and add these headers in a function node.') }}</p>
                        <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>msg.url = "https://weather.example.com/api/weather/current";
msg.headers = {
  "Accept": "application/json",
  "X-API-Key": env.get("WEATHERNODE_API_KEY")
};
return msg;</code></pre>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Telegraf</h4>
                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ __('Use the HTTP input plugin with JSON v2 parsing. Keep the key in the Telegraf service environment.') }}</p>
                        <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>[[inputs.http]]
  urls = ["https://weather.example.com/api/weather/current"]
  interval = "60s"
  data_format = "json_v2"

  [inputs.http.headers]
    X-API-Key = "${WEATHERNODE_API_KEY}"

  [[inputs.http.json_v2.object]]
    path = "data"</code></pre>
                    </div>
                </div>
            </div>
        </details>

        <details class="group">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-700/30">
                <span>{{ __('Errors, limits, and safe use') }}</span>
                <svg class="h-5 w-5 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100 px-5 py-5 dark:border-gray-700/60">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Common responses') }}</h4>
                        <ul class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            <li><code>401</code> {{ __('Key missing, invalid, or revoked') }}</li>
                            <li><code>403</code> {{ __('Private endpoint called with a public key') }}</li>
                            <li><code>429</code> {{ __('Rate limit reached. Check the Retry-After header.') }}</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Good practice') }}</h4>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                            <li>{{ __('Keep private keys out of browser code and URLs.') }}</li>
                            <li>{{ __('Poll current conditions about once per minute unless your station updates less often.') }}</li>
                            <li>{{ __('Check the success field because unavailable services can still return HTTP 200.') }}</li>
                            <li>{{ __('API weather values use canonical metric units.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </details>
    </div>
</section>

<script>
function toggleApiKeyVisibility(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function copyApiKey(id) {
    const input = document.getElementById(id);
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
}

function copyCode(id, button) {
    const code = document.getElementById(id);
    if (!code) return;

    navigator.clipboard.writeText(code.textContent).then(() => {
        const originalText = button.textContent;
        button.textContent = @json(__('Copied'));
        window.setTimeout(() => {
            button.textContent = originalText;
        }, 1500);
    });
}
</script>
@endsection
