<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shopper's choice on produce, set per category.
 *
 * `categories.prep_option` marks a category (main or sub) whose items ask the
 * shopper one question before adding: `ripeness` (fruit: ready to eat, or
 * ripe in 2–3 days) or `use` (vegetables: for salad, or for cooking). NULL for
 * every existing row — nothing asks until an admin sets it.
 *
 * The answer rides on the cart line (`carts.preference`) into the order line
 * (`order_details.preference`) as a code (`ready_to_eat`, `ripe_later`,
 * `salad`, `cooking`), so the picker sees it. It never changes price or stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('prep_option', 20)->nullable()->after('store_id');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->string('preference', 40)->nullable()->after('variation');
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('preference', 40)->nullable()->after('variation');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('prep_option');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('preference');
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('preference');
        });
    }
};
