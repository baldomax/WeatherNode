<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure visitor analytics tables exist (e.g. after "Nothing to migrate" but tables missing).
     */
    public function up(): void
    {
        if (! Schema::hasTable('visitor_logs')) {
            Schema::create('visitor_logs', function (Blueprint $table): void {
                $table->id();
                $table->timestamp('occurred_at')->index();
                $table->string('path', 255)->index();
                $table->string('method', 8);
                $table->unsignedSmallInteger('status_code')->index();
                $table->unsignedInteger('response_ms')->nullable();
                $table->string('referrer_host', 255)->nullable()->index();
                $table->string('search_engine', 50)->nullable()->index();
                $table->string('search_terms', 255)->nullable();
                $table->string('country_code', 2)->nullable()->index();
                $table->string('device_type', 20)->nullable()->index();
                $table->string('browser_family', 30)->nullable()->index();
                $table->string('os_family', 30)->nullable()->index();
                $table->boolean('is_bot')->default(false)->index();
                $table->string('ip_hash', 64)->index();
                $table->text('ip_encrypted');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visitor_daily_stats')) {
            Schema::create('visitor_daily_stats', function (Blueprint $table): void {
                $table->id();
                $table->date('date')->unique();
                $table->unsignedInteger('pageviews')->default(0);
                $table->unsignedInteger('uniques')->default(0);
                $table->unsignedBigInteger('total_response_ms')->default(0);
                $table->unsignedInteger('avg_response_ms')->nullable();
                $table->json('status_codes')->nullable();
                $table->json('top_pages')->nullable();
                $table->json('referrers')->nullable();
                $table->json('countries')->nullable();
                $table->json('devices')->nullable();
                $table->json('browsers')->nullable();
                $table->json('oses')->nullable();
                $table->json('search_engines')->nullable();
                $table->json('search_terms')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Leave tables in place; original migrations own drop.
    }
};
