<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('website')->nullable()->after('phone');
            $table->string('instagram')->nullable()->after('website');
            $table->json('opening_hours')->nullable()->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['phone', 'website', 'instagram', 'opening_hours']);
        });
    }
};
