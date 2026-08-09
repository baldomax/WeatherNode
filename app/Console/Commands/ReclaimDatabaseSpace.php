<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Return space freed by the nightly purges to the filesystem.
 *
 * Several jobs delete rows continuously: visitor logs age out at
 * visitorlog.retention_days, radar tiles hourly, expired cache entries daily.
 * SQLite marks those pages free for reuse but never shrinks the file, so a
 * database that has churned for a while can be mostly empty space. Measured on
 * a real install: 37.8 MB free inside a 58.8 MB file, 64% of it.
 */
class ReclaimDatabaseSpace extends Command
{
    protected $signature = 'db:reclaim-space
        {--force : Reclaim even when the wasted space is below the threshold}
        {--dry-run : Report what would be reclaimed and exit}';

    protected $description = 'Compact the database, returning space freed by purges to the filesystem.';

    /** Not worth a full rewrite below this much waste. */
    private const MIN_WASTED_BYTES = 8 * 1024 * 1024;
    private const MIN_WASTED_RATIO = 0.20;

    public function handle(): int
    {
        // Read from config rather than DB::connection()->getDriverName(), which
        // resolves a connection: on a non-SQLite install there is nothing to do
        // and no reason to open one.
        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver", $default);

        if ($driver !== 'sqlite') {
            // InnoDB reuses free pages within the tablespace on its own, and
            // OPTIMIZE TABLE locks tables for the duration, so leave it alone.
            $this->info("Nothing to do: space is managed automatically on {$driver}.");

            return self::SUCCESS;
        }

        $before = $this->measure();
        if ($before === null) {
            $this->warn('Could not read database page statistics; skipping.');

            return self::SUCCESS;
        }

        [$fileBytes, $freeBytes] = $before;
        $ratio = $fileBytes > 0 ? $freeBytes / $fileBytes : 0.0;

        $this->line(sprintf(
            'Database %s, of which %s is reclaimable (%.0f%%).',
            $this->format($fileBytes),
            $this->format($freeBytes),
            $ratio * 100
        ));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (!$this->option('force') && ($freeBytes < self::MIN_WASTED_BYTES || $ratio < self::MIN_WASTED_RATIO)) {
            $this->info('Below the threshold, leaving it alone. Use --force to compact anyway.');

            return self::SUCCESS;
        }

        // SQLite refuses to VACUUM inside a transaction, and the raw driver
        // error is not obvious. Reachable if this is ever called from
        // application code rather than the scheduler.
        if (DB::transactionLevel() > 0) {
            $this->warn('Skipped: the database cannot be compacted inside a transaction.');

            return self::SUCCESS;
        }

        // VACUUM rewrites the database into a fresh file, so it needs room for
        // a second copy and holds an exclusive lock while it runs. Scheduled
        // off-peak for that reason.
        $this->info('Compacting...');

        try {
            DB::statement('VACUUM');
        } catch (\Throwable $e) {
            $this->error('Compaction failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $after = $this->measure();
        if ($after !== null) {
            $this->info(sprintf(
                'Done. %s -> %s, %s returned to the filesystem.',
                $this->format($fileBytes),
                $this->format($after[0]),
                $this->format(max(0, $fileBytes - $after[0]))
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}|null  [file bytes, free bytes]
     */
    private function measure(): ?array
    {
        try {
            $pageSize = (int) DB::select('PRAGMA page_size')[0]->page_size;
            $pageCount = (int) DB::select('PRAGMA page_count')[0]->page_count;
            $freeCount = (int) DB::select('PRAGMA freelist_count')[0]->freelist_count;
        } catch (\Throwable $e) {
            return null;
        }

        if ($pageSize <= 0) {
            return null;
        }

        return [$pageCount * $pageSize, $freeCount * $pageSize];
    }

    private function format(int $bytes): string
    {
        return $bytes >= 1048576
            ? sprintf('%.1f MB', $bytes / 1048576)
            : sprintf('%.0f KB', $bytes / 1024);
    }
}
