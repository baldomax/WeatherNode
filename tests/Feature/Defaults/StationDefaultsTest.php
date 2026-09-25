<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #105. Two separate problems live here.
 *
 * The shipped values described one station in Uitgeest. And blanking them was
 * not a fix on its own, because Setting::getValue() only falls back when the
 * row is missing: a row holding '' returns '', so (float) '' became 0.0 and the
 * station silently moved to Null Island, while an empty timezone threw and took
 * the homepage down with it.
 */
class StationDefaultsTest extends TestCase
{
    use RefreshDatabase;

    /** A row that exists but is empty used to mean the Gulf of Guinea. */
    public function test_blank_coordinates_do_not_move_the_station_to_null_island(): void
    {
        Setting::setValue('station.latitude', '', 'float', 'station');
        Setting::setValue('station.longitude', '', 'float', 'station');

        $this->assertNotSame(0.0, Setting::latitude());
        $this->assertNotSame(0.0, Setting::longitude());
    }

    /** new DateTimeZone('') throws, and dashboard.blade.php:29 has no guard. */
    public function test_a_blank_timezone_is_still_a_usable_timezone(): void
    {
        Setting::setValue('station.timezone', '', 'string', 'station');

        $this->assertNotSame('', Setting::timezone());
        new \DateTimeZone(Setting::timezone());
    }

    public function test_the_homepage_survives_a_blank_timezone(): void
    {
        Setting::setValue('station.timezone', '', 'string', 'station');

        $this->get('/')->assertOk();
    }

    /** With no rows at all, the code defaults must not name anyone. */
    public function test_no_accessor_falls_back_to_a_real_persons_station(): void
    {
        $personal = ['uitgeest', 'waldijk', 'noord-holland', 'amsterdam'];

        foreach (['stationLocation', 'timezone', 'defaultLanguage'] as $method) {
            $value = strtolower((string) Setting::{$method}());

            foreach ($personal as $needle) {
                $this->assertStringNotContainsString($needle, $value, "Setting::{$method}() returns {$value}");
            }
        }

        $this->assertNotEqualsWithDelta(52.5163996, Setting::latitude(), 0.0001);
        $this->assertNotEqualsWithDelta(4.7078991, Setting::longitude(), 0.0001);
    }

    public function test_a_fresh_install_is_not_described_as_uitgeest(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $offenders = [];

        foreach (Setting::all() as $setting) {
            $value = strtolower((string) $setting->value);

            // Paths are built from base_path() at seed time, so they carry
            // whatever the checkout is called rather than a shipped default.
            if (str_starts_with($value, strtolower(base_path()))) {
                continue;
            }

            foreach (['uitgeest', 'waldijk', '52.5163996', '4.7078991', 'wh4000se'] as $needle) {
                if (str_contains($value, $needle)) {
                    $offenders[] = $setting->key.' = '.$setting->value;
                }
            }
        }

        $this->assertSame([], $offenders, "A fresh install still ships:\n".implode("\n", array_unique($offenders)));
    }

    public function test_the_interface_does_not_open_in_dutch_for_everyone(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('auto', Setting::getValue('display.language'));
    }
}
