<?php

namespace App\Services\Ads;

use App\Services\GeoIp\GeoIpService;
use Illuminate\Http\Request;

class AdsConsentService
{
    public const MODE_AUTO = 'auto';
    public const MODE_ALWAYS_SHOW_ADS = 'always_show_ads';
    public const MODE_ALWAYS_REQUIRE_CONSENT = 'always_require_consent';

    /**
     * EU + EEA + UK + Switzerland.
     */
    private const CONSENT_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE',
        'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT',
        'RO', 'SK', 'SI', 'ES', 'SE', // EU
        'IS', 'LI', 'NO',             // EEA non-EU
        'GB', 'CH',                   // UK + Switzerland
    ];

    public function getAllowedConsentModes(): array
    {
        return [
            self::MODE_AUTO,
            self::MODE_ALWAYS_SHOW_ADS,
            self::MODE_ALWAYS_REQUIRE_CONSENT,
        ];
    }

    public function normalizeConsentMode(?string $mode): string
    {
        $normalized = strtolower(trim((string) $mode));
        return in_array($normalized, $this->getAllowedConsentModes(), true)
            ? $normalized
            : self::MODE_AUTO;
    }

    public function resolveCountryCode(Request $request): ?string
    {
        foreach ([
            'CF-IPCountry',
            'CloudFront-Viewer-Country',
            'X-Country-Code',
            'X-Geo-Country',
            'X-AppEngine-Country',
        ] as $headerName) {
            $headerValue = strtoupper(trim((string) $request->headers->get($headerName, '')));
            if ($this->isValidCountryCode($headerValue)) {
                return $headerValue;
            }
        }

        $ip = $request->ip();
        if (!$ip || $this->isLocalIp($ip)) {
            return null;
        }

        $countryCode = strtoupper((string) app(GeoIpService::class)->lookupCountryCode($ip));
        if ($this->isValidCountryCode($countryCode)) {
            return $countryCode;
        }

        return $this->resolveCountryCodeFromAcceptLanguage($request);
    }

    public function requiresConsentForCountry(?string $countryCode): bool
    {
        if (!$countryCode) {
            return false;
        }

        return in_array(strtoupper($countryCode), self::CONSENT_COUNTRIES, true);
    }

    public function requiresConsentForRequest(Request $request): bool
    {
        return $this->requiresConsentForCountry($this->resolveCountryCode($request));
    }

    public function requiresConsentForCountryWithMode(?string $countryCode, ?string $mode): bool
    {
        $mode = $this->normalizeConsentMode($mode);

        if ($mode === self::MODE_ALWAYS_SHOW_ADS) {
            return false;
        }

        if ($mode === self::MODE_ALWAYS_REQUIRE_CONSENT) {
            return true;
        }

        return $this->requiresConsentForCountry($countryCode);
    }

    public function requiresConsentForRequestWithMode(Request $request, ?string $mode): bool
    {
        $countryCode = $this->resolveCountryCode($request);
        return $this->requiresConsentForCountryWithMode($countryCode, $mode);
    }

    private function isValidCountryCode(?string $countryCode): bool
    {
        return is_string($countryCode) && preg_match('/^[A-Z]{2}$/', $countryCode) === 1;
    }

    private function resolveCountryCodeFromAcceptLanguage(Request $request): ?string
    {
        $acceptLanguage = trim((string) $request->headers->get('Accept-Language', ''));
        if ($acceptLanguage === '') {
            return null;
        }

        $firstToken = trim(explode(',', $acceptLanguage)[0] ?? '');
        if ($firstToken === '') {
            return null;
        }

        $locale = explode(';', $firstToken)[0] ?? '';
        if (!preg_match('/^[a-z]{2}[-_](?<country>[A-Za-z]{2})$/', trim($locale), $matches)) {
            return null;
        }

        $countryCode = strtoupper($matches['country'] ?? '');
        return $this->isValidCountryCode($countryCode) ? $countryCode : null;
    }

    private function isLocalIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
