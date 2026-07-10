<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Weather;

use App\Services\Weather\EcowittPushParser;
use Tests\TestCase;

class EcowittPushParserTest extends TestCase
{
    /**
     * Regression for centauri/WeatherNode#3: a direct `pm10` field (sent by
     * custom Arduino/ESP32 stations) was ignored while `pm25_ch1` was saved.
     */
    public function test_maps_direct_pm10_and_pm25_from_payload(): void
    {
        $data = (new EcowittPushParser())->parse([
            'pm25_ch1' => '12.1',
            'pm10' => '18.5',
        ]);

        $this->assertSame(12.1, $data['pm25_ch1']);
        $this->assertSame(18.5, $data['pm10']);
    }

    public function test_maps_direct_pm10_24h_average(): void
    {
        $data = (new EcowittPushParser())->parse([
            'pm10_24h' => '20.3',
        ]);

        $this->assertSame(20.3, $data['pm10_avg_24h']);
    }

    public function test_falls_back_to_co2_module_pm10_when_no_direct_field(): void
    {
        $data = (new EcowittPushParser())->parse([
            'pm10_co2' => '9.4',
            'pm10_24h_co2' => '11.2',
        ]);

        $this->assertSame(9.4, $data['pm10']);
        $this->assertSame(11.2, $data['pm10_avg_24h']);
    }

    public function test_direct_pm10_takes_precedence_over_co2_module(): void
    {
        $data = (new EcowittPushParser())->parse([
            'pm10' => '18.5',
            'pm10_co2' => '9.4',
            'pm10_24h' => '20.3',
            'pm10_24h_co2' => '11.2',
        ]);

        $this->assertSame(18.5, $data['pm10']);
        $this->assertSame(20.3, $data['pm10_avg_24h']);
    }
}
