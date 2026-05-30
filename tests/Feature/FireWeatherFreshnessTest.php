<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailySummary;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FireWeatherFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_reflects_rising_daytime_temperature_within_the_cache_window(): void
    {
        Setting::setValue('navigation.fire_weather_enabled', true, 'boolean', 'navigation');
        Cache::flush();

        $today = now()->toDateString();
        DailySummary::create([
            'date' => $today,
            'temp_high' => 10.0,   // cold overnight reading
            'temp_low' => 5.0,
            'humidity_low' => 90,
            'humidity_high' => 95,
            'rain_total' => 0,
        ]);

        // Early morning: the page reflects the cold overnight value.
        $this->get('/fire-weather')->assertOk()->assertSee('10.0 °C');

        // The day warms up — weather:fetch updates today's DailySummary every minute.
        DailySummary::whereDate('date', $today)->update([
            'temp_high' => 35.0,
            'humidity_low' => 10,
        ]);

        // 20 minutes later the page must reflect the heat, not a frozen overnight snapshot.
        Carbon::setTestNow(now()->addMinutes(20));

        $response = $this->get('/fire-weather');
        $response->assertOk();
        $response->assertSee('35.0 °C');
        $response->assertDontSee('10.0 °C');

        Carbon::setTestNow();
    }
}
