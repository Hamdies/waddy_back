<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pet lifecycle pushes (PET-10, PET-13, PET-16).
 *
 * - `user_pets.notify`: the per-pet off switch from the pet profile.
 * - `user_pets.last_pushed_at`: the weekly cap. An automatic pet push
 *   (food running low, life stage) is skipped when this pet had one in the
 *   last 7 days. The emotional hook stops working the moment it feels like
 *   spam about your pet.
 * - `pet_push_log`: one row per push, unique on (pet, kind, ref), so a job
 *   that runs twice, or a scheduler that catches up, never sends the same
 *   birthday or the same "food running low" twice.
 * - `pet_reminders`: "Remind me every 4 weeks" for one product. The v1 of the
 *   design's Subscribe & save: no recurring order, no charge, a push that
 *   opens the shop on the usual item (D4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_pets', function (Blueprint $table) {
            $table->boolean('notify')->default(true)->after('is_primary');
            $table->timestamp('last_pushed_at')->nullable()->after('notify');
        });

        Schema::create('pet_push_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_pet_id')->constrained('user_pets')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('ref', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_pet_id', 'kind', 'ref']);
        });

        Schema::create('pet_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_pet_id')->nullable()->constrained('user_pets')->nullOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedSmallInteger('interval_days');
            $table->timestamp('next_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'item_id']);
            $table->index(['active', 'next_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_reminders');
        Schema::dropIfExists('pet_push_log');
        Schema::table('user_pets', function (Blueprint $table) {
            $table->dropColumn(['notify', 'last_pushed_at']);
        });
    }
};
