<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\PushSubscription;
use App\Models\QuickReply;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The first-time setup guide: a short list of things a new owner does, each one ticked by itself when the app can see it is done
 * (or by hand with "Mark as done"). Used by the guide page, the side menu badge and the Today card.
 */
class SetupGuide
{
    /** @return list<array{key: string, title: string, why: string, minutes: int, url: string, cta: string, done: bool, auto: bool, optional: bool}> */
    public function steps(Business $business, User $user): array
    {
        return app(CurrentBusiness::class)->runAs($business, function () use ($business, $user) {
            $manual = (array) (($business->settings ?? [])['guide_done'] ?? []);
            $settings = (array) $business->settings;

            $defs = [
                ['business', 'Check your business details', 'Your name, time zone and address are used in messages, bookings and reports.', 2, route('settings'), 'Open details',
                    ! empty($settings['address']), false],
                ['services', 'Add your services and prices', 'Customers pick a service when they book, and the bot can answer price questions.', 5, route('booking.setup'), 'Add services',
                    Service::query()->exists(), false],
                ['hours', 'Set your opening hours', 'The bot only offers times when you are open, and tells customers your hours.', 2, route('booking.setup'), 'Set hours',
                    ! empty($settings['hours']), false],
                ['menu', 'Look at your chat menu', 'This is what customers see first: language, prices, booking, opening hours. Change the words to suit you, then Publish.', 5, route('menu'), 'Open menu builder',
                    false, false],
                ['chat', 'Try it as a customer', 'Open your chat link, choose a language, ask a price, make a booking. Then find that chat in your Inbox.', 3, route('chat.show', $business->slug), 'Open my chat',
                    Conversation::query()->where('channel', '!=', 'preview')->exists(), false],
                ['live', 'Go live', 'Confirm everything works and switch your assistant on for real customers.', 1, route('setup'), 'Go live',
                    $business->setup_done_at !== null || $business->plan === null, false],
                ['team', 'Add your team', 'Staff get their own sign-in and alerts. Owners and managers can do more than staff.', 3, route('settings', ['tab' => 'team']), 'Add people',
                    User::query()->where('business_id', $business->id)->where('active', true)->count() > 1, true],
                ['channel', 'Connect WhatsApp, Messenger or Instagram', 'Your website chat already works. Connect more places so customers can reach you where they already are.', 10, route('channels'), 'Open channels',
                    Channel::query()->where('active', true)->exists(), true],
                ['replies', 'Write a few saved replies', 'One-click answers for the questions your team types again and again. The Inbox even suggests the best one.', 5, route('library'), 'Open saved replies',
                    QuickReply::query()->exists(), true],
                ['alerts', 'Turn on phone alerts', 'Get a pop-up for new chats and bookings even when the page is closed.', 1, route('account').'#phone-alerts', 'Turn on alerts',
                    PushSubscription::query()->where('user_id', $user->id)->exists() || ! empty($settings['alert_email']), true],
            ];

            return array_map(fn ($d) => [
                'key' => $d[0], 'title' => $d[1], 'why' => $d[2], 'minutes' => $d[3], 'url' => $d[4], 'cta' => $d[5],
                'done' => (bool) ($d[6] || ! empty($manual[$d[0]])), 'auto' => (bool) $d[6], 'optional' => $d[7],
            ], $defs);
        });
    }

    /** @return array{done: int, total: int, minutes: int, complete: bool} counts the required steps only */
    public function progress(Business $business, User $user): array
    {
        $required = array_filter($this->steps($business, $user), fn ($s) => ! $s['optional']);
        $done = count(array_filter($required, fn ($s) => $s['done']));

        return [
            'done' => $done, 'total' => count($required),
            'minutes' => (int) array_sum(array_map(fn ($s) => $s['done'] ? 0 : $s['minutes'], $required)),
            'complete' => $done === count($required),
        ];
    }

    /** The side menu asks on every page, so the answer is kept for half a minute. @return array{done: int, total: int, minutes: int, complete: bool} */
    public function cached(Business $business, User $user): array
    {
        return Cache::remember('guide-progress-'.$business->id.'-'.$user->id, 30, fn () => $this->progress($business, $user));
    }

    public static function forget(Business $business, User $user): void
    {
        Cache::forget('guide-progress-'.$business->id.'-'.$user->id);
    }

    /** @return list<string> */
    public function keys(Business $business, User $user): array
    {
        return array_column($this->steps($business, $user), 'key');
    }
}
