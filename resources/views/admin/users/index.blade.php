@extends('layouts.admin')

@section('title', __('User Management'))

@section('content')
<!-- Registration Settings Card -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Registration Settings') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Control whether new users can register on the site') }}</p>
    </div>
    <div class="px-6 py-4">
        <form action="{{ route('admin.users.toggle-registration') }}" method="POST" class="flex items-center justify-between">
            @csrf
            <div>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Allow User Registration') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if($registrationEnabled)
                        {{ __('New users can create accounts via the registration page.') }}
                    @else
                        {{ __('Registration is disabled. Only admins can create new users.') }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-4">
                <x-toggle-switch 
                    name="enabled" 
                    :enabled="$registrationEnabled" 
                    labelEnabled="{{ __('Enabled') }}"
                    labelDisabled="{{ __('Disabled') }}"
                />
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition font-medium">
                    {{ __('Save') }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users List Card -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Users') }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Manage user accounts and permissions') }}</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
            {{ __('Add User') }}
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Email') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Role') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Created') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($users as $user)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                <span class="text-lg font-medium text-gray-600 dark:text-gray-300">{{ substr($user->name, 0, 1) }}</span>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $user->email }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($user->is_admin)
                            <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded-full">{{ __('Admin') }}</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 rounded-full">{{ __('User') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $user->created_at->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">{{ __('Edit') }}</a>
                        @if($user->id !== auth()->id())
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm(@json(__('Are you sure you want to delete this user?')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
