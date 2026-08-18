<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadarCardSourcesSettingTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_radar_group_offers_a_checkbox_for_each_known_source(): void
    {
        Setting::setValue('radar.card_sources', 'knmi', 'string', 'radar');

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.group', 'radar'));

        $response->assertOk();
        $response->assertSee('name="radar_card_sources[]"', false);
        $response->assertSee('value="knmi"', false);
        $response->assertSee('value="buienradar"', false);
        $response->assertSee('value="rainviewer"', false);
    }

    public function test_it_saves_several_sources(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'radar'), [
                'radar_card_sources' => ['knmi', 'buienradar'],
            ])
            ->assertRedirect();

        $this->assertSame('knmi,buienradar', Setting::getValue('radar.card_sources'));
    }

    public function test_clearing_every_checkbox_stores_an_empty_selection(): void
    {
        Setting::setValue('radar.card_sources', 'knmi,buienradar', 'string', 'radar');

        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'radar'), [])
            ->assertRedirect();

        $this->assertSame('', Setting::getValue('radar.card_sources'));
    }

    public function test_an_unknown_source_is_not_stored(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'radar'), [
                'radar_card_sources' => ['knmi', 'definitely-not-a-source'],
            ])
            ->assertRedirect();

        $this->assertSame('knmi', Setting::getValue('radar.card_sources'));
    }
}
