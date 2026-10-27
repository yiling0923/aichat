<?php

namespace App\Booking;

use App\Models\Business;

/**
 * Booking rules of a business (settings.booking), with safe defaults.
 */
class BookingRules
{
    public const DEFAULTS = [
        'slot_step_min' => 30,     // start times are offered every N minutes
        'buffer_min' => 10,        // gap kept after every appointment
        'min_notice_hours' => 2,   // no bookings sooner than this
        'max_days_ahead' => 14,
        'cancel_hours' => 24,      // customers may cancel up to N hours before
        'days_offered' => 5,       // days shown at a time in the chat
    ];

    /** @return array<string, int> */
    public static function for(Business $business): array
    {
        $saved = (array) ($business->settings['booking'] ?? []);

        return array_map('intval', array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS)));
    }
}
