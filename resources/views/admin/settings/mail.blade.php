@extends('layouts.admin')

@section('title', __('Mail Settings'))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Mail Settings') }}</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Mail Settings') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Configure email provider (OAuth2 or SMTP)') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.settings.mail.update') }}" method="POST" class="space-y-6" x-data="mailSettings()" x-init="init()">
        @csrf
        
        <!-- Provider Selection -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Email Provider') }}</label>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Select your email provider. OAuth2 is required for Gmail and Microsoft.') }}</p>
            </div>
            <div class="p-5">
                <select x-model="provider" @change="onProviderChange()" name="provider" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                    <optgroup label="{{ __('OAuth2 Providers') }}">
                        <option value="gmail_oauth2" @selected($currentProvider === 'gmail_oauth2')>{{ __('Gmail (OAuth2)') }}</option>
                        <option value="microsoft_oauth2" @selected($currentProvider === 'microsoft_oauth2')>{{ __('Microsoft/Office 365 (OAuth2)') }}</option>
                    </optgroup>
                    <optgroup label="{{ __('Predefined SMTP Providers') }}">
                        <option value="brevo" @selected($currentProvider === 'brevo')>{{ __('Brevo') }} (EU)</option>
                        <option value="mailjet" @selected($currentProvider === 'mailjet')>{{ __('Mailjet') }} (EU)</option>
                        <option value="postmark" @selected($currentProvider === 'postmark')>{{ __('Postmark') }}</option>
                        <option value="mailgun" @selected($currentProvider === 'mailgun')>{{ __('Mailgun') }}</option>
                        <option value="smtp2go" @selected($currentProvider === 'smtp2go')>{{ __('SMTP2Go') }} (Asia)</option>
                    </optgroup>
                    <optgroup label="{{ __('Custom') }}">
                        <option value="smtp_custom" @selected($currentProvider === 'smtp_custom')>{{ __('Custom SMTP') }}</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <!-- OAuth2 Configuration -->
        <div x-show="isOAuth2Provider()" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('OAuth2 Configuration') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Configure OAuth2 credentials and authorize access') }}</p>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client ID') }}</label>
                    <input type="text" name="oauth2_client_id" value="{{ $oauth2Credentials['client_id'] ?? '' }}" x-model="oauth2.client_id" 
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="{{ __('Enter OAuth2 Client ID') }}">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <template x-if="provider === 'gmail_oauth2'">
                            <span>{{ __('Get from') }} <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Google Cloud Console</a></span>
                        </template>
                        <template x-if="provider === 'microsoft_oauth2'">
                            <span>{{ __('Get from') }} <a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" class="text-blue-600 hover:underline">Azure Portal</a></span>
                        </template>
                    </p>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client Secret') }}</label>
                    <input type="text" name="oauth2_client_secret" x-model="oauth2.client_secret" 
                           autocomplete="off"
                           data-lpignore="true"
                           style="-webkit-text-security: disc; text-security: disc;"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500 font-mono"
                           placeholder="{{ __('Enter OAuth2 Client Secret') }}">
                </div>

                <div x-show="oauth2.refresh_token">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Refresh Token') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="text" value="{{ $oauth2Credentials['refresh_token'] ?? '' }}" readonly
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm opacity-75">
                        <span class="text-xs text-green-600 dark:text-green-400">✓ {{ __('Configured') }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a :href="getOAuthUrl()" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        <template x-if="provider === 'gmail_oauth2'">
                            <span>{{ __('Authorize with Gmail') }}</span>
                        </template>
                        <template x-if="provider === 'microsoft_oauth2'">
                            <span>{{ __('Authorize with Microsoft') }}</span>
                        </template>
                    </a>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        {{ __('Click to authorize and get refresh token. Make sure Client ID and Secret are saved first.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Predefined SMTP Configuration -->
        <div x-show="isPredefinedSMTP()" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('SMTP Configuration') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="getProviderDescription()"></p>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Host') }}</label>
                        <input type="text" :value="getProviderHost()" readonly
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm opacity-75">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Port') }}</label>
                        <input type="text" :value="getProviderPort()" readonly
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm opacity-75">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Encryption') }}</label>
                        <input type="text" :value="getProviderEncryption()" readonly
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm opacity-75">
                    </div>
                </div>

                <div class="pt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-xs text-blue-800 dark:text-blue-200">
                        <strong>{{ __('Free tier') }}:</strong> <span x-text="getFreeTier()"></span>
                    </p>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                        <a :href="getDocumentationUrl()" target="_blank" class="hover:underline">{{ __('View documentation') }}</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- SMTP Credentials (for both predefined and custom) -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('SMTP Credentials') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="!isOAuth2Provider()">{{ __('Enter your SMTP credentials') }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="isOAuth2Provider()">{{ __('SMTP credentials are not used for OAuth2 providers') }}</p>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <span x-text="isPredefinedSMTP() ? getUsernameLabel() : '{{ __('SMTP Username') }}'"></span>
                        <span class="text-red-500" x-show="isPredefinedSMTP()"> *</span>
                    </label>
                    <input type="text" name="smtp_username" 
                           value="{{ old('smtp_username', $smtpSettings['username'] ?? '') }}" 
                           :disabled="isOAuth2Provider()"
                           :required="isPredefinedSMTP()"
                           :placeholder="isPredefinedSMTP() ? getUsernamePlaceholder() : '{{ __('Enter SMTP username') }}'"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="isPredefinedSMTP()">
                        <a :href="getApiKeyUrl()" target="_blank" class="text-blue-600 hover:underline">{{ __('Get credentials') }}</a>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="isPredefinedSMTP() && getProviderConfig() && getProviderConfig().username_hint" x-text="getProviderConfig().username_hint"></p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <span x-text="isPredefinedSMTP() ? getPasswordLabel() : '{{ __('SMTP Password') }}'"></span>
                        <span class="text-gray-500" x-show="!isPredefinedSMTP() || !isPasswordRequired()"> ({{ __('optional') }})</span>
                        <span class="text-red-500" x-show="isPredefinedSMTP() && isPasswordRequired()"> *</span>
                    </label>
                    <input type="text" name="smtp_password" 
                           autocomplete="off"
                           data-lpignore="true"
                           style="-webkit-text-security: disc; text-security: disc;"
                           :disabled="isOAuth2Provider()"
                           :placeholder="isPredefinedSMTP() ? getPasswordPlaceholder() : '{{ __('Enter SMTP password (leave blank to keep existing)') }}'"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500 font-mono disabled:opacity-50 disabled:cursor-not-allowed">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="provider === 'mailgun'">{{ __('Username format') }}: postmaster@your-domain.mailgun.org</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="isPredefinedSMTP() && isPasswordRequired()">
                        {{ __('This field is required for this provider. Leave blank to keep existing password.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Custom SMTP Configuration -->
        <div x-show="provider === 'smtp_custom'" x-transition class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Custom SMTP Configuration') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Configure SMTP settings manually') }}</p>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Host') }}</label>
                        <input type="text" name="smtp_host" value="{{ $smtpSettings['host'] ?? '' }}" 
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Port') }}</label>
                        <input type="number" name="smtp_port" value="{{ $smtpSettings['port'] ?? '587' }}" 
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="587">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Encryption') }}</label>
                        <select name="smtp_encryption" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="tls" {{ ($smtpSettings['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ ($smtpSettings['encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="" {{ ($smtpSettings['encryption'] ?? '') === '' ? 'selected' : '' }}>{{ __('None') }}</option>
                        </select>
                    </div>
                </div>

            </div>
        </div>

        <!-- Test Email -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Test Email') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Send a test email to verify configuration') }}</p>
            </div>
            <div class="p-5">
                <div class="flex items-center gap-3">
                    <input type="email" name="test_email" 
                           value="{{ \App\Models\Setting::getValue('notifications.email', auth()->user()->email) }}"
                           class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="{{ __('Enter email address') }}">
                    <button type="submit" formaction="{{ route('admin.settings.mail.test') }}" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm transition">
                        {{ __('Send Test Email') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" formaction="{{ route('admin.settings.mail.update') }}" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function mailSettings() {
    return {
        provider: '{{ $currentProvider }}',
        oauth2: {
            client_id: '{{ $oauth2Credentials['client_id'] ?? '' }}',
            client_secret: '',
            refresh_token: '{{ $oauth2Credentials['refresh_token'] ?? '' }}'
        },
        smtp: {
            username: '{{ $smtpSettings['username'] ?? '' }}',
            password: ''
        },
        providerConfigs: @json(config('mail_providers')),

        init() {
            // Initialize with current provider
        },

        isOAuth2Provider() {
            return ['gmail_oauth2', 'microsoft_oauth2'].includes(this.provider);
        },

        isPredefinedSMTP() {
            return ['brevo', 'mailjet', 'postmark', 'mailgun', 'smtp2go'].includes(this.provider);
        },

        onProviderChange() {
            // Reset form when provider changes
            if (!this.isOAuth2Provider()) {
                this.oauth2 = { client_id: '', client_secret: '', refresh_token: '' };
            }
        },

        getProviderConfig() {
            return this.providerConfigs[this.provider] || null;
        },

        getProviderHost() {
            const config = this.getProviderConfig();
            return config ? config.host : '';
        },

        getProviderPort() {
            const config = this.getProviderConfig();
            return config ? config.port : '';
        },

        getProviderEncryption() {
            const config = this.getProviderConfig();
            return config ? config.encryption.toUpperCase() : '';
        },

        getUsernameLabel() {
            const config = this.getProviderConfig();
            return config ? config.username_label : '{{ __('SMTP Username') }}';
        },

        getPasswordLabel() {
            const config = this.getProviderConfig();
            return config ? config.password_label : '{{ __('SMTP Password') }}';
        },

        getUsernamePlaceholder() {
            const config = this.getProviderConfig();
            if (!config) return '{{ __('Enter SMTP username') }}';
            if (config.auth_method === 'smtp_key' || config.auth_method === 'api_token') {
                return '{{ __('Enter API key or token') }}';
            }
            return '{{ __('Enter SMTP username') }}';
        },

        getPasswordPlaceholder() {
            const config = this.getProviderConfig();
            if (!config) return '{{ __('Enter SMTP password') }}';
            if (config.auth_method === 'api_key_secret') {
                return '{{ __('Enter secret key') }}';
            }
            return '{{ __('Enter SMTP password') }}';
        },

        isPasswordRequired() {
            const config = this.getProviderConfig();
            return config ? config.password_required : true;
        },

        getFreeTier() {
            const config = this.getProviderConfig();
            return config ? config.free_tier : '';
        },

        getDocumentationUrl() {
            const config = this.getProviderConfig();
            return config ? config.documentation : '#';
        },

        getApiKeyUrl() {
            const config = this.getProviderConfig();
            return config ? config.get_api_key_url : '#';
        },

        getProviderDescription() {
            const config = this.getProviderConfig();
            if (!config) return '';
            return '{{ __('Configure') }} ' + config.name + ' {{ __('SMTP settings') }}';
        },

        getOAuthUrl() {
            if (this.provider === 'gmail_oauth2') {
                return '{{ route('admin.settings.mail.oauth', ['provider' => 'gmail']) }}';
            } else if (this.provider === 'microsoft_oauth2') {
                return '{{ route('admin.settings.mail.oauth', ['provider' => 'microsoft']) }}';
            }
            return '#';
        }
    };
}
</script>
@endpush
@endsection
