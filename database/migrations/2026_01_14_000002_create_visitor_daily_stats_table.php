<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_daily_stats');
    }
};
