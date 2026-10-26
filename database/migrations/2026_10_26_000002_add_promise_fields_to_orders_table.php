<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * XS-6: the delivery time the customer was first promised (estimated_delivery_at
 * moves with every status change), and the apology credit if it was missed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('promised_delivery_at')->nullable();
            $table->decimal('late_apology_amount', 24, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['promised_delivery_at', 'late_apology_amount']);
        });
    }
};
