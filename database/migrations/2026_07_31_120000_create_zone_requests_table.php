<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demand capture for areas we don't deliver to yet.
 *
 * One row per requester (see the unique index below) recording WHERE they
 * wanted delivery, WHAT they were trying to do when they hit the wall, and
 * WHICH store they wanted. That last pair is the whole point: it turns
 * "somewhere in Nasr City" into "47 people wanted this specific restaurant",
 * which is what actually drives the launch order and the merchant sign-up list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone_requests', function (Blueprint $table) {
            $table->id();

            // Identity. Mirrors the `carts` convention exactly: one column holds
            // EITHER a users.id or a guests.id, disambiguated by `is_guest`.
            //
            // Deliberately NOT two nullable columns (user_id + guest_id) with a
            // composite unique index: MySQL treats NULL as never equal to NULL
            // for unique constraints, so with one column always null EVERY row
            // would count as distinct and the index would silently enforce
            // nothing. Both columns here are non-nullable, so the index binds.
            //
            // No FK on user_id — it points at two different tables depending on
            // is_guest, so referential integrity isn't available. Accepted (it's
            // what `carts` already does); consequence is no cascade delete, so
            // rows outlive deleted accounts. Harmless for aggregate demand data.
            $table->foreignId('user_id');
            $table->boolean('is_guest')->default(0);

            // Where they actually are — the real out-of-zone position.
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address')->nullable();

            // Resolved server-side rather than trusting the client's guess.
            $table->foreignId('nearest_zone_id')->nullable();

            // home_banner | add_to_cart | checkout | cart_proceed | sheet
            $table->string('source')->default('sheet');

            // Set when source is add_to_cart: the store they were trying to
            // order from. The single most actionable column in the table.
            $table->foreignId('store_id')->nullable();
            $table->foreignId('module_id')->nullable();

            // Notification channel. No `contact` free-text field on purpose:
            // an unvalidated string guarantees unreachable entries at launch
            // time, which turns "we'll tell you" into a broken promise. FCM
            // needs no typing and no PII.
            $table->string('fcm_token')->nullable();
            // Whether we can actually reach them. Lets the admin view show how
            // much of an area's demand is contactable, not just how big it is.
            $table->boolean('has_push')->default(0);
            $table->boolean('notified')->default(0);

            // Audit trail. Spam here is decision corruption, not load: inflated
            // numbers would steer real expansion spend. The throttle stops
            // casual abuse; these columns make an anomalous spike visible
            // instead of silently trusted.
            $table->string('ip_address')->nullable();

            $table->timestamps();

            // Dedupe by IDENTITY, never by rounded coordinates: at Cairo
            // apartment density, rounding lat/lng to ~111m merges genuinely
            // distinct households into one row and under-reports the very
            // signal this table exists to measure.
            $table->unique(['user_id', 'is_guest']);

            // Bounding-box pre-filter for "how many others near here?" — a bare
            // haversine can't use an index and degrades to a full scan.
            $table->index(['latitude', 'longitude']);
            $table->index('nearest_zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_requests');
    }
};
