<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardRefund extends Model
{
    public const AWAITING_CHOICE = 'awaiting_choice';
    public const TO_WALLET = 'to_wallet';
    public const REQUESTED = 'requested';
    public const SUBMITTED = 'submitted';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
        'attempts' => 'integer',
        'result_code' => 'integer',
        'choice_deadline' => 'datetime',
        'next_attempt_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Sent to noon as transaction.transactionReference, and read back on sync. */
    public function reference(): string
    {
        return 'wr' . $this->id;
    }
}
