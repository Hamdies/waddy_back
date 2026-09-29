<?php

namespace App\Services;

use App\Models\CatalogProduct;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges two catalogue products that turned out to be one (CAT-11, CAT-16).
 *
 * The survivor keeps its content; the other product's listings move to it.
 * Where one store sold both, it keeps ONE listing — the one with stock, else
 * the more recently updated — and the other is unlinked and switched off,
 * never deleted: order_details still point at it and hold their own
 * item_details snapshot, so history is untouched. Carts, favourites and
 * reviews on the dropped listing move to the kept one, resolving per-user
 * clashes (one favourite, one cart line with the quantities added).
 */
class CatalogMergeService
{
    public function __construct(private CatalogService $catalog)
    {
    }

    /**
     * What merging will do, store by store: ['store_id', 'store', 'move' => id]
     * or ['store_id', 'store', 'keep' => id, 'drop' => id].
     */
    public function plan(CatalogProduct $survivor, CatalogProduct $loser): array
    {
        $columns = ['id', 'store_id', 'stock', 'status', 'price', 'updated_at'];
        $kept = DB::table('items')->where('catalog_product_id', $survivor->id)->get($columns)->keyBy('store_id');
        $stores = DB::table('stores')->pluck('name', 'id');

        $steps = [];
        foreach (DB::table('items')->where('catalog_product_id', $loser->id)->orderBy('store_id')->get($columns) as $listing) {
            $step = ['store_id' => $listing->store_id, 'store' => $stores[$listing->store_id] ?? "#{$listing->store_id}"];
            $other = $kept[$listing->store_id] ?? null;

            if (!$other) {
                $steps[] = $step + ['move' => $listing->id];
                continue;
            }

            [$keep, $drop] = $this->keeper($other, $listing);
            $steps[] = $step + ['keep' => $keep->id, 'drop' => $drop->id];
        }

        return $steps;
    }

    /** Reasons the merge cannot run yet; empty = go. */
    public function blockers(CatalogProduct $survivor, CatalogProduct $loser): array
    {
        if ($survivor->id === $loser->id) {
            return [translate('messages.catalog_merge_same_product')];
        }
        if ($survivor->module_id !== $loser->module_id) {
            return [translate('messages.catalog_merge_other_module')];
        }

        $blockers = [];
        if (Schema::hasTable('flash_sale_items')) {
            // A flash sale carries its own price and stock for that listing;
            // switching the listing off would silently end it.
            foreach ($this->plan($survivor, $loser) as $step) {
                if (isset($step['drop']) && DB::table('flash_sale_items')->where('item_id', $step['drop'])->where('status', 1)->exists()) {
                    $blockers[] = translate('messages.catalog_merge_flash_sale', ['id' => $step['drop'], 'store' => $step['store']]);
                }
            }
        }

        return $blockers;
    }

    /** Returns counts for the confirmation toast. */
    public function merge(CatalogProduct $survivor, CatalogProduct $loser): array
    {
        $steps = $this->plan($survivor, $loser);
        $loserFiles = $this->catalog->files($loser->image, $loser->images);
        $summary = ['moved' => 0, 'switched_off' => 0, 'carts' => 0, 'favourites' => 0, 'reviews' => 0];

        DB::transaction(function () use ($survivor, $loser, $steps, &$summary) {
            foreach ($steps as $step) {
                if (isset($step['move'])) {
                    $this->relink($step['move'], $survivor);
                    $summary['moved']++;
                    continue;
                }

                // Free the (store, product) slot first: CAT-11's unique index.
                DB::table('items')->where('id', $step['drop'])->update([
                    'catalog_product_id' => null,
                    'catalog_linked_at' => null,
                    'status' => 0,
                ]);
                $this->relink($step['keep'], $survivor);

                foreach ($this->moveCustomerRows($step['drop'], $step['keep']) as $key => $count) {
                    $summary[$key] += $count;
                }
                $summary['switched_off']++;
            }

            DB::table('translations')
                ->where('translationable_type', CatalogProduct::class)
                ->where('translationable_id', $loser->id)
                ->delete();
            $loser->delete();

            DB::table('catalog_products')->where('id', $survivor->id)->update(['last_propagated_at' => now()]);
        });

        // Only files nothing else names — backups included — actually go.
        $this->catalog->deleteUnusedFiles(array_diff($loserFiles, $this->catalog->files($survivor->image, $survivor->images)));

        return $summary;
    }

