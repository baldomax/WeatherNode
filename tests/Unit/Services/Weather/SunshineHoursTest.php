<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Weather;

use App\Services\Weather\LocalFiles\RealtimeTxtParser;
use App\Services\Weather\SunshineHoursCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SunshineHoursTest extends TestCase
{
    public function test_realtime_parser_reads_cumulus_sunshine_hours_field(): void
    {
        $parts = array_fill(0, 58, '0');
        $parts[0] = '29/07/26';
        $parts[1] = '12:00:00';
        $parts[2] = '20.0';
        $parts[3] = '50';
        $parts[4] = '10.0';
        $parts[5] = '5.0';
        $parts[6] = '6.0';
        $parts[7] = '180';
        $parts[8] = '0.0';
        $parts[9] = '1.2';
        $parts[10] = '1013.0';
        $parts[13] = 'km/h';
        $parts[14] = 'C';
        $parts[15] = 'hPa';
        $parts[16] = 'mm';
        $parts[45] = '450';
        $parts[55] = '4.75';

        $data = (new RealtimeTxtParser())->parseContent(implode(' ', $parts), 'cumulus');

        $this->assertNotNull($data);
        $this->assertSame(450.0, $data['solar_radiation']);
        $this->assertSame(4.75, $data['solar_hours']);
    }

    public function test_calculator_prefers_reported_solar_hours(): void
    {
        $readings = new Collection([
            (object) ['solar_hours' => 2.5, 'solar_radiation' => 500, 'recorded_at' => Carbon::parse('2026-07-29 10:00:00')],
            (object) ['solar_hours' => 3.1, 'solar_radiation' => 600, 'recorded_at' => Carbon::parse('2026-07-29 11:00:00')],
        ]);

        $hours = (new SunshineHoursCalculator())->resolveFromReadings($readings);

        $this->assertSame(3.1, $hours);
    }

    public function test_calculator_estimates_from_solar_radiation_when_unreported(): void
    {
        $readings = new Collection([
            (object) ['solar_hours' => null, 'solar_radiation' => 200, 'recorded_at' => Carbon::parse('2026-07-29 10:00:00')],
            (object) ['solar_hours' => null, 'solar_radiation' => 50, 'recorded_at' => Carbon::parse('2026-07-29 11:00:00')],
            (object) ['solar_hours' => null, 'solar_radiation' => 300, 'recorded_at' => Carbon::parse('2026-07-29 12:00:00')],
            (object) ['solar_hours' => null, 'solar_radiation' => 400, 'recorded_at' => Carbon::parse('2026-07-29 13:00:00')],
        ]);

        $hours = (new SunshineHoursCalculator())->resolveFromReadings($readings);

        // 10:00->11:00 (above threshold) + 12:00->13:00 (above threshold) = 2.0h
        $this->assertSame(2.0, $hours);
    }
}
