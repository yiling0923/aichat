<?php

namespace App\Bot;

use App\Models\Business;
use Carbon\CarbonInterface;

/**
 * Is the business open? Hours live in the business settings:
 *   hours: { mon: ["10:00","19:00"], ... }  (a missing day means closed)
 *   closed_dates: ["2026-12-25"]
 * A business without hours is treated as always open.
 */
class BusinessHours
{
    public static function isOpen(Business $business, ?CarbonInterface $at = null): bool
    {
        $hours = $business->settings['hours'] ?? null;

        if (! is_array($hours) || $hours === []) {
            return true;
        }

        $local = ($at ?? now())->copy()->setTimezone($business->timezone ?: 'UTC');

        if (in_array($local->format('Y-m-d'), (array) ($business->settings['closed_dates'] ?? []), true)) {
            return false;
        }

        $today = $hours[strtolower($local->format('D'))] ?? null;

        if (! is_array($today) || count($today) !== 2) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= $today[0] && $time < $today[1];
    }
}
