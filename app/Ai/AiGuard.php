<?php

namespace App\Ai;

/**
 * Checks what the AI wants to say before a customer sees it. The AI may only quote prices, discounts and
 * links that exist in the business knowledge, so a made-up "RM 9999" or "50% off" is stopped here.
 */
class AiGuard
{
    private const CURRENCY = '(?:RM|MYR|USD|SGD|HKD|S\$|HK\$|\$|¥|￥)';

    /** @return string|null the reason it was blocked, or null when it is fine */
    public function check(string $reply, string $knowledge): ?string
    {
        $reply = trim($reply);

        if ($reply === '') {
            return 'empty reply';
        }
        if (mb_strlen($reply) > 1200) {
            return 'reply too long';
        }

        $known = $this->numbers($knowledge);

        // Prices and percentages: currency + number, number + currency word, or number + %.
        preg_match_all('/'.self::CURRENCY.'\s?(\d[\d,]*(?:\.\d+)?)|(\d[\d,]*(?:\.\d+)?)\s?(?:%|％|令吉|元|ringgit)/iu', $reply, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $number = $this->normalize($hit[1] !== '' ? $hit[1] : ($hit[2] ?? ''));
            if ($number !== '' && ! in_array($number, $known, true)) {
                return 'price or discount not in the business knowledge: '.$hit[0];
            }
        }

        // Links: only those that appear in the knowledge.
        preg_match_all('#https?://[^\s)]+#i', $reply, $links);
        foreach ($links[0] as $link) {
            if (! str_contains($knowledge, rtrim($link, '.,;'))) {
                return 'link not in the business knowledge';
            }
        }

        return null;
    }

    /** Plain text for WhatsApp and friends: no markdown decoration. */
    public function clean(string $reply): string
    {
        $reply = preg_replace('/[*_`#]{1,3}/', '', $reply) ?? $reply;

        return trim(preg_replace("/\n{3,}/", "\n\n", $reply) ?? $reply);
    }

    /** @return list<string> every number that appears in the knowledge, without separators */
    private function numbers(string $text): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $m);

        return array_values(array_unique(array_map(fn ($n) => $this->normalize($n), $m[0])));
    }

    private function normalize(string $number): string
    {
        $number = str_replace(',', '', $number);

        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }
}
