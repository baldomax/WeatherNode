<?php

namespace App\Services\Mail;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class MailConfigService
{
    /**
     * Get the configured mailer based on provider settings
     */
    public function getConfiguredMailer(): string
    {
        $provider = Setting::getValue('mail.provider', 'smtp_custom');
        
        if (in_array($provider, ['gmail_oauth2', 'microsoft_oauth2'])) {
            return $provider;
        }
        
        return 'smtp';
    }

    /**
     * Apply mail configuration based on provider
     */
    public function applyConfiguration(): void
    {
        $provider = Setting::getValue('mail.provider', 'smtp_custom');
        
        // If no provider is configured and no .env mail settings exist, use 'log' for development
        if ($provider === 'smtp_custom' && !Setting::getValue('mail.smtp.host') && !env('MAIL_HOST')) {
            Config::set('mail.default', 'log');
            return;
        }
        
        if (in_array($provider, ['gmail_oauth2', 'microsoft_oauth2'])) {
            $this->applyOAuth2Configuration($provider);
        } else {
            $this->applySmtpConfiguration($provider);
        }
    }

    /**
     * Apply OAuth2 mail configuration
     */
    protected function applyOAuth2Configuration(string $provider): void
    {
        $clientId = Setting::getValue('mail.oauth2.client_id');
        $refreshToken = $this->getOAuth2RefreshToken($provider);
        
        if (empty($clientId) || empty($refreshToken)) {
            Log::warning("OAuth2 credentials incomplete for provider", ['provider' => $provider]);
            Config::set('mail.default', 'log');
            return;
        }
        
        // Get OAuth2 email address (stored during authorization)
        $oauth2Email = Setting::getValue('mail.oauth2.email_address');
        
        // Get fresh access token
        $oauth2Service = app(\App\Services\Mail\OAuth2MailService::class);
        $accessToken = $oauth2Service->getAccessToken($provider);
        
        if (empty($accessToken)) {
            Log::warning("Failed to get OAuth2 access token for provider", ['provider' => $provider]);
            Config::set('mail.default', 'log');
            return;
        }
        
        // Set mailer type
        Config::set('mail.default', $provider);
        
        // Configure OAuth2 mailer with fresh access token
        if ($provider === 'gmail_oauth2') {
            Config::set('mail.mailers.gmail_oauth2', [
                'transport' => 'smtp',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => $oauth2Email ?: $clientId, // Use stored email or fallback to client ID
                'password' => $accessToken, // Fresh access token
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            ]);
        } elseif ($provider === 'microsoft_oauth2') {
            Config::set('mail.mailers.microsoft_oauth2', [
                'transport' => 'smtp',
                'host' => 'smtp.office365.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => $oauth2Email ?: $clientId, // Use stored email or fallback to client ID
                'password' => $accessToken, // Fresh access token
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            ]);
        }
        
        // Set FROM address to OAuth2 email if available
        if ($oauth2Email) {
            Config::set('mail.from.address', $oauth2Email);
            if (!config('mail.from.name') || config('mail.from.name') === 'Example') {
                Config::set('mail.from.name', env('MAIL_FROM_NAME', env('APP_NAME', 'WeatherNode')));
            }
        }
    }

    /**
     * Apply SMTP configuration (predefined or custom)
     */
    protected function applySmtpConfiguration(string $provider): void
    {
        $predefinedProviders = ['brevo', 'mailjet', 'postmark', 'mailgun', 'smtp2go'];
        
        if (in_array($provider, $predefinedProviders)) {
            $this->applyPredefinedSmtpConfiguration($provider);
        } else {
            $this->applyCustomSmtpConfiguration();
        }
    }

    /**
     * Apply predefined SMTP provider configuration
     */
    protected function applyPredefinedSmtpConfiguration(string $provider): void
    {
        $providerConfig = config("mail_providers.{$provider}");
        
        if (!$providerConfig) {
            // Fallback to custom if provider config not found
            $this->applyCustomSmtpConfiguration();
            return;
        }

        $username = Setting::getValue('mail.smtp.username');
        $password = Setting::getValue('mail.smtp.password');
        
        // Decrypt password if encrypted
        if ($password) {
            try {
                $password = Crypt::decryptString($password);
            } catch (\Exception $e) {
                // Password might not be encrypted, use as-is
            }
        }

        // For Brevo, username (SMTP Key) is required
        if ($provider === 'brevo' && empty($username)) {
            // Fallback to log if credentials not configured
            Config::set('mail.default', 'log');
            return;
        }

        // Handle password based on provider requirements
        // For providers that use API token as username (like Postmark),
        // if no password is provided, use the username (which contains the token) as password
        if (empty($password) && !empty($username)) {
            $authMethod = $providerConfig['auth_method'] ?? 'smtp_credentials';
            $passwordRequired = $providerConfig['password_required'] ?? true;
            
            if ($authMethod === 'api_token' && !$passwordRequired) {
                // For providers like Postmark: API token is stored as username, use it as password too
                $password = $username;
            }
        }
        
        // Validate that we have required credentials
        if (empty($username)) {
            Log::warning("SMTP username is empty for provider", ['provider' => $provider]);
            // Fallback to log mailer if required username is missing
            Config::set('mail.default', 'log');
            return;
        }
        
        if (empty($password) && ($providerConfig['password_required'] ?? false)) {
            Log::warning("SMTP password is required but empty for provider", ['provider' => $provider]);
            // Fallback to log mailer if required password is missing
            Config::set('mail.default', 'log');
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $providerConfig['host'],
            'port' => $providerConfig['port'],
            'encryption' => $providerConfig['encryption'],
            'username' => $username ?: null,
            'password' => $password ?: null,
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ]);
        
