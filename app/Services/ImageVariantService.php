<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Derives small, WebP-encoded copies of uploaded images.
 *
 * Store logos are 300x300 PNGs at ~57 KB rendered on a ~120 px card. The
 * conversion, not the resize, is most of the saving: the same 300 px image as
 * WebP is roughly 10 KB.
 *
 * Two design constraints shaped this:
 *
 *   1. Variant paths are a pure function of the original path. Nothing is
 *      recorded in the database, so there is no migration, no per-model
 *      column, and no way for a manifest to drift from what is on disk.
 *      The cost is one existence probe per image on read, cached.
 *
 *   2. Generation never blocks a request and never fails one. A store must
 *      still save if the encoder chokes on its logo; the API then serves the
 *      original, exactly as it does today.
 */
class ImageVariantService
{
    /** @var array<string, bool> per-request memo for hasVariants() */
    private array $existsMemo = [];

    private static ?bool $requested = null;

    // ---------------------------------------------------------------- config

    public function enabled(): bool
    {
        return (bool) config('imagevariants.enabled', false);
    }

    /**
     * The sizes configured for a directory, or null if it gets no variants.
     *
     * @return list<string>|null
     */
    public function sizesFor(string $dir): ?array
    {
        // Indexed directly rather than through config() dot-notation, because
        // these keys contain slashes ('store/cover') and dots are the separator.
        $sizes = config('imagevariants.directories', [])[$this->normaliseDir($dir)] ?? null;

        return is_array($sizes) && $sizes !== [] ? array_values($sizes) : null;
    }

