<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MA-4: the store app's Documents & verification screen.
 *
 * One row per upload, kept as history. A store's current document of a type
 * is its newest verified row; a newer pending row is "in review" and the
 * verified one stays valid meanwhile. The store only sends the file: support
 * types the number, issuer and expiry while checking it. `bank` rows are
 * payout account changes (details in `details`), applied to the vendor when
 * approved.
 *
 * MA-3: `stores.push_prefs` — the app's push switches (cancel, rider,
 * reviews, payouts, tips). NULL = all on except tips.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('type', 30); // commercial_register | tax_card | health_licence | national_id | bank
            $table->string('status', 10)->default('pending'); // pending | verified | rejected
            $table->string('file')->nullable();
            $table->string('file_disk', 10)->default('public');
            $table->string('number', 100)->nullable();
            $table->string('issued_by', 150)->nullable();
            $table->string('holder_name', 150)->nullable();
            $table->date('expires_at')->nullable();
            $table->json('details')->nullable();
            $table->string('note', 500)->nullable(); // reason shown to the store when rejected
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'type', 'status'], 'ix_store_documents_store_type');
            $table->index('status', 'ix_store_documents_status');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->json('push_prefs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_documents');
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('push_prefs');
        });
    }
};
