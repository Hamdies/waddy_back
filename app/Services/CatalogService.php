<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\CatalogProduct;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Moves content between the catalogue and its listings (docs/catalog_plan.md).
 *
 * Everything here writes through the query builder, not Item models: the
 * Item `saved` hook stamps the *current* upload disk onto any changed image,
 * which would be wrong for a file the catalogue already stored elsewhere, and
 * listings must not pick up slug or timestamp side effects. Storage disks are
 * written explicitly instead.
 *
 * Files are only ever removed through Helpers::deleteProductImageIfUnreferenced,
 * after the rows that stop using them are written — so a photo another store,
 * the catalogue or a rollback backup still names is never touched.
 */
class CatalogService
{
    /** Content a listing takes from its catalogue product. Price, stock, discount, status stay the store's. */
    public const CONTENT_COLUMNS = ['name', 'description', 'image', 'images', 'unit_id', 'category_id', 'category_ids'];

    /** Translation keys that are content. */
    public const CONTENT_TRANSLATION_KEYS = ['name', 'description'];

    /**
     * An admin edited a linked listing. That edit is the catalogue edit
     * (decision 1: the admin is the catalogue team), so the listing's content
     * becomes the product's and goes to every store that sells it. Photos
     * the product stopped showing are removed once nothing uses them.
     *
     * Returns how many listings now carry the content (0 = not linked).
     */
    public function syncFromListing(int $itemId): int
    {
        $listing = DB::table('items')->where('id', $itemId)->first();
        $product = $listing?->catalog_product_id ? CatalogProduct::find($listing->catalog_product_id) : null;
        if (!$product) {
            return 0;
        }

        $disks = $this->storageDisks(Item::class, $itemId);

        return $this->saveContent($product, [
            'name' => $listing->name,
            'description' => $listing->description,
            'image' => $listing->image ?: null,
            'image_storage' => $disks['image'] ?? ($product->image_storage ?: 'public'),
            'images' => $this->decodeImages($listing->images),
            'unit_id' => $listing->unit_id,
            'category_id' => $listing->category_id,
            'category_ids' => $this->decodeJson($listing->category_ids),
        ], $this->contentTranslations(Item::class, $itemId)->map(fn ($t) => (array) $t)->all());
    }

    /**
     * The one write path for catalogue content — the catalogue form and the
     * admin product form both land here. Saves the product and its
     * name/description translations, copies them to every listing, then
     * removes photos the product stopped showing (only if nothing else,
     * including a rollback backup, still names them).
     *
     * $translations: list of ['locale', 'key', 'value']; replaces the old set.
     * Returns how many listings carry the content.
     */
    public function saveContent(CatalogProduct $product, array $attributes, array $translations): int
    {
        $filesBefore = $product->exists ? $this->files($product->image, $product->images) : [];

        if (array_key_exists('name', $attributes) && !array_key_exists('size_value', $attributes)) {
            $attributes['size_value'] = CatalogMatcher::sizeToken(CatalogMatcher::normalize($attributes['name']));
        }

        $count = DB::transaction(function () use ($product, $attributes, $translations) {
            $product->fill($attributes)->save();

            DB::table('translations')
                ->where('translationable_type', CatalogProduct::class)
                ->where('translationable_id', $product->id)
                ->whereIn('key', self::CONTENT_TRANSLATION_KEYS)
                ->delete();
            foreach ($translations as $translation) {
                if (!in_array($translation['key'], self::CONTENT_TRANSLATION_KEYS, true) || trim((string) $translation['value']) === '') {
                    continue;
                }
                DB::table('translations')->insert([
                    'translationable_type' => CatalogProduct::class,
                    'translationable_id' => $product->id,
                    'locale' => $translation['locale'],
                    'key' => $translation['key'],
                    'value' => $translation['value'],
                ]);
            }

            return $this->propagate($product);
        });

        $this->deleteUnusedFiles(array_diff($filesBefore, $this->files($product->image, $product->images)));

        return $count;
    }

    /**
     * A new listing of a catalogue product at one store ("add to stores").
     * Created linked, with no backup: it never had content of its own, so a
     * backfill rollback leaves it alone. Null when the store already sells it.
     */
    public function addListing(CatalogProduct $product, int $storeId, float $price, int $stock, float $discount = 0, string $discountType = 'percent'): ?Item
    {
        if (DB::table('items')->where('store_id', $storeId)->where('catalog_product_id', $product->id)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($product, $storeId, $price, $stock, $discount, $discountType) {
            $item = Item::withoutGlobalScopes()->create([
                'name' => $product->name,
                'description' => $product->description ?? '',
                'image' => $product->image,
                'images' => $product->images ?? [],
                'unit_id' => $product->unit_id,
                'category_id' => $product->category_id,
                'category_ids' => json_encode($product->category_ids ?? []),
                'price' => $price,
                'discount' => $discount,
                'discount_type' => $discountType,
                'stock' => $stock,
                'store_id' => $storeId,
                'module_id' => $product->module_id,
                'status' => 1,
                'is_approved' => 1,
                'veg' => 0,
                'available_time_starts' => '00:00:00',
                'available_time_ends' => '23:59:59',
                'variations' => json_encode([]),
                'food_variations' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'catalog_product_id' => $product->id,
                'catalog_linked_at' => now(),
            ]);

            // Disks and translations exactly as the catalogue has them.
            $this->applyContent($product, $item->id);

            return $item;
        });
    }

