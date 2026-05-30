<?php

declare(strict_types=1);

namespace Tests\Unit\Ads;

use App\Services\Ads\AdsConsentService;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdsConsentServiceTest extends TestCase
{
    public function test_normalize_consent_mode_falls_back_to_auto_for_unknown_values(): void
    {
        $service = app(AdsConsentService::class);

        $this->assertSame(AdsConsentService::MODE_AUTO, $service->normalizeConsentMode(''));
        $this->assertSame(AdsConsentService::MODE_AUTO, $service->normalizeConsentMode('invalid_mode'));
    }

    public function test_auto_mode_requires_consent_only_for_listed_regions(): void
    {
        $service = app(AdsConsentService::class);

        $this->assertTrue($service->requiresConsentForCountryWithMode('NL', AdsConsentService::MODE_AUTO));
        $this->assertFalse($service->requiresConsentForCountryWithMode('US', AdsConsentService::MODE_AUTO));
    }

    public function test_always_show_ads_mode_never_requires_consent(): void
    {
        $service = app(AdsConsentService::class);

        $this->assertFalse($service->requiresConsentForCountryWithMode('NL', AdsConsentService::MODE_ALWAYS_SHOW_ADS));
        $this->assertFalse($service->requiresConsentForCountryWithMode(null, AdsConsentService::MODE_ALWAYS_SHOW_ADS));
    }

    public function test_always_require_consent_mode_always_requires_consent(): void
    {
        $service = app(AdsConsentService::class);

        $this->assertTrue($service->requiresConsentForCountryWithMode('US', AdsConsentService::MODE_ALWAYS_REQUIRE_CONSENT));
        $this->assertTrue($service->requiresConsentForCountryWithMode(null, AdsConsentService::MODE_ALWAYS_REQUIRE_CONSENT));
    }

    public function test_request_mode_uses_country_from_headers(): void
    {
        $service = app(AdsConsentService::class);
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_CF_IPCOUNTRY' => 'NL',
        ]);

        $this->assertTrue($service->requiresConsentForRequestWithMode($request, AdsConsentService::MODE_AUTO));
        $this->assertFalse($service->requiresConsentForRequestWithMode($request, AdsConsentService::MODE_ALWAYS_SHOW_ADS));
    }
}
