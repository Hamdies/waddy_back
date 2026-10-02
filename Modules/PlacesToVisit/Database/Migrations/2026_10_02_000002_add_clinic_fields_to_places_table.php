<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a vet clinic treats and offers (PET-19).
 *
 * Fixed keys rather than Spots' free tags: the app translates them, gives
 * them icons and filters on them ("24/7", "Treats Luna"), and place_tags are
 * listed in Spots, so clinic tags there would leak into it. Null on every
 * Spots place; only "Vet clinics" places fill them in.
 *
 *   clinic_species  ["cat","dog","bird","fish","small"]
 *   clinic_services ["emergency_24h","home_visit","vaccination",...]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->json('clinic_species')->nullable()->after('opening_hours');
            $table->json('clinic_services')->nullable()->after('clinic_species');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['clinic_species', 'clinic_services']);
        });
    }
};
