<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Models\Setting;
use App\Models\WeatherReading;
use App\Support\LiveSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * #156. A cloud source hands back the station's last upload, which is often
 * five to ten minutes old: Weather Underground, WeatherLink cloud, Ambient,
 * WeatherFlow and Netatmo. The health check called anything over five minutes
 * Offline, so these stations showed Offline while everything worked.
 *
 * Judging by when WeatherNode stored the reading instead would hide a dead
 * station: the cloud keeps returning its last observation, and that was
 * stored again every minute. So the age stays the station's own time, the
 * limit follows the source, and an unchanged observation is not stored twice.
 */
class CloudSourceFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function source(string $format): void
    {
        Setting::setValue('livedata.format', $format, 'select', 'livedata');
    }

    private function readingMinutesAgo(int $minutes): void
    {
        WeatherReading::query()->create(['recorded_at' => now()->subMinutes($minutes), 'temperature' => 12.5]);
    }

    private function sensorHealth(): array
    {
        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        return Cache::get('data_source_health', [])['sensor'] ?? [];
    }

    // ── health check ────────────────────────────────────────────────────────

    public function test_a_cloud_station_a_few_uploads_behind_is_online(): void
    {
        $this->source('wu');
        $this->readingMinutesAgo(8);

        $this->assertFalse($this->sensorHealth()['is_stale']);
    }

    public function test_a_cloud_station_that_stopped_uploading_is_offline(): void
    {
        $this->source('wu');
        $this->readingMinutesAgo(25);

        $this->assertTrue($this->sensorHealth()['is_stale']);
    }

    /** Push and file sources send every minute, so five minutes stays the limit. */
    public function test_a_pushing_station_is_still_offline_after_five_minutes(): void
    {
        $this->source('ecoLcl');
        $this->readingMinutesAgo(8);

        $this->assertTrue($this->sensorHealth()['is_stale']);
    }

    public function test_every_cloud_source_gets_the_longer_limit(): void
    {
        foreach (['wu', 'DWL', 'DWL_v2api', 'AWapi', 'wf', 'netatmo'] as $format) {
            $this->assertSame(20, LiveSource::limits($format)['stale'], $format);
        }
        foreach (['ecoLcl', 'ecowittAPI', 'cumulus', 'wuPush'] as $format) {
            $this->assertSame(5, LiveSource::limits($format)['stale'], $format);
        }
    }

    // ── admin live source card ──────────────────────────────────────────────

    public function test_the_admin_card_uses_the_same_limits(): void
    {
        $this->readingMinutesAgo(8);
        $this->assertSame('ok', LiveSource::health('wu')['state']);
        $this->assertSame('late', LiveSource::health('ecoLcl')['state']);

        WeatherReading::query()->delete();
        $this->readingMinutesAgo(30);
        $this->assertSame('late', LiveSource::health('wu')['state']);
        $this->assertSame('down', LiveSource::health('ecoLcl')['state']);
    }

    // ── no duplicate readings ───────────────────────────────────────────────

    /** What the fake Weather Underground API answers next. */
    private array $wu = [];

    private function fakeWu(int $epoch, float $temperature): void
    {
        $this->wu = ['observations' => [[
            'epoch' => $epoch,
            'humidity' => 81,
            'winddir' => 230,
            'metric' => ['temp' => $temperature, 'dewpt' => 9.1, 'pressure' => 1013.2, 'windSpeed' => 12, 'windGust' => 18, 'precipRate' => 0, 'precipTotal' => 1.2],
        ]]];
        // One fake that reads the current answer: a second Http::fake would not replace the first.
        Http::fake(['api.weather.com/*' => fn () => Http::response($this->wu)]);
    }

    private function fetch(): void
    {
        $this->artisan('weather:fetch', ['--save' => true]);
    }

    public function test_the_same_cloud_observation_is_stored_once(): void
    {
        $this->source('wu');
        Setting::setValue('wunderground.api_key', 'key', 'encrypted', 'wunderground');
        Setting::setValue('wunderground.station_id', 'IUNIT1', 'string', 'wunderground');
        $epoch = now()->subMinutes(4)->timestamp;

        $this->fakeWu($epoch, 12.5);
        $this->fetch();
        $this->fetch();
        $this->fetch();

        $this->assertSame(1, WeatherReading::count());
    }

    public function test_a_new_cloud_observation_is_stored(): void
    {
        $this->source('wu');
        Setting::setValue('wunderground.api_key', 'key', 'encrypted', 'wunderground');
        Setting::setValue('wunderground.station_id', 'IUNIT1', 'string', 'wunderground');

        $this->fakeWu(now()->subMinutes(9)->timestamp, 12.5);
        $this->fetch();
        $this->fakeWu(now()->subMinutes(4)->timestamp, 12.9);
        $this->fetch();

        $this->assertSame(2, WeatherReading::count());
        $this->assertSame(12.9, (float) WeatherReading::mostRecent()->temperature);
    }

    /**
     * End to end: a station uploading every five minutes, fetched every
     * minute for half an hour. One row per upload, and Online throughout.
     */
    public function test_half_an_hour_of_a_station_uploading_every_five_minutes(): void
    {
        $this->source('wu');
        Setting::setValue('wunderground.api_key', 'key', 'encrypted', 'wunderground');
        Setting::setValue('wunderground.station_id', 'IUNIT1', 'string', 'wunderground');
        $start = Carbon::parse('2026-10-15 08:00:00');

        for ($minute = 0; $minute < 30; $minute++) {
            Carbon::setTestNow($start->copy()->addMinutes($minute));
            // WU serves the upload from the last five-minute mark, a few minutes late.
            $upload = $start->copy()->addMinutes(intdiv(max(0, $minute - 3), 5) * 5);
            $this->fakeWu($upload->timestamp, 12.0 + $minute / 10);
            $this->fetch();

            $this->assertFalse($this->sensorHealth()['is_stale'], "minute {$minute}");
        }

        $this->assertSame(6, WeatherReading::count());
    }

    // ── reading time zone ───────────────────────────────────────────────────

    /**
     * A cloud reading's time arrives as a Unix timestamp, which Carbon tags
     * "+00:00" rather than "UTC". The writer only converted "UTC" and "GMT",
     * so the UTC wall time was stored as if it were local: on a site in the
     * Netherlands every Weather Underground reading looked two hours old.
     */
    public function test_a_cloud_reading_is_stored_in_the_site_time_zone(): void
    {
        config(['app.timezone' => 'Europe/Amsterdam']);
        $this->source('wu');
        Setting::setValue('wunderground.api_key', 'key', 'encrypted', 'wunderground');
        Setting::setValue('wunderground.station_id', 'IUNIT1', 'string', 'wunderground');
        $measured = Carbon::parse('2026-10-15 10:00:00', 'UTC');

        $this->fakeWu($measured->timestamp, 12.5);
        $this->fetch();

        $stored = \Illuminate\Support\Facades\DB::table('weather_readings')->value('recorded_at');
        $this->assertSame('2026-10-15 12:00:00', $stored);
    }
}
