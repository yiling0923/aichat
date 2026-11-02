<?php

namespace App\Support;

/** Plain words for the lines in the platform owner's activity log. */
class PlatformActions
{
    private const LABELS = [
        'business_created' => 'Business created',
        'plan_changed' => 'Plan changed',
        'limits_changed' => 'Limits changed',
        'password_set' => 'Password set',
        'support_start' => 'Support view started',
        'support_stop' => 'Support view ended',
        'invite_made' => 'Invitation made',
        'billing_changed' => 'Billing settings changed',
        'reminder_sent' => 'Reminder emailed',
        'data_exported' => 'Data downloaded',
        'announcement' => 'Announcement',
        'paused' => 'Paused',
        'resumed' => 'Resumed',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}
