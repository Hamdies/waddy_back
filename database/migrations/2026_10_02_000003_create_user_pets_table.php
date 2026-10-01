<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's pets (PET-06).
 *
 * Onboarding asks species, name, sex, age band and diet. Birthday, breed and
 * weight come later from the pet profile. `sex` is not decoration: Arabic copy
 * is gendered ("لونا محتاجة" / "ركس محتاج"), so every personalised string
 * needs it (PET-07).
 *
 * `birth_date_is_estimate` marks a date worked out from "about 2 years old"
 * rather than a real birthday, so a birthday push never fires on a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 32);
            $table->enum('species', ['cat', 'dog', 'bird', 'fish', 'small']);
            $table->enum('sex', ['male', 'female', 'unknown'])->default('unknown');
            $table->string('photo')->nullable();
            $table->enum('age_band', ['baby', 'adult', 'senior'])->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_is_estimate')->default(false);
            $table->enum('diet', ['dry', 'wet', 'both', 'picky'])->nullable();
            $table->string('breed', 64)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
            $table->index('birth_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pets');
    }
};
