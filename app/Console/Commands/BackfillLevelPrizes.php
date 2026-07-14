<?php

namespace App\Console\Commands;

use App\Models\Level;
use App\Models\User;
use App\Services\XpService;
use Illuminate\Console\Command;

class BackfillLevelPrizes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'xp:backfill-prizes {--user= : Only backfill a single user id}';

    /**
     * The console command description.
     */
    protected $description = 'Create any missing UserLevelPrize instances for levels users have already unlocked';

    /**
     * Execute the console command.
     *
     * Prize instances are normally created at level-up. This repairs historical
     * users who reached a level before that path was reliable, so the level API
     * no longer has to create them on a GET request.
     */
    public function handle(): int
    {
        // Levels keyed by level_number, ascending — reused per user.
        $levels = Level::active()->orderBy('level_number')->get();

        if ($levels->isEmpty()) {
            $this->warn('No active levels configured — nothing to backfill.');
            return self::SUCCESS;
        }

        $query = User::where('level', '>', 0);
        if ($this->option('user')) {
            $query->where('id', (int) $this->option('user'));
        }

        $totalCreated = 0;
        $usersTouched = 0;

        $query->chunkById(200, function ($users) use ($levels, &$totalCreated, &$usersTouched) {
            foreach ($users as $user) {
                $createdForUser = 0;

                foreach ($levels as $level) {
                    if ($level->level_number > $user->level) {
                        break; // levels are ascending; nothing further is unlocked
                    }
                    $createdForUser += XpService::unlockPrizesForLevel($user, $level);
                }

                if ($createdForUser > 0) {
                    $usersTouched++;
                    $totalCreated += $createdForUser;
                }
            }
        });

        $this->info("Backfill complete: {$totalCreated} prize instance(s) created across {$usersTouched} user(s).");

        return self::SUCCESS;
    }
}
