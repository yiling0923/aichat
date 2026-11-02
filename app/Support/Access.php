<?php

namespace App\Support;

/**
 * The areas of the staff area an owner can switch on or off for each person.
 * Owners always have everything. Channels (tokens), billing, backups, settings and the team stay owner-only.
 */
class Access
{
    /** @var array<string, array{0: string, 1: string}> key => [name, what it allows] */
    public const AREAS = [
        'inbox' => ['Inbox and replying', 'Read customer chats and answer them.'],
        'schedule' => ['Schedule', 'See bookings, move or cancel them, mark them Done or No-show.'],
        'customers' => ['Customers', 'See customer details, notes and history.'],
        'customers_admin' => ['Customer tools', 'Import, export, merge and erase customers.'],
        'tasks' => ['Tasks', 'See and complete tasks.'],
        'orders' => ['Orders', 'See and edit orders and payments.'],
        'reports' => ['Reports', 'See the sales and marketing numbers.'],
        'marketing' => ['Marketing', 'Automations, broadcasts and message templates.'],
        'ai' => ['AI assistant', 'Teach the bot extra answers and set how the AI helps.'],
        'library' => ['Saved replies (edit)', 'Add and change the saved replies. Everyone can still use them.'],
        'tags' => ['Tags', 'Create and organise tags.'],
        'menu' => ['Menu builder', 'Change what the bot says and its buttons.'],
        'booking' => ['Booking setup', 'Opening hours, services, practitioners and Google Calendar.'],
    ];

    /** @var array<string, list<string>> what each role gets when the owner has not chosen anything */
    public const ROLE_DEFAULTS = [
        'staff' => ['inbox', 'schedule', 'customers', 'tasks', 'orders', 'reports'],
        'manager' => ['inbox', 'schedule', 'customers', 'customers_admin', 'tasks', 'orders', 'reports', 'marketing', 'ai', 'library', 'tags'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::AREAS);
    }

    /** @return list<string> */
    public static function forRole(string $role): array
    {
        return $role === 'owner' ? self::keys() : (self::ROLE_DEFAULTS[$role] ?? []);
    }

    /** Only known areas, no repeats. @param  array<mixed>  $list @return list<string> */
    public static function clean(array $list): array
    {
        return array_values(array_intersect(self::keys(), array_map('strval', $list)));
    }
}
