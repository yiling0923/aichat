<?php

namespace App\Support;

/** The little pieces the platform console screens share: line icons, trend lines, outlined status pills. */
class PlatformUi
{
    public const ICONS = [
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10"/><path d="M8 7h4M8 11h4M8 15h4"/><path d="M3 21h18"/>',
        'hourglass' => '<path d="M6 2h12M6 22h12"/><path d="M7 2v4a5 5 0 0 0 2 4l3 2-3 2a5 5 0 0 0-2 4v4M17 2v4a5 5 0 0 1-2 4l-3 2 3 2a5 5 0 0 1 2 4v4"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-2z"/><circle cx="17" cy="14.5" r="1.2"/>',
        'coins' => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 10.5c3 .2 6 1.4 6 3v4c0 1.7-2.7 3-6 3-1.6 0-3-.3-4.2-.8"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bag' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'open' => '<path d="M7 17L17 7M8 7h9v9"/>',
        'pause' => '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
        'play' => '<path d="M7 4l13 8-13 8z"/>',
        'download' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'plan' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'lifebuoy' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M5.6 5.6l3.9 3.9M14.5 14.5l3.9 3.9M18.4 5.6l-3.9 3.9M9.5 14.5l-3.9 3.9"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/>',
        'eye' => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M14 9l2 2"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'check' => '<path d="M5 12l5 5L20 7"/>',
    ];

    public static function svg(string $name): string
    {
        return '<svg viewBox="0 0 24 24" aria-hidden="true">'.(self::ICONS[$name] ?? '').'</svg>';
    }

    /** A small trend line, one point per day. @param list<int> $values */
    public static function spark(array $values): string
    {
        $n = count($values);
        $max = max(1, max($values ?: [0]));
        $w = 150;
        $h = 36;
        $points = [];
        foreach (array_values($values) as $i => $x) {
            $points[] = round($n > 1 ? $i / ($n - 1) * $w : $w / 2, 1).','.round($h - 3 - ($x / $max) * ($h - 8), 1);
        }
        $line = implode(' ', $points);

        return '<svg class="pk-spark" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" aria-hidden="true"><polygon points="0,'.$h.' '.$line.' '.$w.','.$h.'" class="pk-fill"/><polyline points="'.$line.'" class="pk-line"/></svg>';
    }

    /** One look for every status: an outlined pill with a dot, coloured by meaning (ok, warn, hot, info, mute). */
    public static function pill(string $label, string $tone = 'mute'): string
    {
        return '<span class="st st-'.$tone.'">'.e($label).'</span>';
    }

    /** What an entry of the activity log looks like: an icon name and a colour tone. @return array{0: string, 1: string} */
    public static function actionLook(string $action): array
    {
        return match ($action) {
            'business_created' => ['building', 'ok'], 'plan_changed' => ['plan', 'info'], 'limits_changed' => ['sliders', 'info'],
            'password_set' => ['key', 'warn'], 'support_start' => ['eye', 'warn'], 'support_stop' => ['eye', 'mute'],
            'invite_made' => ['mail', 'info'], 'billing_changed' => ['coins', 'info'], 'reminder_sent' => ['mail', 'info'], 'data_exported' => ['download', 'info'], 'announcement' => ['mail', 'info'], 'paused' => ['pause', 'hot'], 'resumed' => ['play', 'ok'], default => ['history', 'mute'],
        };
    }
}
