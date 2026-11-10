<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\DispatchService;
use Illuminate\Console\Command;

/**
 * Q-5. "Why didn't this order get a rider?" in one command. Read-only.
 */
class DispatchExplain extends Command
{
    protected $signature = 'dispatch:explain {order : Order id} {--json}';

    protected $description = 'Show the dispatch state of an order and why each rider in its zone was or was not eligible';

    public function handle(): int
    {
        $order = Order::withoutGlobalScopes()->with(['store', 'zone'])->find($this->argument('order'));
        if (!$order) {
            $this->error('No such order');
            return self::FAILURE;
        }
        $x = DispatchService::explain($order);
        if ($this->option('json')) {
            $this->line(json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $o = $x['order'];
        $this->info("Order {$o['id']}: {$o['status']}, {$o['type']}, {$o['payment']}, zone {$o['zone_id']}, rider " . ($o['delivery_man_id'] ?? '-'));
        $this->line('Pickup: ' . ($x['pickup'] ? implode(', ', $x['pickup']) : 'NONE (dispatch cannot score this order)'));
        $d = $x['dispatch'];
        $this->line('Dispatch: ' . ($d ? "{$d['mode']} / {$d['status']}, wave {$d['wave']}, next {$d['next_at']}" . ($d['opened_at'] ? ", opened to zone {$d['opened_at']}" : '') : 'NO order_dispatches row (dispatch never started)'));
        $this->line("Location must be under {$x['fresh_minutes']} min old" . ($x['cash_ceiling'] !== null ? ", cash cap {$x['cash_ceiling']}" : ''));
        $this->table(
            ['rider', 'name', 'eligible?', 'rank', 'pickup min', 'location age'],
            array_map(fn ($r) => [
                $r['id'], $r['name'], $r['reason'] ?? 'yes', $r['rank'] ?? '', $r['minutes'] ?? '',
                $r['location_age_s'] === null ? '-' : ($r['location_age_s'] < 120 ? "{$r['location_age_s']} s" : intdiv($r['location_age_s'], 60) . ' min'),
            ], $x['riders'])
        );
        $this->line('Summary: ' . collect($x['reasons'])->map(fn ($n, $k) => "$k $n")->implode(', '));
        return self::SUCCESS;
    }
}
