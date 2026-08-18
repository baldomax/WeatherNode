<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Weather\EcowittService;
use App\Services\Weather\Normalization\WeatherReadingWriter;
use App\Services\Weather\Sources\AmbientWeatherAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchWeatherDataSaveFailureTest extends TestCase
{
    use RefreshDatabase;

    private function useAmbientSource(): void
    {
        Setting::setValue('livedata.format', 'AWapi', 'select', 'livedata');

        $this->instance(AmbientWeatherAdapter::class, \Mockery::mock(AmbientWeatherAdapter::class, function ($mock) {
            $mock->shouldReceive('fetch')->andReturn([
                'recorded_at' => now(),
                'temperature' => 12.3,
                'humidity' => 70,
            ]);
        }));
    }

    /**
     * The save failure is caught and logged, then $reading was passed straight
     * to updateDailySummary(WeatherReading $reading), fataling the scheduled
     * task with a TypeError instead of reporting the handled failure.
     */
    public function test_a_handled_save_failure_does_not_fatal_the_command(): void
    {
        $this->useAmbientSource();
        $this->instance(WeatherReadingWriter::class, \Mockery::mock(WeatherReadingWriter::class, function ($mock) {
            $mock->shouldReceive('store')->andThrow(new \RuntimeException('Integrity constraint violation'));
        }));

        $this->artisan('weather:fetch', ['--save' => true])->assertExitCode(1);

        $this->assertSame(0, WeatherReading::count());
    }

    /** EcowittService::saveReading() is nullable, so the save can also just return nothing. */
    public function test_a_save_returning_null_does_not_fatal_the_command(): void
    {
        Setting::setValue('livedata.format', 'ecoLcl', 'select', 'livedata');

        $this->instance(EcowittService::class, \Mockery::mock(EcowittService::class, function ($mock) {
            $mock->shouldReceive('fetchRealTimeData')->andReturn([
                'recorded_at' => now(),
                'temperature' => 12.3,
            ]);
            $mock->shouldReceive('saveReading')->andReturn(null);
        }));

        $this->artisan('weather:fetch', ['--save' => true])->assertExitCode(1);
    }

    public function test_a_successful_save_still_reports_success(): void
    {
        $this->useAmbientSource();

        $this->artisan('weather:fetch', ['--save' => true])->assertExitCode(0);

        $this->assertSame(1, WeatherReading::count());
    }
}
