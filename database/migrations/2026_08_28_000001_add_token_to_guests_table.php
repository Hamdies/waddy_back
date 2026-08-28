<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * `guests.id` is a sequential integer that was handed to clients as their
     * whole identity, and it shares a numeric space with `users.id` because
     * both land in `orders.user_id`. This adds an unguessable token so a guest
     * can be authenticated rather than merely asserted.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('id');
        });

        // Backfill existing guests so they keep working once clients migrate.
        DB::table('guests')->whereNull('token')->orderBy('id')->chunkById(500, function ($guests) {
            foreach ($guests as $guest) {
                DB::table('guests')->where('id', $guest->id)->update([
                    'token' => Str::random(64),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};
