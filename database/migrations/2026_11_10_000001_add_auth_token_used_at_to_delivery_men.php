<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-3: rider sessions expire after 30 days unused. `auth_token` itself now
 * holds a sha256 of the token (RiderToken); plain tokens left from before are
 * hashed on their next request, so nobody is logged out by this deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->timestamp('auth_token_used_at')->nullable()->after('auth_token');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->dropColumn('auth_token_used_at');
        });
    }
};
