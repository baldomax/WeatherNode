<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use App\Services\Weather\Sources\WeatherLinkLiveLocalAdapter;
use App\Support\LiveSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * #158. A WeatherLink Live could be set up and tested on the WeatherLink page,
 * but nothing read it into the readings, and the Live Data page could not
 * choose it, so owners fell back to Weather Underground or the WeatherLink
 * cloud and got data five to ten minutes old.
 *
 * The fixture is Davis's own sample of /v1/current_conditions, comments
 * removed: https://github.com/weatherlink/weatherlink-live-local-api/blob/master/API.md
 * Its values are Davis's demo values (1.1 %RH and so on), kept as they are.
 */
class WeatherLinkLiveLocalSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::setValue('weatherlink.wll_ip', '192.168.1.20', 'string', 'weatherlink');
        Setting::setValue('weatherlink.wll_port', '80', 'integer', 'weatherlink');
    }

    private function sample(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/WeatherLinkLive/current_conditions.json')), true);
    }

    /** What the fake device answers next. */
    private array $device = [];

    private function answer(array $response): void
    {
        $this->device = $response;
        // One fake that reads the current answer: a second Http::fake would not replace the first.
        Http::fake(['192.168.1.20/v1/current_conditions' => fn () => Http::response($this->device)]);
    }

    private function iss(int $txid, array $values): array
    {
        return ['lsid' => 1000 + $txid, 'data_structure_type' => 1, 'txid' => $txid] + $values;
    }

    // ── reading the device ──────────────────────────────────────────────────

    public function test_davis_sample_is_read_in_metric(): void
    {
        $this->answer($this->sample());

        $reading = app(WeatherLinkLiveLocalAdapter::class)->fetch();

        $this->assertSame(17.1, $reading['temperature']);          // 62.7 °F
        $this->assertSame(3.2, $reading['wind_speed']);             // 2 mph
        $this->assertSame(12.9, $reading['wind_gust']);             // 8 mph, highest of the last 10 min
        $this->assertSame(12.6, $reading['rain_daily']);            // 63 counts of 0.2 mm
        $this->assertSame(1016.2, $reading['pressure_rel']);        // 30.008 inHg
        $this->assertSame(1016.2, $reading['pressure_abs']);
        $this->assertSame(25.6, $reading['temperature_indoor']);    // 78.0 °F
        $this->assertSame(747, $reading['solar_radiation']);
        $this->assertTrue(\Carbon\Carbon::createFromTimestamp(1531754005)->equalTo($reading['recorded_at']));
    }

    /**
     * An extra temperature/humidity transmitter also reports as an ISS record
     * (type 1), with no wind or rain. It used to overwrite the station's own
     * values whenever it came later in the list.
     */
    public function test_an_extra_transmitter_does_not_replace_the_station(): void
    {
        $this->answer(['data' => ['did' => 'X', 'ts' => now()->timestamp, 'conditions' => [
            $this->iss(1, ['temp' => 50.0, 'hum' => 80, 'wind_speed_last' => 5, 'wind_dir_last' => 180, 'rain_size' => 2, 'rainfall_daily' => 10]),
            $this->iss(2, ['temp' => 68.0, 'hum' => 40, 'wind_speed_last' => null, 'rain_size' => null, 'rainfall_daily' => null]),
        ]], 'error' => null]);

        $reading = app(WeatherLinkLiveLocalAdapter::class)->fetch();

        $this->assertSame(10.0, $reading['temperature']);
        $this->assertSame(80, $reading['humidity']);
        $this->assertSame(180, $reading['wind_direction']);
        // The extra transmitter is kept as an extra sensor on its own channel.
        $this->assertSame(20.0, $reading['temp_2']);
        $this->assertSame(40, $reading['humidity_2']);
    }

    /** The station is found by its wind and rain, wherever it sits in the list. */
    public function test_the_station_can_be_any_transmitter(): void
    {
        $this->answer(['data' => ['did' => 'X', 'ts' => now()->timestamp, 'conditions' => [
            $this->iss(3, ['temp' => 68.0, 'hum' => 40]),
            $this->iss(5, ['temp' => 50.0, 'hum' => 80, 'wind_speed_last' => 5, 'rain_size' => 1, 'rainfall_daily' => 10]),
        ]], 'error' => null]);

        $reading = app(WeatherLinkLiveLocalAdapter::class)->fetch();

        $this->assertSame(10.0, $reading['temperature']);
        $this->assertSame(2.54, $reading['rain_daily']);           // 10 counts of 0.01"
        $this->assertSame(20.0, $reading['temp_3']);
    }

    /** JSON numbers can arrive as 63.0; the rain conversion took only whole numbers. */
    public function test_rain_counts_written_with_a_decimal_point_are_read(): void
    {
        $sample = $this->sample();
        $sample['data']['conditions'][0]['rainfall_daily'] = 63.0;
        $this->answer($sample);

        $this->assertSame(12.6, app(WeatherLinkLiveLocalAdapter::class)->fetch()['rain_daily']);
    }

    // ── as the live data source ─────────────────────────────────────────────

    public function test_the_live_data_page_offers_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.group', 'livedata'))
            ->assertOk()
            ->assertSee('value="wll_local"', false)
            ->assertSee(route('admin.settings.group', 'weatherlink'), false);

        $this->actingAs($admin)->post(route('admin.settings.update', 'livedata'), ['livedata_format' => 'wll_local']);
        $this->assertSame('wll_local', Setting::getValue('livedata.format'));
    }

    public function test_weather_fetch_stores_a_reading_every_minute(): void
    {
        Setting::setValue('livedata.format', 'wll_local', 'select', 'livedata');
        $sample = $this->sample();

        foreach ([0, 1, 2] as $minute) {
            $this->travelTo(now()->addMinute());
            Cache::flush();
            $sample['data']['ts'] = now()->timestamp;
            $sample['data']['conditions'][0]['temp'] = 62.7 + $minute;
            $this->answer($sample);
            $this->artisan('weather:fetch', ['--save' => true])->assertExitCode(0);
        }

        $this->assertSame(3, WeatherReading::count());
        $this->assertSame(18.2, (float) WeatherReading::mostRecent()->temperature);   // 64.7 °F
    }

    /** It is on the owner's own network and sends every 2.5 seconds: a local source. */
    public function test_it_counts_as_a_local_source(): void
    {
        $this->assertSame(['stale' => 5, 'down' => 15], LiveSource::limits('wll_local'));

        Setting::setValue('livedata.format', 'wll_local', 'select', 'livedata');
        $card = LiveSource::card();
        $this->assertSame('WeatherLink Live (local network)', $card['name']);
        $this->assertSame('weatherlink', $card['settingsGroup']);
    }

    public function test_the_live_data_test_button_reads_the_device(): void
    {
        $this->answer($this->sample());
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('admin.settings.test-api'), ['service' => 'livedata', 'format' => 'wll_local'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_a_device_that_does_not_answer_is_a_failed_fetch(): void
    {
        Setting::setValue('livedata.format', 'wll_local', 'select', 'livedata');
        Http::fake(['192.168.1.20/*' => Http::response('', 500)]);

        $this->artisan('weather:fetch', ['--save' => true]);

        $this->assertSame(0, WeatherReading::count());
    }
}
