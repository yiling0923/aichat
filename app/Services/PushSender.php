<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Sends a Web Push alert to a person's phones and computers. Never throws: an alert problem must not break a chat.
 * Subscriptions the phone has dropped (404 or 410) are removed.
 */
class PushSender
{
    public static function configured(): bool
    {
        return (string) config('aichat.push.public') !== '' && (string) config('aichat.push.private') !== '';
    }

    /**
     * @param  list<int>  $userIds
     * @return int how many devices were reached
     */
    public function toUsers(array $userIds, string $title, string $body, string $url, string $tag): int
    {
        if (! self::configured() || $userIds === []) {
            return 0;
        }

        try {
            $subs = PushSubscription::query()->whereIn('user_id', $userIds)->get();
            if ($subs->isEmpty()) {
                return 0;
            }

            $webPush = $this->client();
            $payload = json_encode(['title' => Str::limit($title, 90, '...'), 'body' => Str::limit($body, 160, '...'), 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE);

            foreach ($subs as $sub) {
                $webPush->queueNotification(Subscription::create(['endpoint' => $sub->endpoint, 'publicKey' => $sub->p256dh, 'authToken' => $sub->auth, 'contentEncoding' => 'aes128gcm']), $payload);
            }

            $reached = 0;
            foreach ($webPush->flush() as $report) {
                $hash = hash('sha256', $report->getEndpoint());
                if ($report->isSuccess()) {
                    $reached++;
                    PushSubscription::query()->where('endpoint_hash', $hash)->update(['last_sent_at' => now()]);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', $hash)->delete();
                }
            }

            return $reached;
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // the log itself may be locked
            }

            return 0;
        }
    }

    protected function client(): WebPush
    {
        return new WebPush(['VAPID' => [
            'subject' => (string) config('aichat.push.subject'),
            'publicKey' => (string) config('aichat.push.public'),
            'privateKey' => (string) config('aichat.push.private'),
        ]], ['TTL' => 3600, 'urgency' => 'high'], 6);
    }
}
