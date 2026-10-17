<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vets can apply from the public site (/vet-clinic/apply). An application
 * is a `vet_clinics` row with status `pending`, hidden from the app until
 * an admin approves it, so approving needs no copy step.
 *
 * Existing clinics were added by admins and are `approved`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vet_clinics', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('is_active');
            $table->string('contact_name', 120)->nullable()->after('status');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
            $table->string('contact_email', 190)->nullable()->after('contact_phone');
            $table->string('contact_role', 80)->nullable()->after('contact_email');
            $table->string('license_number', 80)->nullable()->after('contact_role');
            // Storage path on the public disk, e.g. `vet-clinic/license/x.pdf`.
            $table->string('license_document')->nullable()->after('license_number');
            $table->text('review_note')->nullable()->after('license_document');
            $table->timestamp('reviewed_at')->nullable()->after('review_note');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('vet_clinics', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status', 'contact_name', 'contact_phone', 'contact_email', 'contact_role',
                'license_number', 'license_document', 'review_note', 'reviewed_at',
            ]);
        });
    }
};