    private function relink(int $itemId, CatalogProduct $survivor): void
    {
        DB::table('items')->where('id', $itemId)->update([
            'catalog_product_id' => $survivor->id,
            'catalog_linked_at' => now(),
        ]);
        $this->catalog->applyContent($survivor, $itemId);
    }

    /** The listing a store keeps: in stock first, else the more recently updated. */
    private function keeper(object $a, object $b): array
    {
        $aStocked = (int) $a->stock > 0;
        $bStocked = (int) $b->stock > 0;

        if ($aStocked !== $bStocked) {
            return $aStocked ? [$a, $b] : [$b, $a];
        }

        return strcmp((string) $a->updated_at, (string) $b->updated_at) >= 0 ? [$a, $b] : [$b, $a];
    }

    /** Carts, favourites and reviews follow the kept listing (CAT-16). */
    private function moveCustomerRows(int $from, int $to): array
    {
        $counts = ['carts' => 0, 'favourites' => 0, 'reviews' => 0];
        $keep = DB::table('items')->where('id', $to)->first(['id', 'price', 'stock', 'maximum_cart_quantity']);

        if (Schema::hasTable('wishlists')) {
            foreach (DB::table('wishlists')->where('item_id', $from)->get(['id', 'user_id']) as $row) {
                $already = DB::table('wishlists')->where('user_id', $row->user_id)->where('item_id', $to)->exists();
                $already
                    ? DB::table('wishlists')->where('id', $row->id)->delete()
                    : DB::table('wishlists')->where('id', $row->id)->update(['item_id' => $to]);
                $counts['favourites']++;
            }
        }

        if (Schema::hasTable('carts')) {
            $sameLine = fn ($a, $b) => (string) ($a->variation ?? '[]') === (string) ($b->variation ?? '[]')
                && (string) ($a->add_on_ids ?? '[]') === (string) ($b->add_on_ids ?? '[]')
                && (string) ($a->add_on_qtys ?? '[]') === (string) ($b->add_on_qtys ?? '[]');

            $rows = DB::table('carts')->where('item_id', $from)->where('item_type', Item::class)->get();
            foreach ($rows as $row) {
                $match = DB::table('carts')
                    ->where('user_id', $row->user_id)
                    ->where('is_guest', $row->is_guest)
                    ->where('item_id', $to)
                    ->where('item_type', Item::class)
                    ->get()
                    ->first(fn ($candidate) => $sameLine($candidate, $row));

                if ($match) {
                    DB::table('carts')->where('id', $match->id)->update([
                        'quantity' => $this->cappedQuantity($match->quantity + $row->quantity, $match->quantity, $keep),
                        'updated_at' => now(),
                    ]);
                    DB::table('carts')->where('id', $row->id)->delete();
                } else {
                    // Different variation/add-ons stay a separate line.
                    $update = ['item_id' => $to, 'updated_at' => now()];
                    if (in_array((string) ($row->variation ?? '[]'), ['', '[]', 'null'], true)) {
                        $update['price'] = $keep->price;
                    }
                    DB::table('carts')->where('id', $row->id)->update($update);
                }
                $counts['carts']++;
            }
        }

        if (Schema::hasTable('reviews')) {
            // Both of a user's reviews are real; both move.
            $counts['reviews'] = DB::table('reviews')->where('item_id', $from)->update(['item_id' => $to]);
            if ($counts['reviews'] > 0) {
                $this->recomputeRating($to);
                $this->recomputeRating($from);
            }
        }

        return $counts;
    }

    private function cappedQuantity(int $wanted, int $current, object $keep): int
    {
        $caps = array_filter([(int) $keep->stock, (int) ($keep->maximum_cart_quantity ?? 0)], fn ($cap) => $cap > 0);
        $cap = $caps ? min($caps) : $wanted;

        return max($current, min($wanted, $cap));
    }

    /** Same shape review submission maintains: rating = {1..5 counts}, avg_rating, rating_count. */
    private function recomputeRating(int $itemId): void
    {
        $counts = DB::table('reviews')->where('item_id', $itemId)
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($counts as $rating => $total) {
            if (isset($distribution[(int) $rating])) {
                $distribution[(int) $rating] = (int) $total;
            }
        }
        $total = array_sum($distribution);
        $sum = array_sum(array_map(fn ($rating, $n) => $rating * $n, array_keys($distribution), $distribution));

        DB::table('items')->where('id', $itemId)->update([
            'rating' => json_encode($distribution),
            'avg_rating' => $total ? round($sum / $total, 2) : 0,
            'rating_count' => $total,
        ]);
    }
}
