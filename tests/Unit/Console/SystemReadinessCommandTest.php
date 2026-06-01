<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\ApiKey;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_json_report_when_baseline_is_ready(): void
    {
        $this->seedFreshTimestamps();
        $this->createPublicApiKey();

        $exitCode = Artisan::call('system:readiness', [
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('overall', $report);
        $this->assertArrayHasKey('counts', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertSame(0, $report['counts']['fail'] ?? null);

        $checks = collect($report['checks'] ?? [])->keyBy('id');
        $this->assertSame('pass', $checks->get('scheduler_heartbeat')['status'] ?? null);
        $this->assertSame('pass', $checks->get('weather_current_data')['status'] ?? null);
        $this->assertSame('pass', $checks->get('api_public_key')['status'] ?? null);
    }

    public function test_strict_mode_returns_failure_when_any_check_fails(): void
    {
        $this->seedFreshTimestamps();
        // Deliberately skip creating a public API key to trigger a fail.

        $exitCode = Artisan::call('system:readiness', [
            '--json' => true,
            '--strict' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);
        $this->assertSame('fail', $report['overall'] ?? null);

        $checks = collect($report['checks'] ?? [])->keyBy('id');
        $this->assertSame('fail', $checks->get('api_public_key')['status'] ?? null);
    }

    public function test_poll_check_warns_when_attempts_are_fresh_but_last_success_is_stale(): void
    {
        $this->seedFreshTimestamps();
        $this->createPublicApiKey();

        Setting::setValue('solar_forecast.enabled', true, 'boolean', 'solar_forecast');
        Cache::put('poll_timestamp_solar_forecast', now()->subHours(5)->toDateTimeString(), now()->addHours(24));
        Cache::put('poll_attempt_timestamp_solar_forecast', now()->subMinutes(2)->toDateTimeString(), now()->addHours(24));

        $exitCode = Artisan::call('system:readiness', [
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);

        $checks = collect($report['checks'] ?? [])->keyBy('id');
        $solar = $checks->get('poll_solar_forecast');

        $this->assertSame('warn', $solar['status'] ?? null);
        $this->assertStringContainsString('attempts are still running', $solar['summary'] ?? '');
    }

    public function test_poll_check_warns_within_schedule_grace_when_single_slot_is_missed(): void
    {
        $this->seedFreshTimestamps();
        $this->createPublicApiKey();

        Setting::setValue('yrno.enabled', true, 'boolean', 'yrno');
        Cache::put('poll_timestamp_forecast', now()->subMinutes(50)->toDateTimeString(), now()->addHours(24));
        Cache::forget('poll_attempt_timestamp_forecast');

        $exitCode = Artisan::call('system:readiness', [
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);

        $checks = collect($report['checks'] ?? [])->keyBy('id');
        $forecast = $checks->get('poll_forecast');

        $this->assertSame('warn', $forecast['status'] ?? null);
        $this->assertStringContainsString('within one schedule window', $forecast['summary'] ?? '');
    }

    private function seedFreshTimestamps(): void
    {
        $now = now()->toDateTimeString();

        Cache::put('scheduler:last_run', $now, now()->addMinutes(10));
        Cache::put('weather:last_update', $now, now()->addMinutes(10));
        Cache::put('poll_timestamp_aurora', $now, now()->addMinutes(10));
        Cache::put('poll_timestamp_iss', $now, now()->addMinutes(10));
        Cache::put('poll_timestamp_astronomy', $now, now()->addMinutes(10));
        Cache::put('poll_timestamp_pollen', $now, now()->addMinutes(10));
    }

    private function createPublicApiKey(): void
    {
        $plain = 'public-test-key';

        ApiKey::query()->create([
            'name' => 'Site',
            'key_hash' => hash('sha256', $plain),
            'key_prefix' => substr($plain, 0, 8),
            'is_public' => true,
        ]);
    }
}
