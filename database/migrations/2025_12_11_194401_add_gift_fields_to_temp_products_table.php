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
        Schema::table('temp_products', function (Blueprint $table) {
            $table->boolean('is_gifted')->default(0);
            $table->string('gift_name')->nullable();
            $table->string('gift_image')->nullable();
            $table->date('gift_expiry_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temp_products', function (Blueprint $table) {
            $table->dropColumn(['is_gifted', 'gift_name', 'gift_image', 'gift_expiry_date']);
        });
    }
};
