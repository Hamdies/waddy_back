<?php

namespace App\Traits;

use App\Services\ImageVariantService;

/**
 * Exposes WebP variant sets alongside the existing *_full_url attributes.
 *
 * Strictly additive: the string URLs every client reads today are untouched,
 * and the new keys are only appended when the caller opts in (see
 * ImageVariantService::requested()), so an old app build gets a byte-identical
 * payload.
 *
 * A model using this lists the attributes to append and writes one accessor
 * per image column, in the same shape as the *_full_url accessors beside them:
 *
 *   public function imageVariantAppends(): array
 *   {
 *       return ['logo_variants'];
 *   }
 *
 *   public function getLogoVariantsAttribute(): ?array
 *   {
 *       return $this->imageVariantUrls('store', $this->logo, 'logo');
 *   }
 */
trait HasImageVariants
{
    public static function bootHasImageVariants(): void
    {
        static::retrieved(function ($model) {
            // requested() first: it is memoised for the whole request, so the
            // common path (an old client) costs one static comparison per row.
            if (ImageVariantService::requested() && $appends = $model->imageVariantAppends()) {
                $model->append($appends);
            }
        });
    }

    /**
     * Attributes to append when the caller opts in. Overridden per model; a
     * method rather than a property so that a model declaring its own list
     * cannot collide with a trait-declared one.
     *
     * @return list<string>
     */
    public function imageVariantAppends(): array
    {
        return [];
    }

    /**
     * Resolves the disk the way the *_full_url accessors do: the storages
     * table records, per record and per column, whether the file went to the
     * public disk or S3, and that setting can change between uploads.
     */
    protected function imageVariantUrls(string $dir, ?string $value, string $storageKey): ?array
    {
        if (!$value) {
            return null;
        }

        // relationLoaded() rather than a plain access: touching ->storage on a
        // model that did not eager-load it costs a query per row, which is the
        // N+1 this tier exists to remove. Store has a global scope that loads
        // it; the others do not, so they fall back to the same global setting
        // Helpers::upload() would have used.
        $disk = \App\CentralLogics\Helpers::getDisk();

        if ($this->relationLoaded('storage')) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] === $storageKey) {
                    $disk = $storage['value'];
                    break;
                }
            }
        }

        return app(ImageVariantService::class)->urls($dir, $value, $disk);
    }
}
