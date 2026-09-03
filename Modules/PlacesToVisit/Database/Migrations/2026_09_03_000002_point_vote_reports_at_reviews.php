<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Reports are about what somebody WROTE, and writing has moved to
 * `place_reviews`. The `vote_id` column keeps its name (a dozen call sites and
 * the public route shape use it) but now references a review, so the foreign
 * key to `place_votes` has to go — otherwise every report insert fails.
 *
 * Existing rows are remapped from vote id to the migrated review id by
 * (place_id, user_id); reports whose vote carried no review text have nothing
 * to point at and are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('place_vote_reports')) {
            return;
        }

        // Remap before dropping the constraint, while both sides still exist.
        $remap = DB::table('place_vote_reports as r')
            ->join('place_votes as v', 'v.id', '=', 'r.vote_id')
            ->join('place_reviews as pr', function ($j) {
                $j->on('pr.place_id', '=', 'v.place_id')
                  ->on('pr.user_id', '=', 'v.user_id');
            })
            ->select('r.id as report_id', 'pr.id as review_id')
            ->get();

        Schema::table('place_vote_reports', function (Blueprint $table) {
            // Laravel's conventional constraint name for this column.
            try {
                $table->dropForeign(['vote_id']);
            } catch (\Throwable $e) {
                // Already absent on some installs — nothing to undo.
            }
        });

        foreach ($remap as $row) {
            DB::table('place_vote_reports')
                ->where('id', $row->report_id)
                ->update(['vote_id' => $row->review_id]);
        }

        // Reports whose target never had review text can no longer resolve.
        $keep = $remap->pluck('report_id');
        DB::table('place_vote_reports')
            ->when($keep->isNotEmpty(), fn($q) => $q->whereNotIn('id', $keep))
            ->delete();
    }

    public function down(): void
    {
        // The vote rows these once pointed at may be gone; restoring the
        // constraint would fail on any surviving row. Left unconstrained.
    }
};
