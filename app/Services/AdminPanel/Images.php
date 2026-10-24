<?php

namespace App\Services\AdminPanel;

use App\Services\ImageVariantService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Image URLs for list rows. Helpers::get_full_url checks the disk for every
 * image, which is fine for one record and slow for a page of them; here the
 * URL is built from the `storages` row, the small `thumb` variant is used when
 * ImageVariantService has one (its existence check is cached), and a missing
 * file falls back to initials in the browser.
 */
class Images
{
    /**
     * Selects which disk holds `$key` of `$model` as `$alias`. A scalar
     * subquery, not a join, so a duplicated storages row can't duplicate a list row.
     */
    public static function selectDisk(Builder $q, string $model, string $idColumn, string $key, string $alias): Builder
    {
        $pdo = DB::connection()->getPdo();
        return $q->selectRaw("(select value from storages where data_type = {$pdo->quote($model)} and data_id = $idColumn and `key` = {$pdo->quote($key)} limit 1) as $alias");
    }

    public static function url(string $dir, ?string $file, ?string $disk = 'public'): ?string
    {
        if (!$file || $file === 'def.png') {
            return null;
        }
        $disk = $disk === 's3' ? 's3' : 'public';
        $variants = app(ImageVariantService::class)->urls($dir, $file, $disk);
        if ($variants) {
            return $variants['webp']['thumb'] ?? reset($variants['webp']) ?: $variants['original'];
        }
        if ($disk === 's3') {
            try {
                return Storage::disk('s3')->url($dir . '/' . $file);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return asset('storage/' . $dir . '/' . $file);
    }

    /** First image of an item's `images` JSON ([{img, storage}] or plain names). */
    public static function firstOf(string $dir, ?string $json): ?string
    {
        $list = json_decode($json ?? '', true);
        if (!is_array($list) || !$list) {
            return null;
        }
        $first = $list[0];
        return is_array($first) ? self::url($dir, $first['img'] ?? null, $first['storage'] ?? 'public') : self::url($dir, (string) $first);
    }
}
