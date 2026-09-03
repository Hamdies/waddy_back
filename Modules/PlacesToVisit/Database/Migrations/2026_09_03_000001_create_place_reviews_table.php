<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Reviews move out of `place_votes` and into their own table.
 *
 * A vote and a review were one row, which forced three wrong behaviours:
 *
 *  1. Reviewing cast a vote. The user could not say something about a place
 *     without also spending their single weekly vote on it.
 *  2. Un-voting deleted the review, because removing the vote deleted the row.
 *  3. Reviews inherited `period`, so every review vanished from the app each
 *     Monday when the race rolled over.
 *
 * A vote is a move in this week's game; a review is a permanent statement
 * about the place. They have different lifetimes and different cardinality
 * (one vote per user per week overall, one review per user per PLACE, forever),
 * so they get different tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating')->nullable();   // 1-5
            $table->text('review')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();

            // One review per user per place — no period. Re-reviewing updates.
            $table->unique(['place_id', 'user_id']);
            $table->index(['place_id', 'is_flagged']);
        });

        // Carry existing reviews/ratings across. Votes stay where they are.
        // Only rows that actually said something are reviews; a bare vote has
        // neither a rating nor review text and has nothing to migrate.
        //
        // Grouped by (place, user) keeping the most recent row, because the
        // old unique key allowed one row per PERIOD and the new one allows
        // one per place.
        $rows = DB::table('place_votes')
            ->select('place_id', 'user_id', 'rating', 'review', 'image', 'is_flagged', 'created_at', 'updated_at')
            ->where(function ($q) {
                $q->whereNotNull('rating')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('review')->where('review', '!=', '');
                  });
            })
            ->orderBy('created_at')
            ->get();

        $latest = [];
        foreach ($rows as $row) {
            // Later rows overwrite earlier ones for the same (place, user).
            $latest[$row->place_id . ':' . $row->user_id] = [
                'place_id'   => $row->place_id,
                'user_id'    => $row->user_id,
                'rating'     => $row->rating,
                'review'     => $row->review,
                'image'      => $row->image,
                'is_flagged' => $row->is_flagged,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        foreach (array_chunk(array_values($latest), 500) as $chunk) {
            DB::table('place_reviews')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('place_reviews');
    }
};
