<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Update\CompatibilityChecker;
use App\Services\Update\DeployerService;
use App\Services\Update\GithubReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateViaGitSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_update_via_git_rejects_malicious_version_input(): void
    {
        config()->set('updater.enabled', true);
        config()->set('updater.allow_git', true);

        $this->mock(DeployerService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('updateViaGit');
        });

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.git'), [
                'version' => 'v1.2.3; touch /tmp/pwned',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_update_via_git_accepts_valid_version_and_calls_deployer(): void
    {
        config()->set('updater.enabled', true);
        config()->set('updater.allow_git', true);

        $this->mock(DeployerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateViaGit')
                ->once()
                ->with('v1.2.3')
                ->andReturn([
                    'success' => true,
                    'message' => 'ok',
                ]);
        });

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.git'), [
                'version' => 'v1.2.3',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'ok',
        ]);
    }

    public function test_deploy_rejects_malicious_version_input(): void
    {
        config()->set('updater.enabled', true);

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.deploy'), [
                'version' => '../v1.2.3',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_preview_rejects_malicious_version_input(): void
    {
        config()->set('updater.enabled', true);

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.preview'), [
                'version' => 'v1.2.3 && whoami',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_rollback_rejects_malicious_version_input_and_does_not_call_deployer(): void
    {
        config()->set('updater.enabled', true);

        $this->mock(DeployerService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('rollback');
        });

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.rollback'), [
                'version' => 'v1.2.3/../../evil',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version']);
    }

    public function test_deploy_rejects_release_without_trusted_checksum(): void
    {
        config()->set('updater.enabled', true);
        config()->set('updater.require_checksum', true);
        config()->set('updater.validate_before_deploy', false);

        $this->mock(CompatibilityChecker::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkCompatibility')
                ->once()
                ->andReturn(['supported' => true]);
        });

        $this->mock(GithubReleaseService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReleases')
                ->once()
                ->andReturn([
                    [
                        'tag' => 'v1.2.3',
                        'sha256' => null,
                        'assets' => [
                            [
                                'name' => 'weathernode-deploy.zip',
                                'url' => 'https://example.invalid/weathernode-deploy.zip',
                                'is_deploy_zip' => true,
                                'sha256' => null,
                            ],
                        ],
                    ],
                ]);
            $mock->shouldNotReceive('downloadAsset');
        });

        $this->mock(DeployerService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('deploy');
        });

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.deploy'), [
                'version' => 'v1.2.3',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_deploy_rejects_when_downloaded_zip_checksum_mismatches_trusted_value(): void
    {
        config()->set('updater.enabled', true);
        config()->set('updater.require_checksum', true);
        config()->set('updater.validate_before_deploy', false);

        $trustedChecksum = hash('sha256', 'expected-archive');

        $this->mock(CompatibilityChecker::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkCompatibility')
                ->once()
                ->andReturn(['supported' => true]);
        });

        $this->mock(GithubReleaseService::class, function (MockInterface $mock) use ($trustedChecksum): void {
            $mock->shouldReceive('getReleases')
                ->once()
                ->andReturn([
                    [
                        'tag' => 'v1.2.3',
                        'sha256' => $trustedChecksum,
                        'assets' => [
                            [
                                'name' => 'weathernode-deploy.zip',
                                'url' => 'https://example.invalid/weathernode-deploy.zip',
                                'is_deploy_zip' => true,
                                'sha256' => $trustedChecksum,
                            ],
                        ],
                    ],
                ]);

            $mock->shouldReceive('downloadAsset')
                ->once()
                ->andReturnUsing(function (string $url, string $destinationPath): bool {
                    file_put_contents($destinationPath, 'different-archive-content');
                    return true;
                });
        });

        $this->mock(DeployerService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('deploy');
        });

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.updates.deploy'), [
                'version' => 'v1.2.3',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
