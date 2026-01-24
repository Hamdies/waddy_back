<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use App\Models\XpSetting;
use App\Models\LevelPrize;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Modules\Rental\Entities\Trips;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'interest',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_phone_verified' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'order_count' => 'integer',
        'wallet_balance' => 'float',
        'loyalty_point' => 'integer',
        'ref_by' => 'integer',
        'hide_phone' => 'boolean',
        'total_xp' => 'integer',
        'level' => 'integer',
        'last_active_at' => 'datetime',
    ];
    protected $appends = ['image_full_url'];
    public function getImageFullUrlAttribute(){
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('profile',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('profile',$value,'public');
    }

    public function getFullNameAttribute(): string
    {
        return $this->f_name . ' ' . $this->l_name;
    }

    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->where('is_guest', 0);
    }
    public function trips()
    {
        return $this->hasMany(Trips::class)->where('is_guest', 0);
    }

    public function addresses(){
        return $this->hasMany(CustomerAddress::class);
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class,'user_id', 'id');
    }

    public function scopeZone($query, $zone_id=null){
        $query->when(is_numeric($zone_id), function ($q) use ($zone_id) {
            return $q->where('zone_id', $zone_id);
        });
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    // ==================== XP SYSTEM RELATIONSHIPS ====================

    /**
     * Get user's XP transactions.
     */
    public function xpTransactions()
    {
        return $this->hasMany(XpTransaction::class);
    }

    /**
     * Get user's challenges.
     */
    public function userChallenges()
    {
        return $this->hasMany(UserChallenge::class);
    }

    /**
     * Get user's level prizes.
     */
    public function levelPrizes()
    {
        return $this->hasMany(UserLevelPrize::class);
    }

    /**
     * Get current level details.
     */
    public function currentLevel()
    {
        return $this->belongsTo(Level::class, 'level', 'level_number');
    }

    /**
     * Get XP progress to next level.
     */
    public function getXpProgressAttribute(): array
    {
        $currentLevel = Level::where('level_number', $this->level)->first();
        $nextLevel = Level::where('level_number', $this->level + 1)->first();

        if (!$nextLevel) {
            return [
                'current_xp' => $this->total_xp,
                'xp_for_current_level' => $currentLevel?->xp_required ?? 0,
                'xp_for_next_level' => null,
                'xp_to_next_level' => 0,
                'progress_percentage' => 100,
                'is_max_level' => true,
            ];
        }

        $xpInCurrentLevel = $this->total_xp - $currentLevel->xp_required;
        $xpNeededForLevel = $nextLevel->xp_required - $currentLevel->xp_required;
        $progressPercentage = $xpNeededForLevel > 0 
            ? round(($xpInCurrentLevel / $xpNeededForLevel) * 100) 
            : 0;

        return [
            'current_xp' => $this->total_xp,
            'xp_for_current_level' => $currentLevel->xp_required,
            'xp_for_next_level' => $nextLevel->xp_required,
            'xp_to_next_level' => $nextLevel->xp_required - $this->total_xp,
            'progress_percentage' => min(100, $progressPercentage),
            'is_max_level' => false,
        ];
    }

    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });
    }
    protected static function boot()
    {
        parent::boot();
        
        // Auto-assign Level 1 to new users if Level 1 requires 0 XP
        static::created(function ($user) {
            try {
                // Check if XP system is enabled
                if (!XpSetting::isEnabled()) {
                    return;
                }
                
                // Find Level 1 with 0 XP requirement
                $level1 = Level::where('level_number', 1)
                    ->where('xp_required', 0)
                    ->where('status', true)
                    ->first();
                
                if ($level1 && $user->level === 0) {
                    // Assign Level 1
                    $user->level = 1;
                    $user->saveQuietly(); // Avoid triggering events again
                    
                    // Unlock Level 1 prizes
                    $prizes = LevelPrize::where('level_id', $level1->id)
                        ->where('status', true)
                        ->get();
                    
                    foreach ($prizes as $prize) {
                        $validityDays = $prize->validity_days ?? 30;
                        
                        UserLevelPrize::create([
                            'user_id' => $user->id,
                            'level_prize_id' => $prize->id,
                            'status' => 'unlocked',
                            'unlocked_at' => now(),
                            'expires_at' => now()->addDays($validityDays),
                        ]);
                    }
                    
                    \Illuminate\Support\Facades\Log::info("New user {$user->id} auto-assigned to Level 1 with " . count($prizes) . " prizes");
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to auto-assign level: " . $e->getMessage());
            }
        });
        
        static::saved(function ($model) {
            if($model->isDirty('image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }
}
