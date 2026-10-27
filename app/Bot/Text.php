<?php

namespace App\Bot;

/**
 * Small helpers for comparing what customers type with labels and keywords.
 */
class Text
{
    /** Lower case, no punctuation, single spaces. Works for Chinese too. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function isPhrase(string $keyword): bool
    {
        // Multi-word keywords and Chinese words are matched anywhere in the text.
        return str_contains($keyword, ' ') || strlen($keyword) !== mb_strlen($keyword);
    }

    /** True when the keyword appears in the text as a word or phrase. */
    public static function containsKeyword(string $normalizedText, string $keyword): bool
    {
        $keyword = self::normalize($keyword);

        if ($keyword === '' || $normalizedText === '') {
            return false;
        }

        if (self::isPhrase($keyword)) {
            return str_contains($normalizedText, $keyword);
        }

        return in_array($keyword, explode(' ', $normalizedText), true);
    }

    /**
     * Stricter check for the global commands (menu, human, stop): a single
     * word only counts when it is the whole message, so "which staff do you
     * have" does not trigger a handover.
     *
     * @param  list<string>  $keywords
     */
    public static function isCommand(string $normalizedText, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = self::normalize($keyword);

            if ($keyword === '') {
                continue;
            }

            if (self::isPhrase($keyword) ? str_contains($normalizedText, $keyword) : $normalizedText === $keyword) {
                return true;
            }
        }

        return false;
    }

    /** Fill {name}, {phone} and friends. Unknown placeholders become empty. */
    public static function fill(string $text, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', function (array $m) use ($values): string {
            $value = $values[$m[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $text) ?? $text;
    }
}
