<?php

namespace App\Services\Search;

/**
 * A customer's search text, cleaned once so every search endpoint matches the
 * same way: whitespace collapsed (so "chicken  burger" never yields an empty
 * term that LIKE-matches every row), length and term count capped, and LIKE
 * wildcards in the user's text escaped.
 */
class SearchQuery
{
    public const MAX_LENGTH = 100;
    public const MAX_TERMS = 5;

    private string $text;

    /** @var string[] */
    private array $terms;

    private function __construct(string $text, array $terms)
    {
        $this->text = $text;
        $this->terms = $terms;
    }

    public static function fromString($raw): self
    {
        $text = is_scalar($raw) ? (string) $raw : '';
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        $text = trim(mb_substr($text, 0, self::MAX_LENGTH));

        $terms = $text === '' ? [] : explode(' ', $text);
        $terms = array_values(array_unique($terms));
        $terms = array_slice($terms, 0, self::MAX_TERMS);

        return new self($text, $terms);
    }

    public function text(): string
    {
        return $this->text;
    }

    /** @return string[] */
    public function terms(): array
    {
        return $this->terms;
    }

    public function isEmpty(): bool
    {
        return $this->terms === [];
    }

    public function hasMultipleTerms(): bool
    {
        return count($this->terms) > 1;
    }

    /** "%value%" with the user's own %, _ and \ matched literally. */
    public static function contains(string $value): string
    {
        return '%' . self::escapeLike($value) . '%';
    }

    public static function startsWith(string $value): string
    {
        return self::escapeLike($value) . '%';
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
