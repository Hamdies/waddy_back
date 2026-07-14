<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prize instances were historically created from two racing code paths
     * (level-up handler and the levels API backfill), which could mint
     * duplicate rows per (user_id, level_prize_id). Remove duplicates —
     * keeping the most-advanced row so no already-consumed value is
     * re-granted — then add a unique constraint so it cannot recur.
     */
    public function up(): void
    {
        // Keep priority: used > claimed > unlocked > expired, then oldest id.
        $duplicates = DB::select("
            SELECT ulp.id FROM user_level_prizes ulp
            INNER JOIN user_level_prizes keeper
                ON keeper.user_id = ulp.user_id
                AND keeper.level_prize_id = ulp.level_prize_id
                AND keeper.id != ulp.id
                AND (
                    FIELD(keeper.status, 'used', 'claimed', 'unlocked', 'expired')
                        < FIELD(ulp.status, 'used', 'claimed', 'unlocked', 'expired')
                    OR (
                        FIELD(keeper.status, 'used', 'claimed', 'unlocked', 'expired')
                            = FIELD(ulp.status, 'used', 'claimed', 'unlocked', 'expired')
                        AND keeper.id < ulp.id
                    )
                )
        ");

        $ids = array_column($duplicates, 'id');

        if (!empty($ids)) {
            Log::warning('Removing duplicate user_level_prizes rows', ['ids' => $ids]);
            DB::table('user_level_prizes')->whereIn('id', $ids)->delete();
        }

        Schema::table('user_level_prizes', function (Blueprint $table) {
            $table->unique(['user_id', 'level_prize_id'], 'unique_user_level_prize');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_level_prizes', function (Blueprint $table) {
            $table->dropUnique('unique_user_level_prize');
        });
    }
};
