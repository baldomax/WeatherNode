<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The coffee button is an image drawn on Buy Me a Coffee's side, and it is
 * served with a one year cache header. Whatever it said the day a browser
 * first loaded it is what that browser keeps showing, long after the real
 * figure has moved on.
 *
 * A date in the query string gives it a new address each day, so the picture
 * is at most a day behind instead of a year.
 */
class CoffeeButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_button_image_gets_a_fresh_address_each_day(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('button-api', false)
            ->assertSee('&v=' . now()->format('Y-m-d'), false);
    }

    public function test_yesterdays_address_is_not_the_one_served(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertDontSee('&v=' . now()->subDay()->format('Y-m-d'), false);
    }
}
