<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer's pet (PET-06, docs/pets_module_plan.md in waddi_user).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $species cat|dog|bird|fish|small
 * @property string $sex male|female|unknown
 * @property string|null $photo
 * @property string|null $age_band baby|adult|senior, as the owner answered it
 * @property Carbon|null $birth_date
 * @property bool $birth_date_is_estimate
 * @property string|null $diet dry|wet|both|picky
 * @property string|null $breed
 * @property float|null $weight_kg
 * @property bool $is_primary
 * @property bool $notify  the pet's own off switch for lifecycle pushes
 * @property \Illuminate\Support\Carbon|null $last_pushed_at  the weekly cap
 */
class UserPet extends Model
{
    use SoftDeletes;

    public const SPECIES = ['cat', 'dog', 'bird', 'fish', 'small'];
    public const SEXES = ['male', 'female', 'unknown'];
    public const AGE_BANDS = ['baby', 'adult', 'senior'];
    public const DIETS = ['dry', 'wet', 'both', 'picky'];

    /** One household rarely has more; the cap stops a runaway client. */
    public const MAX_PER_USER = 10;

    protected $fillable = [
        'name', 'species', 'sex', 'age_band', 'birth_date', 'birth_date_is_estimate',
        'diet', 'breed', 'weight_kg', 'is_primary', 'notify',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'birth_date' => 'date:Y-m-d',
        'birth_date_is_estimate' => 'boolean',
        'weight_kg' => 'float',
        'is_primary' => 'boolean',
        'notify' => 'boolean',
        'last_pushed_at' => 'datetime',
    ];

    protected $hidden = ['deleted_at', 'last_pushed_at'];

    protected $appends = ['photo_full_url', 'life_stage'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPhotoFullUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }
        return Helpers::get_full_url('pet', $this->photo, Helpers::getDisk());
    }

    /**
     * The age band the pet is in today.
     *
     * With a birth date this moves on its own, so a kitten becomes an adult
     * without the owner editing anything (and a life-stage push can fire on
     * the day it changes). Without one, it is what the owner picked.
     */
    public function getLifeStageAttribute(): ?string
    {
        if (!$this->birth_date) {
            return $this->age_band;
        }
        $years = $this->birth_date->diffInYears(now());
        if ($years < 1) {
            return 'baby';
        }
        return $years >= 7 ? 'senior' : 'adult';
    }
}
