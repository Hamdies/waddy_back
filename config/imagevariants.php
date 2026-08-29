<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When off, uploads behave exactly as they did before: the original is
    | stored and nothing else happens. Existing variants on disk are left
    | alone and simply stop being advertised by the API.
    |
    */

    'enabled' => env('IMAGE_VARIANTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Intervention driver
    |--------------------------------------------------------------------------
    |
    | 'gd' needs php-gd built with WebP support (it has been the default since
    | PHP 7.0). 'imagick' is better on large sources but is not installed on
    | the production box. Check with:
    |
    |   php -r '$i = gd_info(); var_dump($i["WebP Support"]);'
    |
    */

    'driver' => env('IMAGE_VARIANTS_DRIVER', 'gd'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Which queue backfill jobs go on. Keep it off 'default' if a long backfill
    | would otherwise sit in front of push notifications.
    |
    */

    'queue' => env('IMAGE_VARIANTS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Sizes
    |--------------------------------------------------------------------------
    |
    | The longest edge, in pixels. Aspect ratio is preserved and sources are
    | never upscaled, so a 300x300 source asked for 'card' yields 300x300 —
    | still worth generating, because the format conversion is most of the win.
    |
    */

    'sizes' => [
        'thumb' => 150,
        'card'  => 400,
        'hero'  => 1200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Encoding
    |--------------------------------------------------------------------------
    |
    | 'fallback_format' may be set to null to skip the second encode entirely,
    | which halves both backfill time and storage. Keep it while clients older
    | than WebP support are still in the field.
    |
    | Transparent sources are flattened onto 'fallback_background' for the
    | fallback only; the WebP keeps its alpha channel.
    |
    */

    'webp_quality' => (int) env('IMAGE_VARIANTS_WEBP_QUALITY', 80),

    'fallback_format' => env('IMAGE_VARIANTS_FALLBACK_FORMAT', 'jpg'),

    'fallback_quality' => (int) env('IMAGE_VARIANTS_FALLBACK_QUALITY', 82),

    'fallback_background' => '#ffffff',

    /*
    |--------------------------------------------------------------------------
    | Source constraints
    |--------------------------------------------------------------------------
    |
    | Anything not in 'source_formats' is passed through untouched — the same
    | upload helper handles PDFs, .p8 keys and videos, and those must not be
    | fed to an image decoder.
    |
    | GIFs are excluded deliberately: Intervention 2 flattens animation to the
    | first frame, so a variant would silently lose it.
    |
    */

    'source_formats' => ['jpg', 'jpeg', 'png', 'webp'],

    'max_source_bytes' => (int) env('IMAGE_VARIANTS_MAX_SOURCE_BYTES', 12 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Directories
    |--------------------------------------------------------------------------
    |
    | Keys are the directory as passed to Helpers::upload(), without the
    | trailing slash. Values are which sizes to generate. A directory absent
    | from this list gets no variants.
    |
    | Order matters: the first size listed is the one whose existence is
    | probed to decide whether a variant set is available at all.
    |
    */

    'directories' => [
        'store'              => ['thumb', 'card'],
        'store/cover'        => ['card', 'hero'],
        'product'            => ['thumb', 'card'],
        'category'           => ['thumb', 'card'],
        'banner'             => ['card', 'hero'],
        'campaign'           => ['card', 'hero'],
        'promotional_banner' => ['card', 'hero'],
        'parcel_category'    => ['thumb', 'card'],
        'module'             => ['thumb'],
        'level'              => ['thumb'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Read path
    |--------------------------------------------------------------------------
    |
    | 'variant_root' is the subdirectory variants live under, chosen so it can
    | never collide with a content directory (store/cover/ is a real one).
    |
    | 'exists_cache_ttl' is how long the "does this image have variants?"
    | answer is cached. On S3 the underlying check is an HTTP HEAD, so this is
    | load-bearing rather than a micro-optimisation.
    |
    | 'request_opt_in' gates whether the API advertises variants at all. Old
    | app builds cannot use them and should not pay ~150 bytes per card to be
    | told about them, so the payload only changes for a client that asks.
    |
    */

    'variant_root' => 'variants',

    'exists_cache_ttl' => (int) env('IMAGE_VARIANTS_EXISTS_TTL', 86400),

    'request_opt_in' => [
        'header' => 'X-Image-Variants',
        'query'  => 'image_variants',
    ],

];