    /**
     * Copies the product's content onto every linked listing, inline (CAT-14:
     * a handful of stores per product; the queued path waits on a verified
     * worker, CAT-17). last_propagated_at is written after the product row,
     * so it is never older than updated_at unless a propagation failed.
     */
    public function propagate(CatalogProduct $product): int
    {
        $ids = DB::table('items')->where('catalog_product_id', $product->id)->pluck('id');

        DB::transaction(function () use ($product, $ids) {
            foreach ($ids as $id) {
                $this->applyContent($product, (int) $id);
            }
            DB::table('catalog_products')->where('id', $product->id)->update(['last_propagated_at' => now()]);
        });

        return $ids->count();
    }

    /**
     * A store saved a linked listing. Its price, stock and status stand;
     * its content goes back to the catalogue's (decision 3), and a photo the
     * store just uploaded that nothing else uses is removed.
     *
     * Returns true when the store's save had changed content — the caller
     * tells the store it was not applied (CAT-12).
     */
    public function reassertContent(int $itemId): bool
    {
        $listing = DB::table('items')->where('id', $itemId)->first();
        $product = $listing?->catalog_product_id ? CatalogProduct::find($listing->catalog_product_id) : null;
        if (!$product) {
            return false;
        }

        $changed = $this->contentDiffers($listing, $product);
        $listingFiles = $this->files($listing->image, $listing->images);

        $this->applyContent($product, $itemId);
        $this->deleteUnusedFiles(array_diff($listingFiles, $this->files($product->image, $product->images)));

        return $changed;
    }

    /** A bulk-import row for an existing listing, minus the content a linked listing does not own. */
    public function withoutManagedContent(array $row, ?int $catalogProductId): array
    {
        return $catalogProductId ? array_diff_key($row, array_flip(array_merge(self::CONTENT_COLUMNS, ['slug']))) : $row;
    }

    public function isLinked(int $itemId): bool
    {
        return DB::table('items')->where('id', $itemId)->whereNotNull('catalog_product_id')->exists();
    }

    /** Creates a catalogue product whose content is a copy of one listing's. */
    public function createFromListing(object $listing, ?string $sizeValue = null): CatalogProduct
    {
        $disks = $this->storageDisks(Item::class, $listing->id);

        $product = CatalogProduct::create([
            'module_id' => $listing->module_id,
            'name' => $listing->name,
            'description' => $listing->description,
            'image' => $listing->image ?: null,
            'image_storage' => $disks['image'] ?? 'public',
            'images' => $this->decodeImages($listing->images),
            'unit_id' => $listing->unit_id,
            'size_value' => $sizeValue,
            'category_id' => $listing->category_id,
            'category_ids' => $this->decodeJson($listing->category_ids),
            'status' => true,
        ]);

        foreach ($this->contentTranslations(Item::class, $listing->id) as $translation) {
            DB::table('translations')->insert([
                'translationable_type' => CatalogProduct::class,
                'translationable_id' => $product->id,
                'locale' => $translation->locale,
                'key' => $translation->key,
                'value' => $translation->value,
            ]);
        }

        return $product;
    }

    /**
     * Links a listing and gives it the catalogue's content. The content it had
     * before is kept in catalog_content_backup — once, on first link, so a
     * re-link never overwrites the original with catalogue content.
     */
    public function linkListing(CatalogProduct $product, int $itemId): void
    {
        $listing = DB::table('items')->where('id', $itemId)->first();
        if (!$listing) {
            return;
        }

        $update = [
            'catalog_product_id' => $product->id,
            'catalog_linked_at' => now(),
        ];
        if ($listing->catalog_content_backup === null) {
            $update['catalog_content_backup'] = json_encode($this->snapshot($listing));
        }

        DB::table('items')->where('id', $itemId)->update($update);

        $this->applyContent($product, $itemId);
    }

    /** Copies the catalogue product's content onto one listing: columns, translations, image disks. */
    public function applyContent(CatalogProduct $product, int $itemId): void
    {
        DB::table('items')->where('id', $itemId)->update([
            'name' => $product->name,
            'description' => $product->description,
            'image' => $product->image,
            'images' => json_encode($product->images ?? []),
            'unit_id' => $product->unit_id,
            'category_id' => $product->category_id,
            'category_ids' => json_encode($product->category_ids ?? []),
        ]);

        $this->writeStorageDisk(Item::class, $itemId, 'image', $product->image_storage ?: 'public');
        $this->writeStorageDisk(Item::class, $itemId, 'images', $this->imagesDisk($product->images));

        $this->replaceContentTranslations(
            $itemId,
            DB::table('translations')
                ->where('translationable_type', CatalogProduct::class)
                ->where('translationable_id', $product->id)
                ->whereIn('key', self::CONTENT_TRANSLATION_KEYS)
                ->get(['locale', 'key', 'value'])
                ->map(fn ($t) => (array) $t)
                ->all()
        );
    }

