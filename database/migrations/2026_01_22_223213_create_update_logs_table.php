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
        Schema::create('update_logs', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50);
            $table->enum('status', ['pending', 'success', 'failed', 'rolled_back'])->default('pending');
            $table->timestamp('deployed_at')->nullable();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rollback_at')->nullable();
            $table->foreignId('rollback_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('error_message')->nullable();
            $table->string('release_dir')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('validation_results')->nullable();
            $table->json('health_check_results')->nullable();
            
            // Note: For MySQL, JSON columns are supported. For older MySQL versions,
            // Laravel will automatically use TEXT with JSON encoding.
            $table->timestamps();

            $table->index('version');
            $table->index('status');
            $table->index('deployed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('update_logs');
    }
};
