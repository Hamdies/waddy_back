<?php

namespace Modules\PlacesToVisit\Console;

use Illuminate\Console\Command;
use Modules\PlacesToVisit\Services\PrizeDrawService;

class ExpirePrizesCommand extends Command
{
    protected $signature = 'placestovisit:expire-prizes';

    protected $description = 'Expire unredeemed voter-prize vouchers and nudge the ones about to die';

    public function handle(PrizeDrawService $prizeDrawService): int
    {
        $expired = $prizeDrawService->expireStale();
        $reminded = $prizeDrawService->sendExpiryReminders();

        $this->info("Expired {$expired} voucher(s), sent {$reminded} expiry reminder(s).");

        return self::SUCCESS;
    }
}
