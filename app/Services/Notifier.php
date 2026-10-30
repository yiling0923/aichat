<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puts a notification on the bell of the right people. It never throws: a notification problem must not break a chat.
 * Repeats about the same thing (more messages in one chat) update the unread one instead of piling up.
 */
class Notifier
{
    /**
     * @param  list<string>|null  $roles  who gets it (default: everyone active in the business)
     * @param  int|null  $userId  or just this one person
     */
    public function notify(Business $business, string $type, string $title, ?string $body = null, ?string $url = null, ?array $roles = null, ?int $userId = null): void
    {
        try {
            $users = User::query()->where('business_id', $business->id)->where('active', true)
                ->when($userId !== null, fn ($q) => $q->whereKey($userId))
                ->when($roles !== null, fn ($q) => $q->whereIn('role', $roles))
                ->get(['id']);

            $pushTo = [];

            foreach ($users as $user) {
                $same = $url === null ? null : StaffNotification::query()->where('user_id', $user->id)->where('type', $type)->where('url', $url)->whereNull('read_at')->first();

                if ($same !== null) {
                    $same->forceFill(['title' => $title, 'body' => $body === null ? null : Str::limit($body, 140)])->save();
                    $same->touch();
                    $pushTo[] = $user->id;

                    continue;
                }

                StaffNotification::create(['business_id' => $business->id, 'user_id' => $user->id, 'type' => $type, 'title' => Str::limit($title, 120),
                    'body' => $body === null ? null : Str::limit($body, 140), 'url' => $url]);
                $pushTo[] = $user->id;
            }

            // The same alert on their phones and computers, after the page has been sent so nobody waits for it.
            if ($pushTo !== [] && PushSender::configured()) {
                $target = $url ?? route('inbox');
                dispatch(fn () => app(PushSender::class)->toUsers($pushTo, $title, (string) $body, $target, $type.'-'.md5($target)))->afterResponse();
            }
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // the log itself may be locked; a notification problem must never break a chat
            }
        }
    }

    /** A customer who writes for the first time: tell the team, even though the bot is answering (switch off in Settings). */
    public function newChat(Conversation $conversation, string $text): void
    {
        $business = $conversation->business;

        if ($business === null || ($business->settings['alert_new_chat'] ?? true) === false || $conversation->channel === 'preview') {
            return;
        }

        $this->notify($business, 'message', 'New chat from '.self::chatName($conversation), $text !== '' ? $text : null, route('inbox', ['c' => $conversation->id]));
    }

    public static function chatName(Conversation $c): string
    {
        return $c->contact_name ?: ($c->customer?->name ?: 'Visitor '.$c->id);
    }
}
