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
        // Full-width letters and digits (typed with a Chinese keyboard) become normal ones.
        if (class_exists(\Normalizer::class)) {
            $folded = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            $text = $folded === false ? $text : $folded;
        }
        $text = self::simplified(mb_strtolower(trim($text)));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** Traditional Chinese becomes Simplified, so one menu keyword understands both ("多少錢" and "多少钱"). Needs PHP's intl; without it the text is left as typed. */
    public static function simplified(string $text): string
    {
        static $transliterator = false;

        if ($transliterator === false) {
            $transliterator = class_exists(\Transliterator::class) ? (\Transliterator::create('Traditional-Simplified') ?: null) : null;
        }

        if ($transliterator === null || ! preg_match('/\p{Han}/u', $text)) {
            return $text;
        }

        return $transliterator->transliterate($text) ?: $text;
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

    /** 'zh' for text with Chinese characters, 'en' for a plain English sentence (two or more Latin words), otherwise null (unclear, so keep the current language). */
    public static function typedLanguage(string $text): ?string
    {
        if (preg_match_all('/\p{Han}/u', $text) >= 2) {
            return 'zh';
        }

        if (! preg_match('/\p{Han}/u', $text) && preg_match_all('/\b[A-Za-z]{2,}\b/', $text) >= 2) {
            return 'en';
        }

        return null;
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
