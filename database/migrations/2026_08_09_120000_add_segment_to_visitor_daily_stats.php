<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the daily rollup into traffic segments.
 *
 * The rollup only ever stored bot-inclusive totals, so the admin visitor page
 * could not use it for its default view (bots hidden) and re-aggregated raw
 * visitor_logs on every request instead: 12 GROUP BY scans plus a
 * COUNT(DISTINCT), about 1.2s on a few hundred thousand rows.
 *
 * With a segment per row the rollup stores both cuts and the page reads three
 * queries either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitor_daily_stats')) {
            return;
        }

        if (!Schema::hasColumn('visitor_daily_stats', 'segment')) {
            Schema::table('visitor_daily_stats', function (Blueprint $table): void {
                $table->string('segment', 10)->default('all')->after('date');
            });
        }

        // Existing rows counted everything, so they are the 'all' segment.
        DB::table('visitor_daily_stats')->whereNull('segment')->update(['segment' => 'all']);

        // date was unique on its own, which now has to make room for one row
        // per segment. Wrapped because the index name differs on installs that
        // came through the "ensure tables exist" repair migration.
        try {
            Schema::table('visitor_daily_stats', function (Blueprint $table): void {
                $table->dropUnique('visitor_daily_stats_date_unique');
            });
        } catch (\Throwable $e) {
            // Already dropped, or never created under that name.
        }

        if (!$this->hasIndex('visitor_daily_stats', 'visitor_daily_stats_date_segment_unique')) {
            Schema::table('visitor_daily_stats', function (Blueprint $table): void {
                $table->unique(['date', 'segment']);
            });
        }

        // Every aggregate the page runs against raw logs filters on is_bot plus
        // an occurred_at range. There are single-column indexes on both, but
        // only one of them can be used per query, so the planner falls back to
        // a temp B-tree. This covers the filter for the today-so-far queries
        // that still read raw logs.
        if (Schema::hasTable('visitor_logs') && !$this->hasIndex('visitor_logs', 'visitor_logs_is_bot_occurred_at_index')) {
            Schema::table('visitor_logs', function (Blueprint $table): void {
                $table->index(['is_bot', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitor_daily_stats')) {
            return;
        }

        // Collapse back to one row per day before restoring the unique index.
        DB::table('visitor_daily_stats')->where('segment', '!=', 'all')->delete();

        try {
            Schema::table('visitor_daily_stats', function (Blueprint $table): void {
                $table->dropUnique(['date', 'segment']);
            });
        } catch (\Throwable $e) {
            // Not present.
        }

        Schema::table('visitor_daily_stats', function (Blueprint $table): void {
            $table->dropColumn('segment');
            $table->unique('date');
        });

        if (Schema::hasTable('visitor_logs') && $this->hasIndex('visitor_logs', 'visitor_logs_is_bot_occurred_at_index')) {
            Schema::table('visitor_logs', function (Blueprint $table): void {
                $table->dropIndex(['is_bot', 'occurred_at']);
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return in_array(
                $index,
                array_column(Schema::getIndexes($table), 'name'),
                true
            );
        } catch (\Throwable $e) {
            return false;
        }
    }
};
