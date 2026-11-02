<?php

namespace App\Support;

/**
 * Notifications are saved in English at the moment they happen. The bell shows them in the language of the person reading,
 * so older ones change language too when someone switches.
 */
class NotificationText
{
    /** Titles with a name or number inside. The first group is always the changing part. */
    private const TITLES = [
        '/^New chat from (.+)$/s' => ['New chat from :x', 'x'],
        '/^New message from (.+)$/s' => ['New message from :x', 'x'],
        '/^(.+) wrote again$/s' => [':x wrote again', 'x'],
        '/^(.+) went back to the menu$/s' => [':x went back to the menu', 'x'],
        '/^(.+) needs a person$/s' => [':x needs a person', 'x'],
        '/^(.+) may be a complaint$/s' => [':x may be a complaint', 'x'],
        '/^(.+) went back to the bot$/s' => [':x went back to the bot', 'x'],
        '/^New booking: (.+)$/s' => ['New booking: :x', 'x'],
        '/^Booking cancelled by (.+)$/s' => ['Booking cancelled by :x', 'x'],
        '/^Booking changed by (.+)$/s' => ['Booking changed by :x', 'x'],
        '/^New task for you: (.+)$/s' => ['New task for you: :x', 'x'],
        '/^Plan limit reached: (.+)$/s' => ['Plan limit reached: :x', 'x'],
    ];

    public static function title(string $text): string
    {
        if (app()->getLocale() === 'en') {
            return $text;
        }

        $urgent = false;
        if (str_starts_with($text, 'URGENT: ')) {
            $urgent = true;
            $text = substr($text, 8);
        }

        if (preg_match('/^(.+) has waited (\d+ (?:days?|hours?|minutes?)(?: \d+ (?:hours?|minutes?))?)$/s', $text, $m)) {
            $out = __(':who has waited :time', ['who' => self::who($m[1]), 'time' => \App\Support\Duration::localize($m[2])]);
        } elseif (preg_match('/^Low rating \((\d)\/5\) from (.+)$/s', $text, $m)) {
            $out = __('Low rating (:r/5) from :x', ['r' => $m[1], 'x' => self::who($m[2])]);
        } else {
            $out = null;
            foreach (self::TITLES as $pattern => [$key]) {
                if (preg_match($pattern, $text, $m)) {
                    $out = __($key, ['x' => in_array($key, ['Plan limit reached: :x'], true) ? __($m[1]) : self::who($m[1])]);
                    break;
                }
            }
            $out ??= __($text);
        }

        return ($urgent ? __('URGENT:').' ' : '').$out;
    }

    public static function body(?string $text): ?string
    {
        if ($text === null || $text === '' || app()->getLocale() === 'en') {
            return $text;
        }

        if (preg_match('/^Nobody answered for (\d+ (?:days?|hours?|minutes?)(?: \d+ (?:hours?|minutes?))?)\. Reply any time to take over again\.$/', $text, $m)) {
            return __('Nobody answered for :time. Reply any time to take over again.', ['time' => \App\Support\Duration::localize($m[1])]);
        }
        if (preg_match('/^Customer sent a (photo|video|voice message|file|location|attachment)$/', $text, $m)) {
            return __('Customer sent a :what', ['what' => __($m[1])]);
        }

        return __($text);
    }

    /** "a customer", "Customer" and "Visitor 12" are words the system wrote; a real name is left alone. */
    private static function who(string $name): string
    {
        if (preg_match('/^Visitor (\d+)$/', $name, $m)) {
            return __('Visitor :n', ['n' => $m[1]]);
        }

        return in_array($name, ['a customer', 'Customer', 'the customer'], true) ? __($name) : $name;
    }
}
