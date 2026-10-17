<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A tracked QR code from Admin › QR code generator. It encodes
 * shortUrl(); /q/{code} counts the scan and redirects to target_url.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type        link | whatsapp
 * @property string $target_url
 * @property array|null $style   the generator's settings
 * @property int $scans_count
 */
class QrCode extends Model
{
    /** Only codes that open a web link can pass through our redirect. */
    public const TYPES = ['link', 'whatsapp'];

    protected $guarded = ['id'];

    protected $casts = [
        'style' => 'array',
        'scans_count' => 'integer',
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function scans(): HasMany
    {
        return $this->hasMany(QrCodeScan::class);
    }

    public function shortUrl(): string
    {
        return route('qr.redirect', $this->code);
    }

    /** Six mixed-case letters and digits: 56 billion codes, short enough to keep the QR sparse. */
    public static function newCode(): string
    {
        do {
            $code = Str::random(6);
        } while (static::where('code', $code)->exists());
        return $code;
    }
}
