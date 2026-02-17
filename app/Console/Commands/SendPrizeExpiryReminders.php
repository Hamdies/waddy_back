<?php

namespace App\Console\Commands;

use App\Models\UserLevelPrize;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPrizeExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'xp:prize-expiry-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send FCM notifications for prizes expiring within 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $in24Hours = now()->addHours(24);

        // Find prizes expiring within 24 hours that haven't been used/expired
        $expiringPrizes = UserLevelPrize::whereIn('status', ['unlocked', 'claimed'])
            ->whereBetween('expires_at', [$now, $in24Hours])
            ->with(['user', 'prize'])
            ->get();

        $count = 0;

        foreach ($expiringPrizes as $userPrize) {
            $user = $userPrize->user;
            $prize = $userPrize->prize;

            if (!$user || !$prize || !$user->cm_firebase_token) {
                continue;
            }

            $data = [
                'title' => translate('Prize Expiring Soon!'),
                'description' => ($prize->title ?? 'Your prize') . ' ' . translate('expires tomorrow!'),
                'image' => '',
                'type' => 'prize_expiring',
                'prize_id' => (string) $userPrize->id,
                'order_id' => '',
                'module_id' => '',
                'order_type' => '',
            ];

            try {
                \App\CentralLogics\Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);

                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to send prize expiry reminder for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$count} prize expiry reminders.");
        Log::info("XP prize expiry reminders: sent {$count} notifications.");

        return Command::SUCCESS;
    }
}