    public function isSupportedSource(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, config('imagevariants.source_formats', []), true);
    }

    /**
     * Whether this upload is a candidate for variants at all. Cheap, no I/O —
     * safe to call on the hot path of every upload.
     */
    public function shouldGenerate(string $dir, ?string $filename): bool
    {
        return $this->enabled()
            && $filename !== null
            && $filename !== ''
            && $filename !== 'def.png'
            && $this->sizesFor($dir) !== null
            && $this->isSupportedSource($filename);
    }

    // ------------------------------------------------------------ path logic

    public function normaliseDir(string $dir): string
    {
        return trim($dir, '/');
    }

    /**
     * store/ + 2024-11-19-abc.png + card + webp
     *   -> store/variants/card/2024-11-19-abc.webp
     */
    public function variantPath(string $dir, string $filename, string $size, string $format): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        return implode('/', [
            $this->normaliseDir($dir),
            config('imagevariants.variant_root', 'variants'),
            $size,
            $base . '.' . $format,
        ]);
    }

    public function originalPath(string $dir, string $filename): string
    {
        return $this->normaliseDir($dir) . '/' . $filename;
    }

    // ------------------------------------------------------------- generation

    /**
     * Encode every configured size for one image.
     *
     * @return list<string> the variant paths written
     */
    public function generate(string $disk, string $dir, string $filename, bool $force = false): array
    {
        if (!$this->shouldGenerate($dir, $filename)) {
            return [];
        }

        $sizes = $this->sizesFor($dir);
        $source = $this->originalPath($dir, $filename);
        $fs = Storage::disk($disk);

        if (!$fs->exists($source)) {
            return [];
        }

        $max = (int) config('imagevariants.max_source_bytes');
        if ($max > 0 && $fs->size($source) > $max) {
            Log::info('Skipped image variants: source too large', ['path' => $source]);

            return [];
        }

        if (!$force && $this->variantsPresent($disk, $dir, $filename)) {
            return [];
        }

        $manager = new ImageManager(['driver' => config('imagevariants.driver', 'gd')]);
        $written = [];

        $fallbackFormat = config('imagevariants.fallback_format');
        $webpQuality = (int) config('imagevariants.webp_quality', 80);
        $fallbackQuality = (int) config('imagevariants.fallback_quality', 82);

        try {
            $binary = $fs->get($source);

            foreach ($sizes as $size) {
                $edge = (int) (config('imagevariants.sizes')[$size] ?? 0);
                if ($edge <= 0) {
                    continue;
                }

                // Decoded fresh per size: encode() mutates the instance, and
                // reusing one across sizes resizes an already-resized image.
                $image = $manager->make($binary);
                $this->orientate($image);
                $image->resize($edge, $edge, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                // No explicit visibility argument: the originals are stored
                // with putFileAs() and no ACL, and passing 'public' here would
                // set an S3 object ACL that buckets with ACLs disabled reject.
                $webpPath = $this->variantPath($dir, $filename, $size, 'webp');
                $fs->put($webpPath, (string) $image->encode('webp', $webpQuality));
                $written[] = $webpPath;

                if ($fallbackFormat) {
                    $flat = $manager->canvas(
                        $image->width(),
                        $image->height(),
                        config('imagevariants.fallback_background', '#ffffff')
                    )->insert($image);

                    $fallbackPath = $this->variantPath($dir, $filename, $size, $fallbackFormat);
                    $fs->put($fallbackPath, (string) $flat->encode($fallbackFormat, $fallbackQuality));
                    $written[] = $fallbackPath;

                    $flat->destroy();
                }

                $image->destroy();
            }
        } catch (\Throwable $e) {
            Log::warning('Image variant generation failed', [
                'path' => $source,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            // Leave whatever succeeded; a partial set still beats the original,
            // and the existence probe keys on the first size, which is written
            // first, so a set that failed halfway is never advertised as whole.
            return $written;
        }

        $this->forgetExists($disk, $dir, $filename);

        return $written;
    }

    /**
     * EXIF orientation, when the driver and source support it. A phone-camera
     * JPEG that renders upright in a browser is stored rotated; skipping this
     * makes the variant disagree with the original.
     */
    private function orientate($image): void
    {
        try {
            $image->orientate();
        } catch (\Throwable $e) {
            // No EXIF, or ext-exif not loaded. Not worth failing over.
        }
    }

    public function delete(string $disk, string $dir, ?string $filename): void
    {
        if (!$filename || $filename === 'def.png') {
            return;
        }

        $sizes = $this->sizesFor($dir);
        if ($sizes === null) {
            return;
        }

        $formats = array_filter(['webp', config('imagevariants.fallback_format')]);
        $paths = [];

        foreach ($sizes as $size) {
            foreach ($formats as $format) {
                $paths[] = $this->variantPath($dir, $filename, $size, $format);
            }
        }

        try {
            Storage::disk($disk)->delete($paths);
        } catch (\Throwable $e) {
            // Orphaned variants are harmless; a failed delete must not break
            // the update that triggered it.
        }

        $this->forgetExists($disk, $dir, $filename);
    }

    // ------------------------------------------------------------------- read

    /**
     * Uncached, authoritative check. Probes the first configured size only —
     * generate() writes sizes in order, so the first present implies the rest
     * unless generation died mid-way, which is logged.
     */
    public function variantsPresent(string $disk, string $dir, string $filename): bool
    {
        $sizes = $this->sizesFor($dir);
        if ($sizes === null) {
            return false;
        }

        try {
            return Storage::disk($disk)->exists($this->variantPath($dir, $filename, $sizes[0], 'webp'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasVariants(string $disk, string $dir, string $filename): bool
    {
        $key = $this->existsCacheKey($disk, $dir, $filename);

        if (array_key_exists($key, $this->existsMemo)) {
            return $this->existsMemo[$key];
        }

        $ttl = (int) config('imagevariants.exists_cache_ttl', 86400);

        try {
            $present = (bool) Cache::remember(
                $key,
                $ttl,
                fn () => $this->variantsPresent($disk, $dir, $filename) ? 1 : 0
            );
        } catch (\Throwable $e) {
            $present = $this->variantsPresent($disk, $dir, $filename);
        }

        return $this->existsMemo[$key] = $present;
    }

    public function forgetExists(string $disk, string $dir, string $filename): void
    {
        $key = $this->existsCacheKey($disk, $dir, $filename);
        unset($this->existsMemo[$key]);

        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
        }
    }

    private function existsCacheKey(string $disk, string $dir, string $filename): string
    {
        return 'img_variants:' . $disk . ':' . $this->originalPath($dir, $filename);
    }

    /**
     * The variant set for one image, or null when there is nothing to offer.
     *
     * @return array{webp: array<string,string>, jpg?: array<string,string>, original: string}|null
     */
    public function urls(string $dir, ?string $filename, string $disk = 'public'): ?array
    {
        if (!$this->enabled() || !$filename || $filename === 'def.png') {
            return null;
        }

        $sizes = $this->sizesFor($dir);
        if ($sizes === null || !$this->isSupportedSource($filename)) {
            return null;
        }

        if (!$this->hasVariants($disk, $dir, $filename)) {
            return null;
        }

        $set = ['webp' => []];
        foreach ($sizes as $size) {
            $set['webp'][$size] = $this->url($disk, $this->variantPath($dir, $filename, $size, 'webp'));
        }

        $fallbackFormat = config('imagevariants.fallback_format');
        if ($fallbackFormat) {
            $set[$fallbackFormat] = [];
            foreach ($sizes as $size) {
                $set[$fallbackFormat][$size] = $this->url($disk, $this->variantPath($dir, $filename, $size, $fallbackFormat));
            }
        }

        $set['original'] = $this->url($disk, $this->originalPath($dir, $filename));

        return $set;
    }

    /**
     * Mirrors Helpers::get_full_url()'s URL shape so variants and originals
     * resolve through the same host and path prefix.
     */
    private function url(string $disk, string $path): string
    {
        if ($disk === 's3') {
            return Storage::disk('s3')->url($path);
        }

        return asset('storage') . '/' . $path;
    }

    // --------------------------------------------------------------- opt-in

    /**
     * Whether the caller asked for variant sets.
     *
     * Appending them unconditionally would add ~150 bytes per card to every
     * response, including the builds in the field that cannot use them — the
     * opposite of the point. A client that understands variants asks for them.
     */
    public static function requested(): bool
    {
        if (self::$requested !== null) {
            return self::$requested;
        }

        // No console guard is needed: in an artisan or queue context the
        // request is built from empty globals, so neither check fires.
        $request = request();
        if (!$request) {
            return self::$requested = false;
        }

        $header = config('imagevariants.request_opt_in.header', 'X-Image-Variants');
        $query = config('imagevariants.request_opt_in.query', 'image_variants');

        if ($request->hasHeader($header)) {
            $value = (string) $request->header($header);

            // A bare header counts as on; an explicit falsy value opts back
            // out, so a client can disable it without changing its URLs.
            return self::$requested = ($value === '' || filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

        return self::$requested = $request->boolean($query);
    }

    /** Test seam; also resets the per-request memo of the opt-in decision. */
    public static function forgetRequested(): void
    {
        self::$requested = null;
    }
}
