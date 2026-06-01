<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalAdminEmail;
    private ?string $originalAdminPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAdminEmail = env('ADMIN_EMAIL');
        $this->originalAdminPassword = env('ADMIN_PASSWORD');
    }

    protected function tearDown(): void
    {
        $this->setEnvVar('ADMIN_EMAIL', $this->originalAdminEmail);
        $this->setEnvVar('ADMIN_PASSWORD', $this->originalAdminPassword);

        parent::tearDown();
    }

    public function test_seeder_is_noop_when_no_admin_exists_and_credentials_are_missing(): void
    {
        $this->setEnvVar('ADMIN_EMAIL', null);
        $this->setEnvVar('ADMIN_PASSWORD', null);

        (new AdminUserSeeder())->run();

        $this->assertSame(0, User::query()->count());
    }

    public function test_seeder_is_noop_when_admin_exists_even_if_credentials_are_configured(): void
    {
        $admin = User::factory()->create([
            'email' => 'existing-admin@example.com',
            'password' => Hash::make('existing-password'),
            'is_admin' => true,
        ]);

        $this->setEnvVar('ADMIN_EMAIL', 'seed-admin@example.com');
        $this->setEnvVar('ADMIN_PASSWORD', 'seed-pass-123');

        (new AdminUserSeeder())->run();

        $this->assertSame(1, User::query()->count());
        $this->assertTrue((bool) $admin->fresh()?->is_admin);
        $this->assertTrue(Hash::check('existing-password', (string) $admin->fresh()?->password));
        $this->assertSame('existing-admin@example.com', (string) $admin->fresh()?->email);
    }

    public function test_seeder_creates_admin_when_credentials_are_configured_and_no_admin_exists(): void
    {
        $this->setEnvVar('ADMIN_EMAIL', 'seed-admin@example.com');
        $this->setEnvVar('ADMIN_PASSWORD', 'seed-pass-123');

        (new AdminUserSeeder())->run();

        $admin = User::query()->where('email', 'seed-admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertTrue(Hash::check('seed-pass-123', (string) $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }

    private function setEnvVar(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
