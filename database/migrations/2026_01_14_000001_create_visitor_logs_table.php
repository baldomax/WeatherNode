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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
