<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Key-value settings storage for all configuration
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json, encrypted, select, textarea, date
            $table->string('group')->default('general'); // station, display, ecowitt, wunderground, etc.
            $table->text('description')->nullable();
            $table->text('options')->nullable(); // For select types: "value1:Label 1,value2:Label 2"
            $table->timestamps();
            
            $table->index('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
