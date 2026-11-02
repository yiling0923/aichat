<?php

namespace App\Support;

/**
 * A waiting time in words people read at a glance: "45 minutes", "2 hours 5 minutes", "3 days 22 hours".
 * Stored texts use the English words; localize() turns them into the reader's language when they are shown.
 */
class Duration
{
    /** English words for a number of minutes (days and hours once it is a day or more, hours and minutes once it is an hour or more). */
    public static function words(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $unit = fn (int $n, string $one) => $n.' '.$one.($n === 1 ? '' : 's');

        if ($minutes < 60) {
            return $unit($minutes, 'minute');
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $rest = $minutes % 60;

        if ($days > 0) {
            return $unit($days, 'day').($hours > 0 ? ' '.$unit($hours, 'hour') : '');
        }

        return $unit($hours, 'hour').($rest > 0 ? ' '.$unit($rest, 'minute') : '');
    }

    /** Turn an English duration ("3 days 22 hours", or the old "5655 minutes") into the current language. */
    public static function localize(string $phrase): string
    {
        if (app()->getLocale() === 'en' || ! preg_match_all('/(\d+) (day|hour|minute)s?/', $phrase, $m, PREG_SET_ORDER)) {
            return $phrase;
        }

        return implode(' ', array_map(fn ($p) => __(':n '.$p[2], ['n' => $p[1]]), $m));
    }

    /** The waiting time in the language of the current reader. */
    public static function text(int $minutes): string
    {
        return self::localize(self::words($minutes));
    }
}
