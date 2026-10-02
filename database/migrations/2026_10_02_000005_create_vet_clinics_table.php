<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vet clinics get their own table, apart from Spots (PET-04, revised 10-02).
 *
 * They started as `places` in a `surface = pets` category, which reused
 * Spots' fields but put clinics in Spots' admin and tied them to its
 * behaviour. This moves them out: same fields, owned by the Pets module.
 *
 * up() copies every place in a pets-surface category, with its EN/AR
 * title and description, photos (paths kept under `places/`), hours and
 * the clinic_* fields, then switches those places and the category OFF.
 * Nothing in `places` is deleted; down() switches them back on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('instagram')->nullable();
            // Storage paths relative to the public disk, e.g. `vet-clinic/x.png`.
            $table->string('logo')->nullable();
            $table->string('cover')->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('species')->nullable();
            $table->json('services')->nullable();
            $table->json('service_prices')->nullable();
            $table->json('vets')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'latitude', 'longitude']);
        });

        if (!Schema::hasTable('place_categories') || !Schema::hasColumn('place_categories', 'surface')) {
            return;
        }
        $categoryIds = DB::table('place_categories')->where('surface', 'pets')->pluck('id');
        if ($categoryIds->isEmpty()) {
            return;
        }
        $hasClinicFields = Schema::hasColumn('places', 'clinic_species');
        $hasPricesVets = Schema::hasColumn('places', 'clinic_service_prices');

        foreach (DB::table('places')->whereIn('category_id', $categoryIds)->get() as $place) {
            $t = DB::table('place_translations')->where('place_id', $place->id)->get()->keyBy('locale');
            DB::table('vet_clinics')->insert([
                'name' => $t['en']->title ?? ($t['ar']->title ?? 'Clinic ' . $place->id),
                'name_ar' => $t['ar']->title ?? null,
                'description' => $t['en']->description ?? null,
                'description_ar' => $t['ar']->description ?? null,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'address' => $place->address,
                'phone' => $place->phone,
                'website' => $place->website,
                'instagram' => $place->instagram,
                'logo' => $place->image ? 'places/' . $place->image : null,
                'cover' => $place->cover_image ? 'places/' . $place->cover_image : null,
                'opening_hours' => $place->opening_hours,
                'species' => $hasClinicFields ? $place->clinic_species : null,
                'services' => $hasClinicFields ? $place->clinic_services : null,
                'service_prices' => $hasPricesVets ? $place->clinic_service_prices : null,
                'vets' => $hasPricesVets ? $place->clinic_vets : null,
                'is_active' => $place->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('places')->whereIn('category_id', $categoryIds)->update(['is_active' => false]);
        DB::table('place_categories')->whereIn('id', $categoryIds)->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_clinics');
        if (Schema::hasColumn('place_categories', 'surface')) {
            $categoryIds = DB::table('place_categories')->where('surface', 'pets')->pluck('id');
            DB::table('place_categories')->whereIn('id', $categoryIds)->update(['is_active' => true]);
            DB::table('places')->whereIn('category_id', $categoryIds)->update(['is_active' => true]);
        }
    }
};
