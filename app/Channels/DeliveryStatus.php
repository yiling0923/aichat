<?php

namespace App\Channels;

use App\Models\Message;
use App\Services\Notifier;

/**
 * WhatsApp says "accepted" when we send, and only later tells us whether the phone really got the message (sent, delivered,
 * read) or why not. This writes that later news onto the message, so the Inbox shows the real reason when something never arrived.
 */
class DeliveryStatus
{
    /** @param array{id: string, status: string, errors: array<int, array<string, mixed>>} $status */
    public function apply(array $status): void
    {
        $message = Message::query()->withoutGlobalScopes()->where('direction', 'out')->where('external_id', $status['id'])->first();

        if ($message === null) {
            return;
        }

        $payload = (array) $message->payload;

        if ($status['status'] === 'failed') {
            $payload['delivery'] = 'failed';
            $payload['delivery_error'] = $this->explain((array) ($status['errors'][0] ?? []), $message);
            $message->forceFill(['payload' => $payload])->save();

            $conversation = $message->conversation()->withoutGlobalScopes()->first();
            if ($conversation !== null && $conversation->business !== null && ! $message->is_private) {
                app(Notifier::class)->notify($conversation->business, 'message', 'Message to '.Notifier::chatName($conversation).' was not delivered', (string) $payload['delivery_error'], route('inbox', ['c' => $conversation->id]));
            }

            return;
        }

        if (in_array($status['status'], ['sent', 'delivered', 'read'], true) && ($payload['delivery'] ?? '') !== 'failed') {
            $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3];
            if (($rank[$status['status']] ?? 0) > ($rank[$payload['receipt'] ?? ''] ?? 0)) {
                $payload['receipt'] = $status['status'];
                $message->forceFill(['payload' => $payload])->save();
            }
        }
    }

    /** @param array<string, mixed> $error */
    private function explain(array $error, Message $message): string
    {
        $code = (int) ($error['code'] ?? 0);
        $detail = trim((string) ($error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? ''));
        $isFile = ! empty($message->payload['media']);

        $text = match (true) {
            $code === 131053 || ($isFile && in_array($code, [131052, 131051], true)) => 'WhatsApp could not download the file from your server. The tunnel must be running and APP_URL in .env must be the current tunnel address, and that address must open without a warning page.',
            $code === 131047 => 'More than 24 hours have passed since the customer last wrote, so only an approved template can be sent.',
            $code === 131030 => 'That phone number is not in the allowed list of your WhatsApp test number. Add it in Meta, API Setup, To.',
            $code === 131026 => 'WhatsApp could not deliver the message. The phone may be switched off, not on WhatsApp, or the customer has not accepted WhatsApp\'s latest terms.',
            $code === 190 || $code === 131005 || str_contains(strtolower($detail), 'token') || str_contains(strtolower($detail), 'authentication') => 'The access token for this channel has probably expired. Open Channels, press Fix connection, paste a new access token and save.',
            default => '',
        };

        $base = $detail !== '' ? $detail.($code ? ' (code '.$code.')' : '') : ($code ? 'Error code '.$code : 'The message was not delivered.');

        return $text !== '' ? $base.'. '.$text : $base;
    }
}
