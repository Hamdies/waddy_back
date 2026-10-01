<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Promotions on the grocery stores GroceryStoreTypesSeeder added, so every
 * offer state on the grocery home is represented:
 *
 *   free delivery        → mint collar, "Free delivery" chip
 *   store-wide % off     → coral collar, "Offers" chip
 *   discounted items     → "Offers" chip (the collar only shows store-wide)
 *   free delivery + %    → both chips; the collar shows the % (it wins)
 *   nothing              → plain row / delivery time under the logo
 *
 * These are real businesses: every promotion here is one Waddy is offering on
 * their listing, and customers will be charged accordingly. Change the lists
 * below to match what is actually agreed with each shop.
 *
 * Buy-one-get-one is NOT here: the backend has no BOGO pricing, so a BOGO tag
 * would promise a free item the order would still charge for.
 *
 * Re-runnable: every store named below is reset to its listed state.
 *   php artisan db:seed --class=GroceryPromosSeeder --force
 */
class GroceryPromosSeeder extends Seeder
{
    // Stores are keyed by the vendor-email slug GroceryStoreTypesSeeder gave
    // them, NOT by name: admins rename stores after seeding (Kimo Market is
    // now a bakery, Degla Meat is الدهان), and the owner email survives that.
    private const FREE_DELIVERY = ['kimo-market-degla', 'natural-garden-degla', 'sphinx-roastery-degla', 'dina-farms-degla'];

    /** store => [percent, min purchase, max discount] */
    private const STORE_DISCOUNT = [
        'adam-supermarket-degla' => [10, 150, 50],
        'la-poire-degla' => [15, 200, 75],
        'el-market-degla' => [20, 250, 100],
        'dina-farms-degla' => [10, 100, 40],
    ];

    /** store => [item name => percent] */
    private const ITEM_DISCOUNT = [
        'degla-meat' => ['Kofta Mix 1kg' => 10, 'Minced Beef 500g' => 15],
        'el-bahrain-fish-maadi' => ['Jumbo Shrimp 1kg' => 15],
        'abou-rayan-roastery-maadi' => ['Mixed Nuts 500g' => 10, 'Dates Stuffed with Almonds 250g' => 20],
    ];

    /** Explicitly promotion-free, so a re-run clears anything left over. */
    private const NOTHING = ['andria-butchery-degla', 'el-baraka-vegetables-degla', 'capricci-degla'];

    public function run(): void
    {
        $moduleId = Module::where('module_type', 'grocery')->whereNull('variant')->value('id');
        $slugs = array_unique(array_merge(
            self::FREE_DELIVERY,
            array_keys(self::STORE_DISCOUNT),
            array_keys(self::ITEM_DISCOUNT),
            self::NOTHING,
        ));

        DB::transaction(function () use ($moduleId, $slugs) {
            foreach ($slugs as $slug) {
                $store = Store::withoutGlobalScope('translate')
                    ->where('module_id', $moduleId)
                    ->whereHas('vendor', fn ($q) => $q->where('email', "{$slug}@waddyapp.com"))
                    ->first();

                if (!$store) {
                    $this->command->warn("No store owned by {$slug}@waddyapp.com, skipped.");

                    continue;
                }
                $label = $store->getRawOriginal('name');

                // Reset, then apply: the lists above are the whole truth.
                $free = in_array($slug, self::FREE_DELIVERY, true);
                $store->forceFill(['free_delivery' => $free ? 1 : 0])->save();
                Discount::where('store_id', $store->id)->delete();
                Item::withoutGlobalScopes()->where('store_id', $store->id)->update(['discount' => 0]);

                $applied = [];
                if ($free) {
                    $applied[] = 'free delivery';
                }

                if (isset(self::STORE_DISCOUNT[$slug])) {
                    [$percent, $min, $max] = self::STORE_DISCOUNT[$slug];
                    Discount::create([
                        'store_id' => $store->id,
                        'start_date' => now()->subDay()->toDateString(),
                        'end_date' => now()->addMonths(3)->toDateString(),
                        'start_time' => '00:00:00',
                        'end_time' => '23:59:00',
                        'min_purchase' => $min,
                        'max_discount' => $max,
                        'discount' => $percent,
                        'discount_type' => 'percent',
                    ]);
                    $applied[] = "{$percent}% off (min {$min}, max {$max} LE)";
                }

                foreach (self::ITEM_DISCOUNT[$slug] ?? [] as $itemName => $percent) {
                    $hit = Item::withoutGlobalScopes()
                        ->where('store_id', $store->id)
                        ->where('name', $itemName)
                        ->update(['discount' => $percent, 'discount_type' => 'percent']);
                    $applied[] = $hit ? "{$itemName} −{$percent}%" : "({$itemName} not found)";
                }

                $this->command->line("#{$store->id} {$label}: " . ($applied ? implode(', ', $applied) : 'no promotion'));
            }
        });
    }
}
