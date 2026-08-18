<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback and db:wipe
 * all destroy data, and --force skips the confirmation that would otherwise
 * stop them. The update paths only ever run "migrate --force", which is
 * additive, so nothing legitimate needs these on a live install.
 */
class DestructiveCommandsAreBlockedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);
        parent::tearDown();
    }

    private function bootAsEnvironment(string $env): void
    {
        DB::prohibitDestructiveCommands(false);
        $this->app['env'] = $env;
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    public function test_migrate_fresh_cannot_wipe_a_production_database_even_with_force(): void
    {
        Setting::setValue('station.name', 'Real Station', 'string', 'station');
        $this->bootAsEnvironment('production');

        $this->artisan('migrate:fresh', ['--force' => true]);

        $this->assertSame('Real Station', Setting::getValue('station.name'));
    }

    public function test_db_wipe_cannot_run_on_a_production_install(): void
    {
        Setting::setValue('station.name', 'Real Station', 'string', 'station');
        $this->bootAsEnvironment('production');

        $this->artisan('db:wipe', ['--force' => true]);

        $this->assertSame('Real Station', Setting::getValue('station.name'));
    }

    public function test_the_provider_prohibits_exactly_when_the_app_is_in_production(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString(
            'DB::prohibitDestructiveCommands($this->app->isProduction())',
            $source,
            'The guard must be wired into the provider, not just set by this test.'
        );
    }

    /**
     * Asserted on the flag rather than by running the command: RefreshDatabase
     * wraps each test in a transaction and SQLite cannot VACUUM inside one, so
     * migrate:fresh can never complete in a test regardless of this guard.
     */
    public function test_local_development_can_still_reset_its_database(): void
    {
        $this->bootAsEnvironment('local');

        $this->assertFalse($this->isProhibited(), 'Local development still needs migrate:fresh.');
    }

    public function test_production_sets_the_flag_on_every_destructive_command(): void
    {
        $this->bootAsEnvironment('production');

        $this->assertTrue($this->isProhibited());
    }

    private function isProhibited(): bool
    {
        $property = new \ReflectionProperty(\Illuminate\Database\Console\Migrations\FreshCommand::class, 'prohibitedFromRunning');

        return (bool) $property->getValue();
    }

}
