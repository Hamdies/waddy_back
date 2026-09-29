<?php

namespace App\Services;

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
 * No method here deletes a file. Old photos stay on disk, named in
 * catalog_content_backup, until a prune has checked nothing uses them.
 */
class CatalogService
{
    /** Content a listing takes from its catalogue product. Price, stock, discount, status stay the store's. */
    public const CONTENT_COLUMNS = ['name', 'description', 'image', 'images', 'unit_id', 'category_id', 'category_ids'];

    /** Translation keys that are content. */
    public const CONTENT_TRANSLATION_KEYS = ['name', 'description'];

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
