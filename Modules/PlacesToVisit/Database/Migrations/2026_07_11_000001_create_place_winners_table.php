<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_winners', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10)->index();          // e.g. 2026-W28
            $table->unsignedBigInteger('zone_id')->nullable()->index(); // null = overall
            $table->unsignedBigInteger('place_id')->index();
            $table->unsignedInteger('votes_count')->default(0);
            $table->decimal('avg_rating', 3, 1)->nullable();
            $table->timestamps();

            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_winners');
    }
};
