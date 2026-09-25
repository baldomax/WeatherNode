<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin list already refuses to delete your own account. The profile page
 * did not, so the only administrator could delete themselves.
 *
 * On a single-admin install that empties the users table, and /setup/admin
 * opens again to whoever reaches the site first. Even with other users left
 * behind it leaves an install nobody can administer.
 */
class LastAdminCannotVanishTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_only_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => bcrypt('Password123!')]);

        $this->actingAs($admin)
            ->delete(route('profile.destroy'), ['password' => 'Password123!'])
            ->assertSessionHasErrors(['password'], null, 'userDeletion');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /** And so the setup never reopens for a passer-by on a live install. */
    public function test_the_admin_setup_stays_shut(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => bcrypt('Password123!')]);

        $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'Password123!']);
        \Illuminate\Support\Facades\Auth::logout();

        $this->assertSame(1, User::query()->count());
        $this->get(route('setup.admin.create'))->assertNotFound();
        $this->post(route('setup.admin.store'), [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();
    }

    /** With a second admin in place, leaving is allowed. */
    public function test_one_of_two_admins_can_still_leave(): void
    {
        User::factory()->create(['is_admin' => true]);
        $leaving = User::factory()->create(['is_admin' => true, 'password' => bcrypt('Password123!')]);

        $this->actingAs($leaving)
            ->delete(route('profile.destroy'), ['password' => 'Password123!'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $leaving->id]);
    }

    /** A normal user leaving is nobody's business but their own. */
    public function test_an_ordinary_user_can_still_leave(): void
    {
        User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false, 'password' => bcrypt('Password123!')]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'Password123!']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
