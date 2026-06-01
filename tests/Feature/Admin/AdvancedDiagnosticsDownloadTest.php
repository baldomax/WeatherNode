<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedDiagnosticsDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_download_diagnostics_snapshot(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.advanced.diagnostics'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertHeader('content-disposition');
    }

    public function test_non_admin_cannot_download_diagnostics_snapshot(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->post(route('admin.settings.advanced.diagnostics'));

        $response->assertRedirect(route('dashboard'));
    }
}
