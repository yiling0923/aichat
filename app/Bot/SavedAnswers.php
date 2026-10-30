<?php

namespace App\Bot;

use App\Models\KnowledgeEntry;

/**
 * The extra answers the owner wrote in AI assistant > Teach it, used WITHOUT any AI: when a customer's typed words point to one
 * (its title, or a common word for it such as "pay" or "停车"), that answer is sent as it was written.
 */
class SavedAnswers
{
    /** Words that point to a topic even when the title is worded differently. Keys are normalised titles. */
    private const SYNONYMS = [
        'parking' => ['park', '停车', '泊车', '车位'],
        'payment methods' => ['pay', 'payment', 'duitnow', 'touch n go', 'tng', 'cash', 'credit card', 'debit', 'ewallet', 'e wallet', 'transfer', '付款', '支付', '付钱', '转账', '刷卡'],
        'aftercare' => ['after care', 'after treatment', 'after the treatment', 'after my treatment', '护理', '术后', '疗程后'],
        'cancellation policy' => ['cancel', 'refund', 'reschedule', '取消', '退款', '改期'],
        'walk ins' => ['walk in', 'walkin', 'without booking', 'no appointment', '直接到店', '直接来', '不用预约'],
        'first visit' => ['first time', 'first visit', 'new customer', 'never been', '第一次', '首次', '新客'],
    ];

    /** Topics whose common words ("cancel") also mean something to the menu, so they only apply when the menu has no match. */
    private const MENU_CLASH = ['cancellation policy'];

    /** Title words too common to point to a topic by themselves. */
    private const GENERIC = ['policy', 'method', 'methods', 'info', 'information', 'about', 'the', 'and', 'for', 'your', 'our', 'how', 'what'];

    /**
     * The saved answer the typed words point to, in the customer's language, or null.
     * $titleOnly keeps just the sure matches (the title itself is in the words), for when the menu might also claim them.
     */
    public function find(string $text, string $language, bool $titleOnly = false): ?string
    {
        $typed = Text::normalize($text);

        if ($typed === '' || mb_strlen($typed) > 200) {
            return null;
        }

        $words = explode(' ', $typed);
        $best = null;
        $bestScore = 0;

        foreach (KnowledgeEntry::query()->where('active', true)->orderBy('id')->get() as $entry) {
            $score = $this->score($typed, $words, Text::normalize((string) $entry->title), self::keywordList((string) $entry->keywords));

            if ($score > $bestScore) {
                $best = $entry;
                $bestScore = $score;
            }
        }

        if ($best === null || ($titleOnly && $bestScore < 100)) {
            return null;
        }

        $answers = (array) $best->answer;
        $answer = trim((string) ($answers[$language] ?? '')) ?: trim((string) ($answers['en'] ?? '')) ?: trim((string) ($answers['zh'] ?? ''));

        return $answer !== '' ? $answer : null;
    }

    /** @return list<string> the owner's keywords (comma or line separated), normalised */
    public static function keywordList(string $raw): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($k) => Text::normalize($k), preg_split('/[,，、;；\n]+/u', $raw) ?: []), fn ($k) => mb_strlen($k) >= 2)));
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $keywords  extra words the owner listed for this answer (normalised)
     */
    private function score(string $typed, array $words, string $title, array $keywords = []): int
    {
        if ($title === '') {
            return 0;
        }

        if (str_contains($typed, $title)) {
            return 100;
        }

        foreach ($keywords as $keyword) {
            if (preg_match('/\p{Han}/u', $keyword) ? str_contains($typed, $keyword) : str_contains(' '.$typed.' ', ' '.$keyword.' ')) {
                return 100;
            }
        }

        foreach (self::SYNONYMS[$title] ?? [] as $synonym) {
            if (str_contains(' '.$typed.' ', ' '.$synonym.' ') || (preg_match('/\p{Han}/u', $synonym) && str_contains($typed, $synonym)) || (mb_strlen($synonym) >= 4 && str_contains($typed, $synonym))) {
                return in_array($title, self::MENU_CLASH, true) ? 60 : 100;
            }
        }

        // Chinese titles are matched as a whole (there are no spaces to split on).
        if (preg_match('/\p{Han}/u', $title)) {
            return 0;
        }

        $significant = array_values(array_filter(explode(' ', $title), fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::GENERIC, true)));

        if ($significant === []) {
            return 0;
        }

        $hits = 0;
        foreach ($significant as $word) {
            $stem = mb_substr($word, 0, 5);
            foreach ($words as $typedWord) {
                if (mb_strlen($typedWord) >= 3 && str_starts_with($typedWord, $stem)) {
                    $hits++;
                    break;
                }
            }
        }

        // A one-word topic needs that word; a longer title needs at least two of its words (so "visit" alone is not "First visit").
        return $hits >= min(2, count($significant)) ? 10 + $hits : 0;
    }
}
