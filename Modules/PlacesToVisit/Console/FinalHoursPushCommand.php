<?php

namespace Modules\PlacesToVisit\Console;

use Illuminate\Console\Command;
use Modules\PlacesToVisit\Services\PlacePushService;

class FinalHoursPushCommand extends Command
{
    protected $signature = 'placestovisit:final-hours-push {--gap= : Max vote gap between #1 and #2 to qualify as a close race (default: race.final_hours_gap)}';

    protected $description = 'Push a final-hours nudge to every scope where the weekly race is close';

    public function handle(PlacePushService $pushService): int
    {
        $sent = $pushService->sendFinalHoursPushes((int) ($this->option('gap') ?? config('placestovisit.race.final_hours_gap', 3)));
        $this->info("Sent {$sent} close-race push(es).");
        return self::SUCCESS;
    }
}