        // Set FROM address to SMTP username if it's a valid email (for verified senders)
        if ($username && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            Config::set('mail.from.address', $username);
            // Keep existing FROM name or use a default
            if (!config('mail.from.name') || config('mail.from.name') === 'Example') {
                Config::set('mail.from.name', env('MAIL_FROM_NAME', env('APP_NAME', 'WeatherNode')));
            }
        }
    }

    /**
     * Apply custom SMTP configuration
     */
    protected function applyCustomSmtpConfiguration(): void
    {
        // Check if settings exist, otherwise fall back to .env
        $host = Setting::getValue('mail.smtp.host') ?: env('MAIL_HOST', '127.0.0.1');
        $port = Setting::getValue('mail.smtp.port') ?: env('MAIL_PORT', 2525);
        $encryption = Setting::getValue('mail.smtp.encryption') ?: env('MAIL_ENCRYPTION', 'tls');
        $username = Setting::getValue('mail.smtp.username') ?: env('MAIL_USERNAME');
        $password = Setting::getValue('mail.smtp.password') ?: env('MAIL_PASSWORD');
        
        // Decrypt password if encrypted
        if ($password) {
            try {
                $password = Crypt::decryptString($password);
            } catch (\Exception $e) {
                // Password might not be encrypted, use as-is
            }
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ]);
        
        // Set FROM address to SMTP username if it's a valid email (for verified senders)
        if ($username && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            Config::set('mail.from.address', $username);
            // Keep existing FROM name or use a default
            if (!config('mail.from.name') || config('mail.from.name') === 'Example') {
                Config::set('mail.from.name', env('MAIL_FROM_NAME', env('APP_NAME', 'WeatherNode')));
            }
        }
    }

    /**
     * Get OAuth2 refresh token (decrypted)
     */
    public function getOAuth2RefreshToken(string $provider): ?string
    {
        $token = Setting::getValue("mail.oauth2.refresh_token");
        
        if (!$token) {
            return null;
        }

        try {
            return Crypt::decryptString($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get OAuth2 client credentials
     */
    public function getOAuth2Credentials(string $provider): array
    {
        $clientId = Setting::getValue("mail.oauth2.client_id");
        $clientSecret = Setting::getValue("mail.oauth2.client_secret");
        
        $decryptedSecret = null;
        if ($clientSecret) {
            try {
                $decryptedSecret = Crypt::decryptString($clientSecret);
            } catch (\Exception $e) {
                // Secret might not be encrypted
                $decryptedSecret = $clientSecret;
            }
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $decryptedSecret,
            'refresh_token' => $this->getOAuth2RefreshToken($provider),
        ];
    }

    /**
     * Check if OAuth2 is configured
     */
    public function isOAuth2Configured(string $provider): bool
    {
        $clientId = Setting::getValue("mail.oauth2.client_id");
        $refreshToken = $this->getOAuth2RefreshToken($provider);
        
        return !empty($clientId) && !empty($refreshToken);
    }

    /**
     * Get predefined provider configuration
     */
    public function getPredefinedProviderConfig(string $provider): ?array
    {
        return config("mail_providers.{$provider}");
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        $providers = [
            'oauth2' => [
                'gmail_oauth2' => 'Gmail (OAuth2)',
                'microsoft_oauth2' => 'Microsoft/Office 365 (OAuth2)',
            ],
            'predefined' => [],
            'custom' => [
                'smtp_custom' => 'Custom SMTP',
            ],
        ];

        $predefinedConfigs = config('mail_providers', []);
        foreach ($predefinedConfigs as $key => $config) {
            $regionLabel = $config['region'] ? " ({$config['region']})" : '';
            $providers['predefined'][$key] = $config['name'] . $regionLabel;
        }

        return $providers;
    }
}
