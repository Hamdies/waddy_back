<?php

namespace App\Observers;

use App\Models\User;
use App\Services\XpService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event - award signup XP.
     */
    public function created(User $user): void
    {
        try {
            // Award signup XP bonus using the configurable setting
            XpService::addSignupXp($user);
            Log::info("Signup XP awarded to user {$user->id}");
        } catch (\Exception $e) {
            Log::error("Failed to award signup XP to user {$user->id}: " . $e->getMessage());
        }
    }
}
