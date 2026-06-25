<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Alerts\MeteoalarmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeteoalarmServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::setValue('alerts.region_code', 'NL011', 'string', 'alerts');
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/meteoalarm/' . $name);
    }

    /** Fake the Atom feed + per-warning CAP documents from captured fixtures. */
    private function fakeMeteoalarm(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'meteoalarm-legacy-atom-netherlands')) {
                return Http::response($this->fixture('netherlands.atom'), 200);
            }
            if (str_contains($url, 'cap-nl011-heat-severe')) {
                return Http::response($this->fixture('cap-nl011-heat-severe.xml'), 200);
            }
            if (str_contains($url, 'cap-nl011-wind-severe')) {
                return Http::response($this->fixture('cap-nl011-wind-severe.xml'), 200);
            }
            return Http::response('not found', 404);
        });
    }

    private function alertOfType(array $alerts, string $type): array
    {
        foreach ($alerts as $a) {
            if (($a['warning_type'] ?? null) === $type) {
                return $a;
            }
        }
        $this->fail("No alert of type {$type}");
    }

    public function test_returns_active_alerts_for_configured_region(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        $alerts = array_values((new MeteoalarmService())->getActiveAlerts());

        $this->assertCount(2, $alerts);
        $types = array_column($alerts, 'warning_type');
        $this->assertContains('high-temperature', $types);
        $this->assertContains('wind', $types);
    }

    public function test_maps_severity_word_to_level_and_color(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        $heat = $this->alertOfType((new MeteoalarmService())->getActiveAlerts(), 'high-temperature');

        $this->assertSame(3, $heat['severity']);            // Severe -> 3
        $this->assertSame('#F19E39', $heat['severity_color']); // orange
        $this->assertSame('NL011', $heat['region']);
    }

    public function test_deduplicates_same_type_keeping_highest_active_severity(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        $alerts = (new MeteoalarmService())->getActiveAlerts();
        $heat = array_values(array_filter($alerts, fn ($a) => $a['warning_type'] === 'high-temperature'));

        $this->assertCount(1, $heat);       // Moderate + Severe collapse to one
        $this->assertSame(3, $heat[0]['severity']); // Severe wins over Moderate
    }

    public function test_excludes_expired_warnings(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        $alerts = (new MeteoalarmService())->getActiveAlerts();

        // The only Extreme (level 4) warning is expired -> no level-4 alert remains
        $this->assertNotContains(4, array_column($alerts, 'severity'));
    }

    public function test_excludes_other_regions(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        $alerts = (new MeteoalarmService())->getActiveAlerts();

        foreach ($alerts as $a) {
            $this->assertSame('NL011', $a['region']);
        }
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'cap-nl009'));
    }

    public function test_uses_localized_description_for_app_locale(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        $heat = $this->alertOfType((new MeteoalarmService())->getActiveAlerts(), 'high-temperature');
        $this->assertStringContainsString('Extreem warm', $heat['description']);
    }

    public function test_uses_english_description_for_english_locale(): void
    {
        app()->setLocale('en');
        $this->fakeMeteoalarm();

        $heat = $this->alertOfType((new MeteoalarmService())->getActiveAlerts(), 'high-temperature');
        $this->assertStringContainsString('heat index', $heat['description']);
    }

    public function test_only_fetches_cap_documents_for_surviving_alerts(): void
    {
        app()->setLocale('nl');
        $this->fakeMeteoalarm();

        (new MeteoalarmService())->getActiveAlerts();

        // 1 feed request + exactly 2 CAP requests (heat + wind survivors)
        Http::assertSentCount(3);
    }

    public function test_returns_empty_when_feed_request_fails(): void
    {
        app()->setLocale('nl');
        Http::fake(['feeds.meteoalarm.org/*' => Http::response('error', 500)]);

        $this->assertSame([], (new MeteoalarmService())->getActiveAlerts());
    }

    public function test_falls_back_to_feed_data_when_cap_document_unavailable(): void
    {
        app()->setLocale('nl');
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'meteoalarm-legacy-atom-netherlands')) {
                return Http::response($this->fixture('netherlands.atom'), 200);
            }
            return Http::response('gone', 404); // every CAP document fails
        });

        $heat = $this->alertOfType((new MeteoalarmService())->getActiveAlerts(), 'high-temperature');

        // Alert still surfaces with feed-derived severity + the English event as text.
        $this->assertSame(3, $heat['severity']);
        $this->assertStringContainsString('high-temperature warning', $heat['description']);
    }
}
