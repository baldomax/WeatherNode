<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WeatherReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The day page linked to the day before it with no lower bound, so a crawler
 * could walk back from 2020 to the year 1800 and never finish. Every one of
 * those empty days returned 200 and asked to be indexed.
 */
class HistoryDayCrawlBoundsTest extends TestCase
{
    use RefreshDatabase;

    private function reading(string $date): void
    {
        WeatherReading::create([
            'recorded_at' => Carbon::parse($date.' 12:00:00'),
            'temperature' => 15.0,
        ]);
    }

    public function test_a_date_before_the_first_reading_is_gone(): void
    {
        $this->reading('2026-08-10');

        $this->get('/history/2026-08-09')->assertNotFound();
        $this->get('/history/1800-01-01')->assertNotFound();
    }

    public function test_a_date_in_the_future_is_gone(): void
    {
        $this->reading('2026-08-10');

        $this->get('/history/'.now()->addDay()->format('Y-m-d'))->assertNotFound();
    }

    public function test_the_first_day_does_not_link_further_back(): void
    {
        $this->reading('2026-08-10');
        $this->reading('2026-08-11');

        $this->get('/history/2026-08-10')
            ->assertOk()
            ->assertDontSee('/history/2026-08-09');
    }

    public function test_a_later_day_still_links_back(): void
    {
        $this->reading('2026-08-10');
        $this->reading('2026-08-11');

        $this->get('/history/2026-08-11')
            ->assertOk()
            ->assertSee('/history/2026-08-10');
    }

    public function test_a_day_with_data_is_indexable(): void
    {
        $this->reading('2026-08-10');

        $this->get('/history/2026-08-10')
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    /** A gap in the middle of the record is a real URL, but not one worth indexing. */
    public function test_a_day_with_no_data_inside_the_range_is_noindex(): void
    {
        $this->reading('2026-08-10');
        $this->reading('2026-08-14');

        $this->get('/history/2026-08-12')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }
}
