<?php

namespace App\Services\Mail;

use App\Models\Setting;
use App\Services\Mail\MailConfigService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\Google;
use TheNetworg\OAuth2\Client\Provider\Azure;

class OAuth2MailService
{
    protected MailConfigService $mailConfigService;

    public function __construct(MailConfigService $mailConfigService)
    {
        $this->mailConfigService = $mailConfigService;
    }

    /**
     * Get a fresh access token for the OAuth2 provider
     */
    public function getAccessToken(string $provider): ?string
    {
        $clientId = Setting::getValue('mail.oauth2.client_id');
        $clientSecret = Setting::getValue('mail.oauth2.client_secret');
        $refreshToken = $this->mailConfigService->getOAuth2RefreshToken($provider);

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            Log::error('OAuth2 credentials incomplete', ['provider' => $provider]);
            return null;
        }

        // Decrypt client secret
        try {
            $decryptedSecret = Crypt::decryptString($clientSecret);
        } catch (\Exception $e) {
            $decryptedSecret = $clientSecret;
        }

        try {
            if ($provider === 'gmail_oauth2') {
                return $this->getGmailAccessToken($clientId, $decryptedSecret, $refreshToken);
            } elseif ($provider === 'microsoft_oauth2') {
                return $this->getMicrosoftAccessToken($clientId, $decryptedSecret, $refreshToken);
            }
        } catch (\Exception $e) {
            Log::error('Failed to refresh OAuth2 access token', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return null;
    }

    /**
     * Get Gmail access token using refresh token
     */
    protected function getGmailAccessToken(string $clientId, string $clientSecret, string $refreshToken): ?string
    {
        $provider = new Google([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => route('admin.settings.mail.oauth.callback', ['provider' => 'gmail']),
        ]);

        try {
            $accessToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

            return $accessToken->getToken();
        } catch (\Exception $e) {
            Log::error('Gmail OAuth2 token refresh failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get Microsoft access token using refresh token
     */
    protected function getMicrosoftAccessToken(string $clientId, string $clientSecret, string $refreshToken): ?string
    {
        $provider = new Azure([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => route('admin.settings.mail.oauth.callback', ['provider' => 'microsoft']),
        ]);

        try {
            $accessToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
                'resource' => 'https://outlook.office.com/',
            ]);

            return $accessToken->getToken();
        } catch (\Exception $e) {
            Log::error('Microsoft OAuth2 token refresh failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get the email address associated with the OAuth2 account
     * This is needed for the FROM address
     */
    public function getOAuth2EmailAddress(string $provider): ?string
    {
        // For now, we'll need to store this separately or get it from the OAuth token
        // This could be stored when the user authorizes
        return Setting::getValue("mail.oauth2.email_address");
    }
}
