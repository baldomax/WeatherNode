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
</script>
@endsection
