<?php

namespace App\Console\Commands;

use App\Models\UserChallenge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendChallengeExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'xp:challenge-expiry-reminders {--hours=3 : Warn when a challenge expires within this many hours}';

    /**
     * The console command description.
     */
    protected $description = 'Push a reminder for in-progress challenges expiring soon';

    /**
     * Execute the console command.
     *
     * Nudges users who have an active (not yet completed) challenge about to
     * expire, so near-misses turn into completions. Deduped by a flag column so
     * a challenge is only reminded once.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $now = now();
        $threshold = now()->addHours($hours);

        $challenges = UserChallenge::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $threshold])
            ->where(function ($q) {
                $q->whereNull('expiry_reminded_at')
                    ->orWhere('expiry_reminded_at', '<', now()->subDay());
            })
            ->with(['user:id,cm_firebase_token', 'challenge:id,title,xp_reward'])
            ->limit(500)
            ->get();

        $sent = 0;

        foreach ($challenges as $uc) {
            $user = $uc->user;
            $challenge = $uc->challenge;

            if (!$user || !$challenge || !$user->cm_firebase_token) {
                // Still stamp so we don't reconsider it every run.
                $uc->forceFill(['expiry_reminded_at' => now()])->saveQuietly();
                continue;
            }

            $data = [
                'title' => translate('Challenge ending soon'),
                'description' => '"' . $challenge->title . '" — '
                    . translate('finish it to earn') . ' ' . $challenge->xp_reward . ' XP',
                'image' => '',
                'type' => 'challenge_expiry',
                'challenge_id' => (string) $challenge->id,
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
                $sent++;
            } catch (\Exception $e) {
                Log::error('Challenge expiry reminder failed: ' . $e->getMessage());
            }

            $uc->forceFill(['expiry_reminded_at' => now()])->saveQuietly();
        }

        $this->info("Challenge expiry reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
