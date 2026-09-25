<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use App\Support\FirstRunSetup;
use Database\Seeders\FirstRunSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step two asks where the readings come from, then drops the owner on that
 * source's own settings page, which is where the keys and addresses live.
 */
class SetupSourceStepTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function atSourceStep(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::SOURCE);
    }

    public function test_the_source_step_opens(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->get(route('admin.setup.source'))
            ->assertOk();
    }

    /** The list is the one the settings page already offers, not a new one. */
    public function test_it_offers_the_formats_the_app_supports(): void
    {
        $this->atSourceStep();

        $response = $this->actingAs($this->admin())->get(route('admin.setup.source'));

        $response->assertSee('Ecowitt Local (push)');
        $response->assertSee('Weather Underground');
        $response->assertSee('WeatherFlow');
    }

    public function test_it_saves_the_choice_and_finishes_setup(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'wu']);

        $this->assertSame('wu', Setting::getValue('livedata.format'));
        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    /** Where the keys are entered, so that is where the owner is left. */
    public function test_it_lands_on_the_settings_page_for_that_source(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'wf'])
            ->assertRedirect(route('admin.settings.group', 'weatherflow'));
    }

    /** Not every format has a settings group of its own. */
    public function test_a_source_without_its_own_page_lands_on_live_data(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'weewx'])
            ->assertRedirect(route('admin.settings.group', 'livedata'));
    }

    /**
     * Found by walking the wizard over HTTP rather than in tests. Step two was
     * reachable and completable whatever step was owed, so posting to it with
     * step one unfinished left the install marked done with no location: the
     * exact state this feature exists to prevent.
     */
    public function test_step_two_sends_you_back_while_step_one_is_owed(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.source'))
            ->assertRedirect(route('admin.setup.station'));
    }

    public function test_step_two_cannot_be_saved_while_step_one_is_owed(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::STATION);

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'wu'])
            ->assertRedirect(route('admin.setup.station'));

        $this->assertSame(FirstRunSetup::STATION, FirstRunSetup::state());
    }

    /** A finished install has settings pages; it does not need the wizard. */
    public function test_a_finished_install_is_sent_away_from_the_wizard(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.source'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->admin())
            ->get(route('admin.setup.station'))
            ->assertRedirect(route('admin.dashboard'));
    }

    /** Saving it again must not reopen a setup that is over. */
    public function test_a_finished_install_cannot_be_pushed_back_into_setup(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->admin())->post(route('admin.setup.station.store'), [
            'name' => 'Testfield Weather',
            'latitude' => '52.1',
            'longitude' => '4.5',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    /** Putting it off and coming back has to work, or the notice lies. */
    public function test_the_wizard_can_be_resumed_after_being_put_off(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::SKIPPED);

        $this->actingAs($this->admin())
            ->get(route('admin.setup.station'))
            ->assertOk();
    }

    /**
     * The kit is part of what the community map shows, and nothing else in
     * the app ever asks. The manufacturer used to ship as Fine Offset, which
     * meant every install sharing itself claimed hardware it may not have.
     */
    public function test_it_asks_what_the_hardware_is(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->get(route('admin.setup.source'))
            ->assertOk()
            ->assertSee('name="manufacturer"', false)
            ->assertSee('name="hardware"', false);
    }

    public function test_it_saves_the_hardware(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())->post(route('admin.setup.source.store'), [
            'format' => 'ecoLcl',
            'manufacturer' => 'davis',
            'hardware' => 'Vantage Pro2',
        ]);

        $this->assertSame('davis', Setting::getValue('station.manufacturer'));
        $this->assertSame('Vantage Pro2', Setting::getValue('station.hardware'));
    }

    /** Saying nothing is allowed: it is a nicety, not a requirement. */
    public function test_the_hardware_may_be_left_blank(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'ecoLcl'])
            ->assertSessionHasNoErrors();

        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    public function test_it_rejects_a_manufacturer_it_does_not_know(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'ecoLcl', 'manufacturer' => 'acme'])
            ->assertSessionHasErrors('manufacturer');
    }

    public function test_it_rejects_a_format_the_app_does_not_know(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'not-a-station'])
            ->assertSessionHasErrors('format');

        $this->assertSame(FirstRunSetup::SOURCE, FirstRunSetup::state());
    }
}
