<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreSchedule;
use App\Models\Translation;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Removes the INVENTED test grocery stores an earlier version of
 * GroceryStoreTypesSeeder created (Degla Mini Market, Bean House Roastery, …).
 *
 * Matched only by vendor email ending "@test.waddyapp.com" — that domain was
 * used by those test stores and nothing else. Any store that already has an
 * order is left alone and reported, so no order history loses its store.
 *
 *   php artisan db:seed --class=RemoveTestGroceryStoresSeeder --force
 */
class RemoveTestGroceryStoresSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = Vendor::where('email', 'like', '%@test.waddyapp.com')->get();

        if ($vendors->isEmpty()) {
            $this->command->info('No test stores found. Nothing to remove.');

            return;
        }

        DB::transaction(function () use ($vendors) {
            foreach ($vendors as $vendor) {
                $stores = Store::withoutGlobalScope('translate')->where('vendor_id', $vendor->id)->get();
                $keptAny = false;

                foreach ($stores as $store) {
                    if (DB::table('orders')->where('store_id', $store->id)->exists()) {
                        $this->command->warn("Kept {$store->name}: it has orders.");
                        $keptAny = true;

                        continue;
                    }

                    $itemIds = Item::withoutGlobalScopes()->where('store_id', $store->id)->pluck('id');
                    Translation::where('translationable_type', Item::class)->whereIn('translationable_id', $itemIds)->delete();
                    Item::withoutGlobalScopes()->whereIn('id', $itemIds)->delete();

                    StoreSchedule::where('store_id', $store->id)->delete();
                    Discount::where('store_id', $store->id)->delete();
                    Translation::where('translationable_type', Store::class)->where('translationable_id', $store->id)->delete();
                    $store->cuisines()->detach();
                    $store->delete();

                    $this->command->line("Removed {$store->name} and {$itemIds->count()} items.");
                }

                if (!$keptAny) {
                    $vendor->delete();
                }
            }
        });
    }
}
