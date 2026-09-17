<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Who was in the machine — the losing balls, which nothing recorded before.
 *
 * PrizeDrawService::drawFor() has always computed the eligible pool and then
 * thrown away everyone it did not pick; place_prizes holds winners only. That
 * is fine for awarding vouchers and useless for showing a draw: a claw machine
 * with only winners in it is a list.
 *
 * This table is written inside the same transaction as the prizes, so entrants
 * and winners commit together or not at all. See CLAW-Z1 in
 * waddi_user/docs/spots_claw_draw_plan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_draw_entrants', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10)->index();              // e.g. 2026-W32
            $table->unsignedBigInteger('place_winner_id')->index();
            $table->unsignedBigInteger('place_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // Their vote count that period — shown on the winner row. Snapshot
            // rather than derived, because votes can be flagged or removed
            // after the draw and the replay must show the round as it was.
            $table->unsignedInteger('votes')->default(0);

            // 0 = never pulled, 1..N = the order the claw took them. The
            // client animates this order verbatim; it is the server's
            // decision, not a ranking.
            $table->unsignedTinyInteger('rank')->default(0);

            // The true pool size before sampling, denormalised onto every row
            // of the draw. A popular venue can have thousands of voters and we
            // only store a capped sample, so the "+N more in the machine" copy
            // needs the real number from somewhere.
            $table->unsignedInteger('total_entrants')->nullable();

            $table->timestamps();

            // One row per user per week, mirroring place_prizes. Makes the
            // whole write idempotent under WinnerService's lazy closePeriod().
            $table->unique(['period', 'user_id']);

            // The endpoint's only read: everyone in one period, winners first.
            $table->index(['period', 'rank']);

            $table->foreign('place_winner_id')->references('id')->on('place_winners')->onDelete('cascade');
            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_draw_entrants');
    }
};
