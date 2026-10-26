<?php

namespace App\Console\Commands;

use App\Services\StoreDailySummaryService;
use Illuminate\Console\Command;

/**
 * SM-7. Scheduled at 23:30 Cairo; --dry-run prints instead of sending.
 */
class SendStoreDailySummaries extends Command
{
    protected $signature = 'stores:daily-summary {--store= : Only this store id} {--dry-run}';

    protected $description = "Push each store today's orders, earnings, cancellations and prep time";

    public function handle(): int
    {
        $count = StoreDailySummaryService::sendAll(
            $this->option('store') ? (int) $this->option('store') : null,
            (bool) $this->option('dry-run'),
            fn ($store, $lang, $phones, $text) => $this->line("store {$store->id} [$lang, $phones phones]: $text"),
        );
        $this->info(($this->option('dry-run') ? 'Would send to ' : 'Sent to ') . "$count stores");
        return self::SUCCESS;
    }
}
