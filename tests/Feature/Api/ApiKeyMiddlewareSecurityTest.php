<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyMiddlewareSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_radar_endpoints_require_header_key(): void
    {
        $plainKey = $this->createApiKey();

        $this->getJson('/api/ecowitt/status?api_key=' . $plainKey)
            ->assertStatus(401)
            ->assertJson([
                'error' => 'API key required',
            ]);

        $this->withHeaders(['X-API-Key' => $plainKey])
            ->getJson('/api/ecowitt/status')
            ->assertOk();
    }

    public function test_radar_visual_endpoints_accept_query_key(): void
    {
        $plainKey = $this->createApiKey();

        $this->get('/api/radar/tile/not-valid.png?api_key=' . $plainKey)
            ->assertStatus(400);

        $this->get('/api/radar/future-image?url=' . rawurlencode('https://example.com/sample.png') . '&api_key=' . $plainKey)
            ->assertStatus(400);
    }

    public function test_rate_limit_is_scoped_per_ip_for_same_key(): void
    {
        $plainKey = $this->createApiKey(rateLimitPerMinute: 1);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders(['X-API-Key' => $plainKey])
            ->getJson('/api/ecowitt/status')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders(['X-API-Key' => $plainKey])
            ->getJson('/api/ecowitt/status')
            ->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
            ->withHeaders(['X-API-Key' => $plainKey])
            ->getJson('/api/ecowitt/status')
            ->assertOk();
    }

    public function test_radar_tile_requests_are_rate_limited(): void
    {
        $plainKey = $this->createApiKey(rateLimitPerMinute: 1);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
                ->get('/api/radar/tile/not-valid.png?api_key=' . $plainKey)
                ->assertStatus(400);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->get('/api/radar/tile/not-valid.png?api_key=' . $plainKey)
            ->assertStatus(429);
    }

    public function test_public_key_is_blocked_from_private_api_routes(): void
    {
        $publicKey = $this->createApiKey();

        $this->withHeaders(['X-API-Key' => $publicKey])
            ->postJson('/api/forecast/narrate', [
                'date' => '2026-03-04',
                'min_temp_c' => 5,
                'max_temp_c' => 12,
            ])
            ->assertStatus(403)
            ->assertJson([
                'error' => 'Private API key required',
            ]);
    }

    public function test_private_key_can_access_private_api_routes(): void
    {
        $privateKey = $this->createApiKey(isPublic: false);

        $response = $this->withHeaders(['X-API-Key' => $privateKey])
            ->postJson('/api/forecast/narrate', [
                'date' => '2026-03-04',
                'min_temp_c' => 5,
                'max_temp_c' => 12,
            ]);

        $response->assertOk();
        $response->assertJsonPath('date', '2026-03-04');
        $this->assertIsString($response->json('text'));
        $this->assertNotSame('', trim((string) $response->json('text')));
    }

    public function test_public_key_can_still_access_public_data_route(): void
    {
        $publicKey = $this->createApiKey();

        $this->withHeaders(['X-API-Key' => $publicKey])
            ->getJson('/api/data/luftdaten')
            ->assertOk();
    }

    private function createApiKey(?int $rateLimitPerMinute = null, bool $isPublic = true): string
    {
        $plain = 'test_' . bin2hex(random_bytes(12));

        ApiKey::query()->create([
            'name' => 'Test key',
            'key_hash' => hash('sha256', $plain),
            'key_prefix' => substr($plain, 0, 8),
            'key_encrypted' => null,
            'is_public' => $isPublic,
            'rate_limit_per_minute' => $rateLimitPerMinute,
        ]);

        return $plain;
    }
}
