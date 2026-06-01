<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_screen_is_available_on_fresh_install(): void
    {
        $this->get(route('setup.admin.create'))
            ->assertOk()
            ->assertSee('First-run setup');
    }

    public function test_setup_can_create_first_admin(): void
    {
        $response = $this->post(route('setup.admin.store'), [
            'name' => 'Docker Admin',
            'email' => 'docker-admin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'name' => 'Docker Admin',
            'email' => 'docker-admin@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_setup_is_disabled_after_admin_exists(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->get(route('setup.admin.create'))
            ->assertNotFound();
    }

    public function test_setup_is_disabled_when_any_user_exists(): void
    {
        User::factory()->create(['is_admin' => false]);

        $this->get(route('setup.admin.create'))
            ->assertNotFound();
    }

    public function test_login_screen_shows_setup_link_when_pending(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('setup.admin.create', absolute: false));
    }
}
