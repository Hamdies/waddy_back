<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OF-1: offers a store runs and pays for itself (% off, amount off, free
 * delivery over X). Orders record which offer they used, for results and
 * budgets. A live % offer is mirrored into `discounts` (what every app
 * version already shows); `stores.active_offers` publishes the live offers
 * to apps that know the new kinds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('type', 20); // percent | amount | free_delivery
            $table->decimal('value', 24, 2)->default(0); // % or EGP; 0 for free_delivery
            $table->decimal('min_order', 24, 2)->default(0);
            $table->decimal('max_discount', 24, 2)->nullable(); // percent only
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->default('00:00:00');
            $table->time('end_time')->default('23:59:59');
            $table->decimal('budget', 24, 2)->nullable();
            $table->string('status', 20)->default('active'); // active | paused | ended
            $table->string('ended_reason', 20)->nullable(); // store | budget | replaced
            $table->timestamps();
            $table->index(['store_id', 'status'], 'idx_store_offers_store_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('store_offer_id')->nullable()->index();
            $table->unsignedBigInteger('delivery_offer_id')->nullable()->index();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->json('active_offers')->nullable();
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->unsignedBigInteger('store_offer_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', fn (Blueprint $table) => $table->dropColumn('store_offer_id'));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('active_offers'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_offer_id']);
            $table->dropIndex(['delivery_offer_id']);
            $table->dropColumn(['store_offer_id', 'delivery_offer_id']);
        });
        Schema::dropIfExists('store_offers');
    }
};
