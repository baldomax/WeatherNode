<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Forecast;

use App\Models\Setting;
use App\Services\Forecast\AemetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AemetServiceTest extends TestCase
{
    use RefreshDatabase;

    private const DATOS_URL = 'https://opendata.aemet.es/opendata/sh/abc123';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** Mirrors SettingsSeeder: an encrypted setting holding a literal empty value. */
    private function seedUnconfigured(): void
    {
        Setting::create(['key' => 'aemet.api_key', 'value' => '', 'type' => 'encrypted', 'group' => 'aemet', 'description' => 'AEMET OpenData API Key']);
        Setting::create(['key' => 'aemet.municipio', 'value' => '', 'type' => 'string', 'group' => 'aemet', 'description' => 'AEMET Municipio Code']);
    }

    private function configure(string $municipio = '28079'): void
    {
        Setting::setValue('aemet.api_key', 'test-key', 'encrypted', 'aemet');
        Setting::setValue('aemet.municipio', $municipio, 'string', 'aemet');
    }

    private function hourlyPayload(): array
    {
        $date = now()->addDay()->format('Y-m-d');

        return [[
            'prediccion' => ['dia' => [[
                'fecha' => $date . 'T00:00:00',
                'temperatura' => [
                    ['periodo' => '12', 'value' => '21'],
                    ['periodo' => '13', 'value' => '23'],
                ],
                'vientoAndRachaMax' => [
                    ['periodo' => '12', 'value' => [['velocidad' => ['14'], 'direccion' => ['NE']]]],
                ],
                'precipitacion' => [['periodo' => '12', 'value' => '0']],
                'estadoCielo' => [['periodo' => '12', 'value' => '11', 'descripcion' => 'Despejado']],
            ]]],
        ]];
    }

    /**
     * getCastedValue() returns null for an encrypted setting with an empty
     * stored value, so a typed string property blew up before the configured
     * check could run. Selecting AEMET before entering a key 500s the site.
     */
    public function test_constructs_when_no_credentials_are_configured(): void
    {
        $this->seedUnconfigured();

        $service = new AemetService();

        $this->assertNull($service->fetchForecast());
        $this->assertSame([], $service->getDailyForecast());
        $this->assertSame([], $service->getHourlyForecast());
    }

    public function test_follows_the_two_step_datos_url_and_sends_the_api_key(): void
    {
        $this->configure();
        Http::fake([
            'opendata.aemet.es/opendata/api/*' => Http::response(['estado' => 200, 'datos' => self::DATOS_URL]),
            self::DATOS_URL => Http::response($this->hourlyPayload()),
        ]);

        $data = (new AemetService())->fetchForecast();

        $this->assertIsArray($data);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/prediccion/')
            && $request->header('api_key') === ['test-key']);
        Http::assertSent(fn ($request) => $request->url() === self::DATOS_URL);
    }

    public function test_does_not_hammer_the_upstream_after_a_failure(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 500)]);

        $service = new AemetService();
        $this->assertNull($service->fetchForecast());
        $countAfterFirst = count(Http::recorded());

        $this->assertNull($service->fetchForecast());

        $this->assertSame(
            $countAfterFirst,
            count(Http::recorded()),
            'A failed fetch should be remembered briefly instead of retried on every call.'
        );
    }

    public function test_rejects_a_municipio_that_is_not_a_five_digit_ine_code(): void
    {
        $this->configure('../../etc/passwd');
        Http::fake();

        $this->assertNull((new AemetService())->fetchForecast());

        Http::assertNothingSent();
    }

    public function test_parses_hourly_entries_into_the_standard_payload(): void
    {
        $this->configure();
        Http::fake([
            'opendata.aemet.es/opendata/api/*' => Http::response(['estado' => 200, 'datos' => self::DATOS_URL]),
            self::DATOS_URL => Http::response($this->hourlyPayload()),
        ]);

        $hourly = (new AemetService())->getHourlyForecast(48);

        $this->assertNotEmpty($hourly);
        $first = $hourly[0];
        $this->assertSame(21.0, $first['temperature']);
        $this->assertSame('clearsky', $first['symbol']);
        $this->assertSame(45, $first['wind_direction']);
        $this->assertArrayHasKey('time', $first);
    }

    /**
     * AEMET publishes vientoAndRachaMax entries as the wind record itself, with
     * velocidad and direccion as single element arrays, rather than as a list.
     */
    public function test_reads_wind_from_the_unwrapped_aemet_shape(): void
    {
        $this->configure();
        $date = now()->addDay()->format('Y-m-d');
        Http::fake([
            'opendata.aemet.es/opendata/api/*' => Http::response(['estado' => 200, 'datos' => self::DATOS_URL]),
            self::DATOS_URL => Http::response([[
                'prediccion' => ['dia' => [[
                    'fecha' => $date . 'T00:00:00',
                    'temperatura' => [['periodo' => '12', 'value' => '21']],
                    'vientoAndRachaMax' => [
                        ['periodo' => '12', 'direccion' => ['SE'], 'velocidad' => ['20']],
                    ],
                    'estadoCielo' => [['periodo' => '12', 'value' => '43', 'descripcion' => 'Lluvia']],
                ]]],
            ]]),
        ]);

        $first = (new AemetService())->getHourlyForecast()[0];

        $this->assertSame(20.0, $first['wind_speed']);
        $this->assertSame(135, $first['wind_direction']);
        $this->assertSame('rain', $first['symbol']);
    }

    public function test_tolerates_a_day_entry_with_no_date(): void
    {
        $this->configure();
        Http::fake([
            'opendata.aemet.es/opendata/api/*' => Http::response(['estado' => 200, 'datos' => self::DATOS_URL]),
            self::DATOS_URL => Http::response([[
                'prediccion' => ['dia' => [
                    ['temperatura' => [['periodo' => '12', 'value' => '20']]],
                ]],
            ]]),
        ]);

        $this->assertIsArray((new AemetService())->getHourlyForecast());
    }
}
