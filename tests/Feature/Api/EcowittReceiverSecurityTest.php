<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcowittReceiverSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_mode_auto_learns_passkey_on_first_push(): void
    {
        Setting::setValue('ecowitt.secure_mode', false, 'boolean', 'ecowitt');

        $response = $this->post('/api/ecowitt/receive', $this->payload([
            'PASSKEY' => 'first-pass',
        ]));

        $response->assertStatus(200);
        $this->assertSame(base64_encode('first-pass'), Setting::getValue('ecowitt.passkey'));
        $this->assertSame(1, WeatherReading::query()->count());
    }

    public function test_secure_mode_rejects_missing_or_invalid_endpoint_token(): void
    {
        Setting::setValue('ecowitt.secure_mode', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.secure_token', 'secure123', 'string', 'ecowitt');
        Setting::setValue('ecowitt.passkey', 'push-pass', 'string', 'ecowitt');

        $this->post('/api/ecowitt/receive', $this->payload())->assertStatus(403);
        $this->post('/api/ecowitt/receive/wrongtoken', $this->payload())->assertStatus(403);
    }

    public function test_secure_mode_requires_configured_passkey(): void
    {
        Setting::setValue('ecowitt.secure_mode', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.secure_token', 'secure123', 'string', 'ecowitt');
        Setting::setValue('ecowitt.passkey', '', 'string', 'ecowitt');

        $this->post('/api/ecowitt/receive/secure123', $this->payload())->assertStatus(503);
    }

    public function test_secure_mode_accepts_valid_token_and_passkey(): void
    {
        Setting::setValue('ecowitt.secure_mode', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.secure_token', 'secure123', 'string', 'ecowitt');
        Setting::setValue('ecowitt.passkey', 'push-pass', 'string', 'ecowitt');

        $response = $this->post('/api/ecowitt/receive/secure123', $this->payload());

        $response->assertStatus(200);
        $this->assertSame(1, WeatherReading::query()->count());
    }

    public function test_ip_allowlist_rejects_non_matching_source_ip(): void
    {
        Setting::setValue('ecowitt.ip_filter_enabled', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.ip_allowlist', '203.0.113.10', 'text', 'ecowitt');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->post('/api/ecowitt/receive', $this->payload());

        $response->assertStatus(403);
        $this->assertSame(0, WeatherReading::query()->count());
    }

    public function test_ip_allowlist_accepts_matching_cidr_source_ip(): void
    {
        Setting::setValue('ecowitt.ip_filter_enabled', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.ip_allowlist', '198.51.100.0/24', 'text', 'ecowitt');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->post('/api/ecowitt/receive', $this->payload());

        $response->assertStatus(200);
        $this->assertSame(1, WeatherReading::query()->count());
    }

    public function test_name_allowlist_rejects_non_matching_station_identifier(): void
    {
        Setting::setValue('ecowitt.name_filter_enabled', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.name_allowlist', 'GW2000', 'text', 'ecowitt');

        $response = $this->post('/api/ecowitt/receive', $this->payload([
            'stationtype' => 'WH2900',
            'model' => 'WS2910',
        ]));

        $response->assertStatus(403);
        $this->assertSame(0, WeatherReading::query()->count());
    }

    public function test_name_allowlist_accepts_case_insensitive_partial_match(): void
    {
        Setting::setValue('ecowitt.name_filter_enabled', true, 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.name_allowlist', 'gw2000', 'text', 'ecowitt');

        $response = $this->post('/api/ecowitt/receive', $this->payload([
            'stationtype' => 'GW2000A_V2',
        ]));

        $response->assertStatus(200);
        $this->assertSame(1, WeatherReading::query()->count());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'PASSKEY' => 'push-pass',
            'tempf' => '68.0',
            'humidity' => '55',
            'baromrelin' => '29.80',
            'windspeedmph' => '2.4',
        ], $overrides);
    }
}
