<?php

namespace App\Console\Commands;

use App\CentralLogics\ProductLogic;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the orders placed by tests/load/orders.js (guest orders whose contact email ends in
 * @loadtest.invalid), puts their stock back, takes them off the stores' order totals and deletes every
 * row that points at them. Real orders are never touched.
 */
class LoadTestCleanup extends Command
{
    private const MARKER = '@loadtest.invalid';

    protected $signature = 'loadtest:cleanup {--dry-run : Only count what would be removed}';

    protected $description = 'Delete orders created by the k6 order load test and restore their stock';

    public function handle(): int
    {
        $ids = Order::where('is_guest', 1)->where('delivery_address', 'like', '%' . self::MARKER . '%')->pluck('id');
        if ($this->option('dry-run') || $ids->isEmpty()) {
            $this->info("Load-test orders found: {$ids->count()}" . ($this->option('dry-run') ? ' (dry run, nothing removed)' : ''));
            return self::SUCCESS;
        }

        // Every table with an order_id column (details, payments, status logs, notifications, ...).
        $tables = collect(DB::select(
            'SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'order_id']
        ))->pluck('t')->reject(fn ($t) => $t === 'orders')->values();

        foreach ($ids->chunk(200) as $chunk) {
            DB::transaction(function () use ($chunk, $tables) {
                foreach (Order::with(['details', 'module', 'store'])->whereIn('id', $chunk)->get() as $order) {
                    $tracksStock = (bool) (config('module.' . $order->module?->module_type)['stock'] ?? false);
                    if ($order->order_status !== 'canceled' && $tracksStock) {
                        foreach ($order->details as $detail) {
                            $item = $detail->campaign ?: $detail->item;
                            if ($item) {
                                $variant = json_decode($detail->variation, true) ?: [];
                                ProductLogic::update_stock($item, -$detail->quantity, count($variant) ? $variant[0]['type'] : null)->save();
                            }
                        }
                    }
                    if ($order->store && $order->store->total_order > 0) {
                        $order->store->decrement('total_order');
                    }
                }
                foreach ($tables as $table) {
                    DB::table($table)->whereIn('order_id', $chunk)->delete();
                }
                Order::whereIn('id', $chunk)->delete();
            });
        }

        $this->info("Removed {$ids->count()} load-test orders (stock restored) from " . ($tables->count() + 1) . ' tables.');
        return self::SUCCESS;
    }
}
