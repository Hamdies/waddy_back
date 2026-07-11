<?php

namespace Modules\PlacesToVisit\Console;

use Illuminate\Console\Command;
use Modules\PlacesToVisit\Services\PlacePushService;

class FinalHoursPushCommand extends Command
{
    protected $signature = 'placestovisit:final-hours-push {--gap=3 : Max vote gap between #1 and #2 to qualify as a close race}';

    protected $description = 'Push a final-hours nudge to every scope where the weekly race is close';

    public function handle(PlacePushService $pushService): int
    {
        $sent = $pushService->sendFinalHoursPushes((int) $this->option('gap'));
        $this->info("Sent {$sent} close-race push(es).");
        return self::SUCCESS;
    }
}
