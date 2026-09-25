<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #105. The manufacturer shipped as Fine Offset, which is the author's
 * own kit. It is published to the community map, so every install that turned
 * sharing on announced hardware it may never have owned.
 */
class StationHardwareDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_claims_no_hardware(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('', (string) Setting::getValue('station.manufacturer'));
        $this->assertSame('', (string) Setting::getValue('station.hardware'));
    }

    /** The list needs somewhere for "I have not said" to sit. */
    public function test_the_list_offers_nothing_chosen(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertArrayHasKey('', Setting::find('station.manufacturer')->getOptionsArray());
    }
}
