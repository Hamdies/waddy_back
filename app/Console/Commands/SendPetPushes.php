<?php

namespace App\Console\Commands;

use App\Services\PetPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pet lifecycle pushes (docs/pets_module_plan.md in waddi_user, PET-10/13/16).
 * The guardrails live in PetPushService, so running a kind twice is safe.
 */
class SendPetPushes extends Command
{
    protected $signature = 'pets:pushes {kind : birthdays | life-stage | replenish | reminders}';

    protected $description = 'Send pet lifecycle pushes of one kind';

    public function handle(PetPushService $service): int
    {
        $kind = $this->argument('kind');
        $sent = match ($kind) {
            'birthdays' => $service->birthdays(),
            'life-stage' => $service->lifeStages(),
            'replenish' => $service->replenish(),
            'reminders' => $service->reminders(),
            default => null,
        };
        if ($sent === null) {
            $this->error("Unknown kind: {$kind}");
            return Command::INVALID;
        }

        $this->info("pets:pushes {$kind}: sent {$sent}.");
        Log::info("pets:pushes {$kind}: sent {$sent}.");
        return Command::SUCCESS;
    }
}
