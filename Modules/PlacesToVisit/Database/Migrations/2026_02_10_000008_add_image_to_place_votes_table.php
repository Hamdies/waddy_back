<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_votes', function (Blueprint $table) {
            $table->string('image')->nullable()->after('review');
        });
    }

    public function down(): void
    {
        Schema::table('place_votes', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
