<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RadarSourceRegistry;
use Tests\TestCase;

class RadarSourceRegistryTest extends TestCase
{
    public function test_it_lists_the_selectable_sources(): void
    {
        $ids = array_keys(RadarSourceRegistry::all());

        $this->assertContains('rainviewer', $ids);
        $this->assertContains('knmi', $ids);
        $this->assertContains('buienradar', $ids);
    }

    public function test_each_source_declares_a_label_and_its_coverage(): void
    {
        foreach (RadarSourceRegistry::all() as $id => $source) {
            $this->assertArrayHasKey('label', $source, $id);
            $this->assertArrayHasKey('coverage', $source, $id);
        }

        $this->assertSame('Netherlands', RadarSourceRegistry::all()['knmi']['coverage']);
        $this->assertSame('Worldwide', RadarSourceRegistry::all()['rainviewer']['coverage']);
    }

    public function test_selected_reads_the_comma_separated_setting(): void
    {
        $this->assertSame(['knmi', 'buienradar'], RadarSourceRegistry::parse('knmi,buienradar'));
        $this->assertSame(['rainviewer'], RadarSourceRegistry::parse(' rainviewer '));
        $this->assertSame([], RadarSourceRegistry::parse(''));
    }

    public function test_unknown_ids_are_discarded_rather_than_rendered(): void
    {
        $this->assertSame(['knmi'], RadarSourceRegistry::parse('knmi,not-a-source'));
    }

    /** Whatever else is chosen, the main provider is always shown. */
    public function test_the_main_provider_is_always_included(): void
    {
        $this->assertSame(['rainviewer'], RadarSourceRegistry::visible('', 'rainviewer'));
        $this->assertSame(['rainviewer', 'knmi'], RadarSourceRegistry::visible('knmi', 'rainviewer'));
        $this->assertSame(['knmi'], RadarSourceRegistry::visible('knmi', 'knmi'));
    }

    public function test_visible_keeps_registry_order_so_tabs_do_not_shuffle(): void
    {
        $this->assertSame(
            array_values(array_intersect(array_keys(RadarSourceRegistry::all()), ['rainviewer', 'knmi', 'buienradar'])),
            RadarSourceRegistry::visible('buienradar,knmi', 'rainviewer')
        );
    }
}
