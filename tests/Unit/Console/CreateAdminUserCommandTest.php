<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_email_in_non_interactive_mode(): void
    {
        $exitCode = Artisan::call('admin:create');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing required option --email', Artisan::output());
        $this->assertSame(0, User::query()->count());
    }

    public function test_command_creates_admin_user_with_options(): void
    {
        $exitCode = Artisan::call('admin:create', [
            '--email' => 'owner@example.com',
            '--name' => 'Site Owner',
            '--password' => 'StrongPass123!',
        ]);

        $this->assertSame(0, $exitCode);

        $admin = User::query()->where('email', 'owner@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertSame('Site Owner', $admin->name);
        $this->assertTrue(Hash::check('StrongPass123!', (string) $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_command_rejects_existing_non_admin_without_promote_flag(): void
    {
        $user = User::factory()->create([
            'email' => 'existing-user@example.com',
            'name' => 'Existing User',
            'password' => Hash::make('OldPass123!'),
            'is_admin' => false,
        ]);

        $exitCode = Artisan::call('admin:create', [
            '--email' => 'existing-user@example.com',
            '--name' => 'Existing User Updated',
            '--password' => 'StrongPass123!',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Re-run with --promote', Artisan::output());
        $this->assertFalse((bool) $user->fresh()?->is_admin);
        $this->assertSame('Existing User', (string) $user->fresh()?->name);
        $this->assertTrue(Hash::check('OldPass123!', (string) $user->fresh()?->password));
    }

    public function test_command_promotes_existing_non_admin_with_promote_flag(): void
    {
        $user = User::factory()->create([
            'email' => 'existing-user@example.com',
            'name' => 'Existing User',
            'password' => Hash::make('OldPass123!'),
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $exitCode = Artisan::call('admin:create', [
            '--email' => 'existing-user@example.com',
            '--name' => 'Promoted Admin',
            '--password' => 'StrongPass123!',
            '--promote' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) $user->fresh()?->is_admin);
        $this->assertSame('Promoted Admin', (string) $user->fresh()?->name);
        $this->assertTrue(Hash::check('StrongPass123!', (string) $user->fresh()?->password));
        $this->assertNotNull($user->fresh()?->email_verified_at);
    }

    public function test_command_does_not_modify_existing_admin_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Original Admin',
            'password' => Hash::make('OldPass123!'),
            'is_admin' => true,
        ]);

        $exitCode = Artisan::call('admin:create', [
            '--email' => 'admin@example.com',
            '--name' => 'Updated Name',
            '--password' => 'StrongPass123!',
            '--promote' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', Artisan::output());
        $this->assertSame('Original Admin', (string) $admin->fresh()?->name);
        $this->assertTrue(Hash::check('OldPass123!', (string) $admin->fresh()?->password));
        $this->assertTrue((bool) $admin->fresh()?->is_admin);
    }
}
