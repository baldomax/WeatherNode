<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReclaimDatabaseSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_without_changing_anything_on_a_dry_run(): void
    {
        $this->artisan('db:reclaim-space --dry-run')
            ->expectsOutputToContain('reclaimable')
            ->assertExitCode(0);
    }

    public function test_it_declines_when_there_is_little_to_reclaim(): void
    {
        $this->artisan('db:reclaim-space')
            ->expectsOutputToContain('Below the threshold')
            ->assertExitCode(0);
    }

    /**
     * The test suite runs inside a transaction, which is also the one place
     * application code could realistically call this from. SQLite refuses to
     * VACUUM there, so it should say so rather than surface a PDO error.
     */
    public function test_it_refuses_to_compact_inside_a_transaction(): void
    {
        if (config('database.connections.' . config('database.default') . '.driver') !== 'sqlite') {
            $this->markTestSkipped('Compaction only applies to SQLite.');
        }

        $this->assertGreaterThan(0, DB::transactionLevel(), 'RefreshDatabase should hold a transaction open');

        $this->artisan('db:reclaim-space --force')
            ->expectsOutputToContain('cannot be compacted inside a transaction')
            ->assertExitCode(0);
    }

    public function test_it_is_a_no_op_on_other_drivers(): void
    {
        // The command reads the driver from config and never opens a
        // connection, so no MySQL server is needed. The default is put back
        // before the test ends, otherwise RefreshDatabase tries to roll its
        // transaction back on the swapped connection during teardown.
        $original = config('database.default');
        config(['database.default' => 'mysql']);

        try {
            $this->artisan('db:reclaim-space')
                ->expectsOutputToContain('Nothing to do')
                ->assertExitCode(0);
        } finally {
            config(['database.default' => $original]);
        }
    }
}
