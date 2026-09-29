<?php

namespace App\Services;

/**
 * Decides when two product names describe the same product.
 *
 * Deliberately conservative (CAT-13): normalisation only removes differences
 * of *format* — case, punctuation, "1.5 L" vs "1.5l", Arabic letter variants,
 * Arabic-Indic digits. It never drops words and never converts units
 * (1000g stays apart from 1kg), so two names that normalise equal really are
 * spelled the same. Everything fuzzier is only ever *suggested*, via
 * similarity(), for a human to confirm.
 */
class CatalogMatcher
{
    /** Spellings folded onto one unit token. Order matters: longer first. */
    private const UNITS = [
        'ml' => ['ml', 'مل'],
        'l' => ['ltr', 'litre', 'liter', 'lt', 'l', 'لتر'],
        'kg' => ['kgs', 'kg', 'kilo', 'كجم', 'كيلو'],
        'g' => ['grams', 'gram', 'gms', 'gm', 'gr', 'g', 'جرام', 'جم'],
        'pcs' => ['pieces', 'piece', 'pcs', 'pc', 'قطعة', 'قطع'],
        'bags' => ['bags', 'bag'],
        'sheets' => ['sheets'],
    ];

    public static function normalize(?string $name): string
    {
        $s = mb_strtolower(trim((string) $name));

        $s = strtr($s, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.',
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        // Harakat and tatweel.
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);

        // "6 x 1.5 L" → "6x1.5l"
        $s = preg_replace('/(\d)\s*[x×]\s*(\d)/u', '$1x$2', $s);

        $spellings = [];
        foreach (self::UNITS as $canonical => $variants) {
            foreach ($variants as $variant) {
                $spellings[$variant] = $canonical;
            }
        }
        uksort($spellings, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $alternation = implode('|', array_map(fn ($v) => preg_quote($v, '/'), array_keys($spellings)));

        $s = preg_replace_callback(
            '/(\d+(?:\.\d+)?)\s*(' . $alternation . ')(?![\p{L}])/u',
            fn ($m) => self::trimNumber($m[1]) . $spellings[$m[2]],
            $s
        );

        // Punctuation becomes a space; a dot survives only inside a number.
        $s = preg_replace('/[^\p{L}\p{N}.]+/u', ' ', $s);
        $s = preg_replace('/(?<!\d)\.|\.(?!\d)/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /** The pack size in a normalised name ("100ml", "6x1.5l", "30pcs"), if any. */
    public static function sizeToken(string $normalized): ?string
    {
        $units = implode('|', array_keys(self::UNITS));

        return preg_match('/\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?(?:' . $units . ')(?![\p{L}])/u', $normalized, $m)
            ? $m[0]
            : null;
    }

    /** Stable across runs while the name is unchanged, so a key from a dry run can be approved later. */
    public static function groupKey(int $moduleId, string $normalized): string
    {
        return 'g' . substr(sha1($moduleId . '|' . $normalized), 0, 7);
    }

    /**
     * 0..1 word overlap between two normalised names, ignoring the size.
     * Names with different sizes are never similar: 200g and 95g of the same
     * coffee are two products.
     */
    public static function similarity(string $a, string $b): float
    {
        if (self::sizeToken($a) !== self::sizeToken($b)) {
            return 0.0;
        }

        $words = function (string $name) {
            $size = self::sizeToken($name);

            return array_values(array_unique(array_filter(
                explode(' ', $name),
                fn ($word) => $word !== '' && $word !== $size
            )));
        };

        $wa = $words($a);
        $wb = $words($b);
        $union = count(array_unique(array_merge($wa, $wb)));

        return $union === 0 ? 0.0 : count(array_intersect($wa, $wb)) / $union;
    }

    /**
     * Worth a human look: same size, and either most words shared or one
     * name's words all inside the other's ("tomatoes 1kg" / "fresh tomatoes
     * 1kg"). Never merged automatically — fresh vs UHT milk passes this too.
     */
    public static function isPossibleMatch(string $a, string $b): bool
    {
        if ($a === $b || self::sizeToken($a) !== self::sizeToken($b)) {
            return false;
        }

        $size = self::sizeToken($a);
        $words = fn (string $name) => array_values(array_diff(explode(' ', $name), ['', $size]));
        $wa = $words($a);
        $wb = $words($b);
        $smaller = count($wa) <= count($wb) ? $wa : $wb;
        $larger = $smaller === $wa ? $wb : $wa;

        return self::similarity($a, $b) >= 0.6
            || ($smaller !== [] && array_diff($smaller, $larger) === []);
    }

    public static function isArabic(string $name): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $name) && !preg_match('/[a-z]/i', $name);
    }

    private static function trimNumber(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : (ltrim($number, '0') ?: '0');
    }
}
