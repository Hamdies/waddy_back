<?php

namespace Modules\PlacesToVisit\Console;

use Illuminate\Console\Command;
use Modules\PlacesToVisit\Services\WinnerService;

class CloseWeekCommand extends Command
{
    protected $signature = 'placestovisit:close-week {--period= : ISO week to close (e.g. 2026-W28), defaults to last week}';

    protected $description = 'Close the finished voting week and crown the weekly winners (overall + per zone)';

    public function handle(WinnerService $winnerService): int
    {
        $winners = $winnerService->closePeriod($this->option('period'));

        if ($winners->isEmpty()) {
            $this->info('Nothing to close — week already closed or no votes.');
            return self::SUCCESS;
        }

        foreach ($winners as $winner) {
            $scope = $winner->zone_id ? "zone {$winner->zone_id}" : 'overall';
            $this->info("{$winner->period} [{$scope}]: place #{$winner->place_id} with {$winner->votes_count} votes");
        }

        return self::SUCCESS;
    }
}
