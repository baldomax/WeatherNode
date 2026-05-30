<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        Setting::setValue('system.registration_enabled', true, 'boolean', 'system');

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Setting::setValue('system.registration_enabled', true, 'boolean', 'system');

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_is_closed_by_default_when_setting_missing(): void
    {
        Setting::query()->where('key', 'system.registration_enabled')->delete();

        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
    }
}
