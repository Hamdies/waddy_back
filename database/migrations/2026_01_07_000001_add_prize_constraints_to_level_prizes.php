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
        Schema::table('level_prizes', function (Blueprint $table) {
            // Period-based usage limits
            $table->integer('max_uses_per_period')->nullable()->after('usage_limit');
            $table->enum('period_type', ['once', 'daily', 'weekly', 'monthly'])->nullable()->after('max_uses_per_period');
            
            // Module restrictions (JSON array of module IDs, null = all modules)
            $table->json('applicable_modules')->nullable()->after('period_type');
            
            // Whether prize requires claiming (false for badges - auto-complete)
            $table->boolean('is_claimable')->default(true)->after('applicable_modules');
        });

        // Add usage tracking to user_level_prizes
        Schema::table('user_level_prizes', function (Blueprint $table) {
            $table->integer('uses_count')->default(0)->after('status');
            $table->timestamp('last_used_at')->nullable()->after('uses_count');
            $table->timestamp('period_started_at')->nullable()->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('level_prizes', function (Blueprint $table) {
            $table->dropColumn(['max_uses_per_period', 'period_type', 'applicable_modules', 'is_claimable']);
        });

        Schema::table('user_level_prizes', function (Blueprint $table) {
            $table->dropColumn(['uses_count', 'last_used_at', 'period_started_at']);
        });
    }
};
