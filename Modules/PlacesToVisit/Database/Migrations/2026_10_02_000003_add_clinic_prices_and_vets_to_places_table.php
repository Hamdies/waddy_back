<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic page's "Services & prices" and "Meet the vets" (design 03).
 *
 *   clinic_service_prices  {"vaccination": 450, "surgery": 2500}  starting
 *                          price per ticked service, EGP; a service without
 *                          one shows no price
 *   clinic_vets            [{"name":"Dr. Mona Saleh","role":"General
 *                          practice","years":12}]  up to 4, no photos yet
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->json('clinic_service_prices')->nullable()->after('clinic_services');
            $table->json('clinic_vets')->nullable()->after('clinic_service_prices');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['clinic_service_prices', 'clinic_vets']);
        });
    }
};