    /**
     * Puts a listing's pre-link content back and unlinks it. Returns false
     * when there is no backup (linked some other way): that listing is left
     * as it is rather than unlinked with catalogue content it never owned.
     */
    public function restoreListing(int $itemId): bool
    {
        $listing = DB::table('items')->where('id', $itemId)->first();
        if (!$listing || $listing->catalog_content_backup === null) {
            return false;
        }

        $backup = json_decode($listing->catalog_content_backup, true);
        $columns = $backup['columns'] ?? [];

        DB::table('items')->where('id', $itemId)->update(array_merge(
            array_intersect_key($columns, array_flip(self::CONTENT_COLUMNS)),
            [
                'catalog_product_id' => null,
                'catalog_linked_at' => null,
                'catalog_content_backup' => null,
            ]
        ));

        foreach (['image', 'images'] as $key) {
            if (isset($backup['disks'][$key])) {
                $this->writeStorageDisk(Item::class, $itemId, $key, $backup['disks'][$key]);
            }
        }

        $this->replaceContentTranslations($itemId, $backup['translations'] ?? []);

        return true;
    }

    /** Everything restoreListing() needs, taken before the listing's content is replaced. */
    private function snapshot(object $listing): array
    {
        $columns = [];
        foreach (self::CONTENT_COLUMNS as $column) {
            $columns[$column] = $listing->{$column};
        }

        return [
            'taken_at' => now()->toIso8601String(),
            'columns' => $columns,
            'disks' => $this->storageDisks(Item::class, $listing->id),
            'translations' => $this->contentTranslations(Item::class, $listing->id)
                ->map(fn ($t) => (array) $t)
                ->all(),
        ];
    }

    private function contentDiffers(object $listing, CatalogProduct $product): bool
    {
        $same = (string) $listing->name === (string) $product->name
            && trim((string) $listing->description) === trim((string) $product->description)
            && (string) $listing->image === (string) $product->image
            && $this->files(null, $listing->images) === $this->files(null, $product->images)
            && (int) $listing->unit_id === (int) $product->unit_id
            && (int) $listing->category_id === (int) $product->category_id;

        if (!$same) {
            return true;
        }

        $fingerprint = fn ($rows) => collect($rows)
            ->map(fn ($t) => (array) $t)
            ->map(fn ($t) => "{$t['locale']}|{$t['key']}|{$t['value']}")
            ->sort()
            ->values()
            ->all();

        return $fingerprint($this->contentTranslations(Item::class, $listing->id))
            !== $fingerprint($this->contentTranslations(CatalogProduct::class, $product->id));
    }

    /** Filenames a row shows: main image plus gallery, sorted. */
    public function files(?string $image, $images): array
    {
        $files = array_filter(array_merge(
            [$image],
            array_column($this->decodeImages($images), 'img')
        ), fn ($file) => is_string($file) && $file !== '' && $file !== 'def.png');

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    public function deleteUnusedFiles(array $files): void
    {
        foreach ($files as $file) {
            Helpers::deleteProductImageIfUnreferenced($file);
        }
    }

    private function replaceContentTranslations(int $itemId, array $translations): void
    {
        DB::table('translations')
            ->where('translationable_type', Item::class)
            ->where('translationable_id', $itemId)
            ->whereIn('key', self::CONTENT_TRANSLATION_KEYS)
            ->delete();

        foreach ($translations as $translation) {
            DB::table('translations')->insert([
                'translationable_type' => Item::class,
                'translationable_id' => $itemId,
                'locale' => $translation['locale'],
                'key' => $translation['key'],
                'value' => $translation['value'],
            ]);
        }
    }

    private function contentTranslations(string $type, int $id)
    {
        return DB::table('translations')
            ->where('translationable_type', $type)
            ->where('translationable_id', $id)
            ->whereIn('key', self::CONTENT_TRANSLATION_KEYS)
            ->get(['locale', 'key', 'value']);
    }

    private function storageDisks(string $type, int $id): array
    {
        return DB::table('storages')
            ->where('data_type', $type)
            ->where('data_id', (string) $id)
            ->whereIn('key', ['image', 'images'])
            ->pluck('value', 'key')
            ->all();
    }

    private function writeStorageDisk(string $type, int $id, string $key, string $disk): void
    {
        DB::table('storages')->updateOrInsert(
            ['data_type' => $type, 'data_id' => (string) $id, 'key' => $key],
            ['value' => $disk, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function imagesDisk(?array $images): string
    {
        $first = $images[0] ?? null;

        return is_array($first) && !empty($first['storage']) ? $first['storage'] : 'public';
    }

    /** `images` in any historical shape → a list of {img, storage}. */
    public function decodeImages($value): array
    {
        $decoded = $this->decodeJson($value) ?? [];

        return collect($decoded)
            ->map(fn ($image) => is_array($image) ? $image : ['img' => $image, 'storage' => 'public'])
            ->filter(fn ($image) => !empty($image['img']))
            ->values()
            ->all();
    }

    private function decodeJson($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
