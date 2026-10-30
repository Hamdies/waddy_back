<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OD-2: a complaint names the items that were wrong or missing, and an
 * approved refund records who paid for it. `items` is
 * [{detail_id, name, quantity, amount}]; NULL on the old whole-order
 * requests. `store_amount` comes out of the restaurant's earnings and
 * `admin_amount` is Waddy's commission given back; together they make
 * `refund_amount`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->json('items')->nullable()->after('refund_amount');
            $table->decimal('store_amount', 24, 3)->nullable()->after('items');
            $table->decimal('admin_amount', 24, 3)->nullable()->after('store_amount');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['items', 'store_amount', 'admin_amount']);
        });
    }
};
