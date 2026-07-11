<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40)->index();       // vote_created, share_done, banner_view, ...
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('place_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('period', 10)->index();       // 2026-W28
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_events');
    }
};
