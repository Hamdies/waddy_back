<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sub_status', 50)->nullable()->after('order_status');
            $table->timestamp('sub_status_updated_at')->nullable()->after('sub_status');
            $table->index('sub_status', 'idx_orders_sub_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_sub_status');
            $table->dropColumn(['sub_status', 'sub_status_updated_at']);
        });
    }
};
