<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_ramadan_featured')->default(0);
        });

        // Seed the ramadan_mode setting
        DB::table('business_settings')->insertOrIgnore([
            'key' => 'ramadan_mode',
            'value' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('is_ramadan_featured');
        });

        DB::table('business_settings')->where('key', 'ramadan_mode')->delete();
    }
};
