<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DockerAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_screen_is_not_available_when_not_containerized(): void
    {
        config(['app.containerized' => false]);

        $this->get(route('docker.setup.admin.create'))
            ->assertNotFound();
    }

    public function test_setup_screen_is_available_when_containerized_and_no_users_exist(): void
    {
        config(['app.containerized' => true]);

        $this->get(route('docker.setup.admin.create'))
            ->assertOk()
            ->assertSee('Docker first-run setup');
    }

    public function test_setup_can_create_first_admin_when_containerized(): void
    {
        config(['app.containerized' => true]);

        $response = $this->post(route('docker.setup.admin.store'), [
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
        config(['app.containerized' => true]);
        User::factory()->create(['is_admin' => true]);

        $this->get(route('docker.setup.admin.create'))
            ->assertNotFound();
    }

    public function test_setup_is_disabled_when_any_user_exists(): void
    {
        config(['app.containerized' => true]);
        User::factory()->create(['is_admin' => false]);

        $this->get(route('docker.setup.admin.create'))
            ->assertNotFound();
    }

    public function test_login_screen_shows_setup_link_when_pending(): void
    {
        config(['app.containerized' => true]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('docker.setup.admin.create', absolute: false));
    }
}
