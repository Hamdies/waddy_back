<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $casts = [
        'user_id' => 'integer',
        'zone_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['voice_instruction_full_url'];

    public function getVoiceInstructionFullUrlAttribute()
    {
        return $this->voice_instruction
            ? asset('storage/' . $this->voice_instruction)
            : null;
    }
}
