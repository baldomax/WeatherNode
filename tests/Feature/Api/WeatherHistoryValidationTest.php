<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeatherHistoryValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_rejects_invalid_period(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        $response = $this->getJson('/api/weather/history?period=all&field=temperature');

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid period',
        ]);
    }

    public function test_history_rejects_invalid_field(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        $response = $this->getJson('/api/weather/history?period=24h&field=temperature;drop table');

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid field',
        ]);
    }

    public function test_history_applies_selected_period_window(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        WeatherReading::query()->create([
            'recorded_at' => now()->subDays(40),
            'temperature' => 9.1,
        ]);
        WeatherReading::query()->create([
            'recorded_at' => now()->subHours(2),
            'temperature' => 12.3,
        ]);

        $response = $this->getJson('/api/weather/history?period=24h&field=temperature');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('period', '24h');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.value', 12.3);
    }

    public function test_history_downsamples_dense_data(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        $start = now()->subHours(24);
        $rows = [];
        for ($i = 0; $i < 720; $i++) {
            $rows[] = [
                'recorded_at' => $start->copy()->addMinutes($i * 2),
                'temperature' => 10 + ($i / 100),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('weather_readings')->insert($rows);

        $response = $this->getJson('/api/weather/history?period=24h&field=temperature');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('sampling.bucket_seconds', 300);
        $response->assertJsonPath('sampling.max_points', 288);
        $this->assertLessThanOrEqual(290, count($response->json('data')));
    }
}
