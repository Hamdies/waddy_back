<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_submissions', function (Blueprint $table) {
            // The mobile app has no map picker yet — location is optional,
            // admin pins the exact location when approving the submission
            $table->decimal('latitude', 10, 8)->nullable()->change();
            $table->decimal('longitude', 11, 8)->nullable()->change();

            $table->string('website')->nullable()->after('phone');
            $table->string('instagram')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('place_submissions', function (Blueprint $table) {
            $table->dropColumn(['website', 'instagram']);
        });
    }
};
