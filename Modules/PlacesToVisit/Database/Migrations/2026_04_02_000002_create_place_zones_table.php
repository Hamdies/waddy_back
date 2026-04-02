<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Internal name (EN)
            $table->string('name_ar')->nullable();     // Internal name (AR)
            $table->string('display_name');   // Shown to users (EN)
            $table->string('display_name_ar')->nullable(); // Shown to users (AR)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_zones');
    }
};
