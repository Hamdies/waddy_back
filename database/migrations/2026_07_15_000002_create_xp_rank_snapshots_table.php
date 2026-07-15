<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the last leaderboard rank a user was seen at, per period type
     * (weekly / monthly / alltime). The leaderboard endpoint compares the live
     * rank against this snapshot to produce a real "moved up/down/held" delta —
     * the ▲/▼/HELD column in the design — then refreshes the snapshot. One row
     * per (user, period_type); no historical rows, so it stays tiny.
     */
    public function up(): void
    {
        Schema::create('xp_rank_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('period_type', 16); // 'weekly' | 'monthly' | 'alltime'
            // The period bucket the snapshot belongs to (e.g. 2026-W29, 2026-07,
            // or 'lifetime'). Lets us reset movement to "new" when a fresh season
            // starts instead of comparing across period boundaries.
            $table->string('period', 32);
            $table->unsignedInteger('rank');
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'period_type'], 'xp_rank_snap_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_rank_snapshots');
    }
};
