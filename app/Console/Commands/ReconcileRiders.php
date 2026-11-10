<?php

namespace App\Console\Commands;

use App\Models\DeliveryMan;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Q-1. Nightly at 04:00 Cairo.
 *
 * 1. `delivery_men.current_orders` is a counter bumped up and down in a dozen
 *    places; one missed decrement and the rider looks full to dispatch forever
 *    (rider 1 reached 11). Reset it to the rider's real live orders.
 * 2. List orders stuck in a live status for over 6 hours for ops. They are
 *    not changed: someone has to decide what happened to each.
 */
class ReconcileRiders extends Command
{
    protected $signature = 'riders:reconcile {--dry-run : Print, change nothing} {--stuck-hours=6}';

    protected $description = 'Fix drifted rider current_orders counters and report stuck orders';

    /** Statuses in which an assigned order occupies its rider. */
    public const LIVE = ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fixed = $this->reconcileCounters($dry);
        $stuck = $this->reportStuck((int) $this->option('stuck-hours'));

        $this->info(($dry ? 'Would fix ' : 'Fixed ') . "$fixed rider counter(s); $stuck stuck order(s)");
        return self::SUCCESS;
    }

    private function reconcileCounters(bool $dry): int
    {
        $live = $this->liveCounts();
        $suspects = DeliveryMan::withoutGlobalScopes()
            ->where('current_orders', '>', 0)
            ->orWhereIn('id', array_keys($live))
            ->pluck('id');

        $fixed = 0;
        foreach ($suspects as $id) {
            // Recount under the rider's lock, so an order assigned while this
            // runs isn't lost.
            DB::transaction(function () use ($id, $dry, &$fixed) {
                $dm = DeliveryMan::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first(['id', 'current_orders']);
                $real = $this->liveCounts($id)[$id] ?? 0;
                if (!$dm || (int) $dm->current_orders === $real) {
                    return;
                }
                $fixed++;
                $this->line("rider {$id}: current_orders {$dm->current_orders} -> {$real}");
                if ($dry) {
                    return;
                }
                DB::table('delivery_men')->where('id', $id)->update(['current_orders' => $real]);
                Log::warning('riders:reconcile corrected current_orders', ['delivery_man_id' => $id, 'was' => (int) $dm->current_orders, 'now' => $real]);
            });
        }
        return $fixed;
    }

    /** @return array<int,int> rider id => live order count */
    private function liveCounts(?int $riderId = null): array
    {
        return Order::withoutGlobalScopes()
            ->whereNotNull('delivery_man_id')
            ->when($riderId, fn ($q) => $q->where('delivery_man_id', $riderId))
            ->whereIn('order_status', self::LIVE)
            ->groupBy('delivery_man_id')
            ->selectRaw('delivery_man_id, count(*) as n')
            ->pluck('n', 'delivery_man_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private function reportStuck(int $hours): int
    {
        $before = now()->subHours($hours);
        // schedule_at, not created_at: a scheduled order is only late once its
        // slot has passed.
        $stuck = Order::withoutGlobalScopes()
            ->whereIn('order_status', self::LIVE)
            ->where(fn ($q) => $q->where('schedule_at', '<', $before)->orWhere(fn ($q) => $q->whereNull('schedule_at')->where('created_at', '<', $before)))
            ->orderBy('id')
            ->get(['id', 'order_status', 'delivery_man_id', 'store_id', 'schedule_at', 'created_at']);

        foreach ($stuck as $o) {
            $this->line("stuck order {$o->id}: {$o->order_status}, rider " . ($o->delivery_man_id ?? '-') . ", store {$o->store_id}, since " . ($o->schedule_at ?? $o->created_at));
        }
        if ($stuck->isNotEmpty()) {
            $context = ['count' => $stuck->count(), 'order_ids' => $stuck->pluck('id')->take(100)->all()];
            Log::warning("riders:reconcile found orders live for over {$hours} h", $context);
            if (config('logging.channels.slack.url')) {
                try {
                    Log::channel('slack')->warning("Waddy: {$stuck->count()} order(s) live for over {$hours} h", $context);
                } catch (\Throwable $e) {
                    Log::warning('riders:reconcile could not post to Slack', ['error' => $e->getMessage()]);
                }
            }
        }
        return $stuck->count();
    }
}
