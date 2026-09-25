<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use App\Models\User;
use App\Services\Alerts\MeteoalarmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #105. Alerts shipped switched on and pointed at NL011, the Meteoalarm
 * code for Noord-Holland, so a fresh install anywhere in the world fetched
 * Dutch warnings and showed them as local.
 *
 * The saving path was worse than the seeded value: it wrote NL011 back
 * whenever alerts were saved without a region, so clearing the field in the
 * admin form quietly put it back.
 */
class AlertsRegionDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_has_no_region_and_no_alerts(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('', (string) Setting::getValue('alerts.region_code'));
        $this->assertFalse((bool) Setting::getValue('alerts.enabled'));
    }

    /** With no region set, the code default used to supply a Dutch one. */
    public function test_nothing_fetches_dutch_warnings_when_nothing_is_configured(): void
    {
        Http::fake();

        app(MeteoalarmService::class)->fetchAlerts();

        Http::assertNothingSent();
    }

    /**
     * Saving the alerts form without a region field at all, which is what a
     * non-European source posts, must not invent a Dutch one. An empty string
     * would not catch this: input() only falls back when the key is absent.
     */
    public function test_saving_without_a_region_field_does_not_invent_one(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/settings/alerts', [
            'alerts_enabled' => '1',
            'source' => 'usa',
        ]);

        $this->assertSame('', (string) Setting::getValue('alerts.region_code'), 'a Dutch region was written back');
    }

    /** A region the owner sets must still work. */
    public function test_a_configured_region_is_used(): void
    {
        Http::fake(['*' => Http::response('<feed></feed>', 200)]);
        Setting::setValue('alerts.region_code', 'DE031', 'string', 'alerts');

        app(MeteoalarmService::class)->fetchAlerts();

        Http::assertSent(fn ($request) => str_contains(strtolower($request->url()), 'germany')
            || str_contains(strtolower($request->url()), '-de'));
    }
}
