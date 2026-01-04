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
            // Award 50 XP for signup - this will trigger level up to Level 1
            XpService::addXp(
                $user,
                'signup_bonus',
                50,
                'signup',
                $user->id,
                'Welcome bonus for joining Waddy'
            );

            Log::info("Signup XP awarded to user {$user->id}");
        } catch (\Exception $e) {
            Log::error("Failed to award signup XP: " . $e->getMessage());
        }
    }
}
