<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds back to the card a noon payment came from (NP-16). One row per
 * order and source; the customer picks wallet or card while it's
 * awaiting_choice. Also adds the card_refund_enabled switch to the noon
 * admin card (off: every refund goes to the wallet, as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('payment_request_id', 36)->nullable();
            $table->string('noon_order_id', 40);
            $table->decimal('amount', 24, 2);
            $table->string('currency', 10);
            // refund_request | cancellation | admin
            $table->string('source', 20);
            // awaiting_choice | to_wallet | requested | submitted | succeeded | failed
            $table->string('status', 20)->index();
            $table->timestamp('choice_deadline')->nullable();
            // customer | deadline | guest | auto | admin
            $table->string('chosen_by', 20)->nullable();
            $table->string('noon_txn_id', 64)->nullable();
            $table->string('noon_txn_status', 30)->nullable();
            $table->string('rrn', 64)->nullable();
            $table->integer('result_code')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'source']);
            $table->index('noon_order_id');
            $table->index('user_id');
        });

        $row = DB::table('addon_settings')->where('key_name', 'noon')->where('settings_type', 'payment_config')->first();
        if ($row) {
            $update = [];
            foreach (['live_values', 'test_values'] as $column) {
                $values = json_decode($row->$column ?? '{}', true) ?: [];
                if (!array_key_exists('card_refund_enabled', $values)) {
                    $values['card_refund_enabled'] = '0';
                    $update[$column] = json_encode($values);
                }
            }
            if ($update) {
                DB::table('addon_settings')->where('id', $row->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_refunds');
    }
};
