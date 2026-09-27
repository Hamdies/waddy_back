<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * XP discount prizes are redeemed as personal coupons (X-25).
 *
 * - `user_level_prizes.coupon_id` links a claimed discount prize to the coupon
 *   minted for it, so spending the coupon marks the prize used.
 * - `coupons.module_id` becomes nullable: an XP coupon is not bound to one
 *   module, and a null module now means "any module" in CouponLogic. Admin and
 *   vendor coupons always carry a module, so their behaviour is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_level_prizes', 'coupon_id')) {
            Schema::table('user_level_prizes', function (Blueprint $table) {
                $table->unsignedBigInteger('coupon_id')->nullable()->index();
            });
        }

        // Keep the column's existing type; only drop NOT NULL.
        $column = DB::selectOne("SHOW COLUMNS FROM coupons WHERE Field = 'module_id'");
        if ($column && $column->Null === 'NO') {
            DB::statement("ALTER TABLE coupons MODIFY module_id {$column->Type} NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_level_prizes', 'coupon_id')) {
            Schema::table('user_level_prizes', function (Blueprint $table) {
                $table->dropIndex(['coupon_id']);
                $table->dropColumn('coupon_id');
            });
        }
        // coupons.module_id is left nullable: XP coupons with a null module may
        // exist by now, and re-adding NOT NULL would fail on them.
    }
};
