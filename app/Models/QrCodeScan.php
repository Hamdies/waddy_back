<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One scan of a tracked QR code. No IP is stored; see visitor_hash. */
class QrCodeScan extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public const DEVICES = ['android', 'ios', 'desktop', 'other'];

    public static function deviceFrom(string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/android/i', $userAgent) => 'android',
            (bool) preg_match('/iphone|ipad|ipod|ios/i', $userAgent) => 'ios',
            (bool) preg_match('/windows|macintosh|linux|cros/i', $userAgent) => 'desktop',
            default => 'other',
        };
    }
}
