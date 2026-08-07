<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // The venue's bookmarkable redemption link is the whole auth story
            // for cashiers — no login, just a secret nobody else can guess.
            $table->char('redeem_token', 32)->nullable()->unique()->after('zone_id');
            // Per-venue override of config('placestovisit.prize.value_cap')
            $table->decimal('prize_value_cap', 8, 2)->nullable()->after('redeem_token');
        });

        // Backfill so every existing venue has a working link on day one
        DB::table('places')->whereNull('redeem_token')->orderBy('id')
            ->select('id')->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('places')->where('id', $row->id)
                        ->update(['redeem_token' => Str::random(32)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropUnique(['redeem_token']);
            $table->dropColumn(['redeem_token', 'prize_value_cap']);
        });
    }
};
