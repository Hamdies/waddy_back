<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which part of the app a place category belongs to.
 *
 * Vet clinics are stored as places (phone, hours, location, cover, reviews all
 * already exist here) but must never appear in Spots: not in its lists, the
 * leaderboard, trending, the weekly winner or its pushes (PET-04/05).
 * `SurfaceScope` hides every non-`spots` category and its places outside the
 * admin panel, so a Spots query that forgets about clinics stays correct.
 *
 * Every existing category is `spots`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_categories', function (Blueprint $table) {
            $table->string('surface', 16)->default('spots')->after('name');
            $table->index('surface');
        });
    }

    public function down(): void
    {
        Schema::table('place_categories', function (Blueprint $table) {
            $table->dropIndex(['surface']);
            $table->dropColumn('surface');
        });
    }
};
