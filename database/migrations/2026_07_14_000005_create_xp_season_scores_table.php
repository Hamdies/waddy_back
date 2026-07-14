<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-period XP totals so the leaderboard can show a weekly/monthly season
     * (which resets and stays motivating) alongside the all-time board. The
     * period is an AppClock key like "2026-W28" (weekly) or "2026-07" (monthly).
     */
    public function up(): void
    {
        Schema::create('xp_season_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('period_type', 16);   // 'weekly' | 'monthly'
            $table->string('period', 16);         // e.g. '2026-W28' or '2026-07'
            $table->unsignedBigInteger('xp_earned')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // One row per user per period; increment() keys off this.
            $table->unique(['user_id', 'period_type', 'period'], 'unique_user_period');

            // Leaderboard query: top XP within a given period.
            $table->index(['period_type', 'period', 'xp_earned'], 'season_leaderboard_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xp_season_scores');
    }
};
