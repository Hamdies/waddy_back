<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = ['ip_address', 'token'];

    /**
     * The token is the guest's actual credential — keep it out of anything
     * that serialises a guest into a response.
     */
    protected $hidden = ['token'];

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public function orders()
    {
        return $this->hasMany(Order::class,'user_id','id');
    }
}
