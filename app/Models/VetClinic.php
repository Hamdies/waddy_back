<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A vet clinic in the Pets module (PET-04). Info only: call, WhatsApp,
 * directions; no orders. Its own table and admin, apart from Spots.
 *
 * @property int $id
 * @property string $name
 * @property string|null $name_ar
 * @property array|null $opening_hours  monday…sunday => {open, close, closed}
 * @property array|null $species         keys of SPECIES
 * @property array|null $services        keys of SERVICES
 * @property array|null $service_prices  service key => starting price (EGP)
 * @property array|null $vets            [{name, role, years}]
 */
class VetClinic extends Model
{
    public const SPECIES = ['cat', 'dog', 'bird', 'fish', 'small'];

    public const SERVICES = [
        'emergency_24h', 'home_visit', 'vaccination', 'surgery', 'dental',
        'xray', 'lab', 'grooming', 'boarding', 'pharmacy',
    ];

    public const MAX_VETS = 4;

    public const DAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    /** Opening hours are entered in Cairo time; the app server runs on UTC. */
    public const TIMEZONE = 'Africa/Cairo';

    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'opening_hours' => 'array',
        'species' => 'array',
        'services' => 'array',
        'service_prices' => 'array',
        'vets' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The name in the app's language, falling back to English. */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return ($locale === 'ar' && $this->name_ar) ? $this->name_ar : $this->name;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        return ($locale === 'ar' && $this->description_ar) ? $this->description_ar : $this->description;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? asset('storage/' . $this->cover) : null;
    }

    /** "1" from the admin checkbox, true from seeders: both mean closed. */
    public static function isClosedFlag(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    /**
     * Open right now, in Cairo time. Null when the clinic has no hours on
     * file. An overnight range (20:00–02:00) carries into the next morning.
     */
    public function isOpenNow(): ?bool
    {
        $hours = $this->opening_hours;
        if (!$hours) {
            return null;
        }
        $now = now(self::TIMEZONE);
        $time = $now->format('H:i');

        $yesterday = $hours[strtolower($now->copy()->subDay()->format('l'))] ?? null;
        if ($yesterday && !self::isClosedFlag($yesterday['closed'] ?? false)) {
            $yo = $yesterday['open'] ?? null;
            $yc = $yesterday['close'] ?? null;
            if ($yo && $yc && $yc < $yo && $time <= $yc) {
                return true;
            }
        }

        $today = $hours[strtolower($now->format('l'))] ?? null;
        if (!$today || self::isClosedFlag($today['closed'] ?? false)) {
            return false;
        }
        $open = $today['open'] ?? null;
        $close = $today['close'] ?? null;
        if (!$open || !$close) {
            return null;
        }
        if ($close < $open) {
            return $time >= $open;
        }
        return $time >= $open && $time <= $close;
    }

    /** Today's `{open, close, closed}`, Cairo time. */
    public function todayHours(): ?array
    {
        return $this->opening_hours[strtolower(now(self::TIMEZONE)->format('l'))] ?? null;
    }
}
