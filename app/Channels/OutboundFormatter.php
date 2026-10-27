<?php

namespace App\Channels;

use App\Models\Channel;
use App\Models\Message;

/**
 * Turns a bot or staff message into what each channel accepts.
 * WhatsApp: up to 3 options become reply buttons, up to 10 become a list. Messenger and Instagram: quick replies.
 */
class OutboundFormatter
{
    /** @return array{path: string, json: array<string, mixed>} */
    public function format(Channel $channel, string $to, Message $message): array
    {
        $options = array_values($message->payload['options'] ?? []);
        $text = (string) $message->body;

        return $channel->type === 'whatsapp'
            ? $this->whatsapp($channel, $to, $text, $options)
            : $this->quickReplies($channel, $to, $text, $options);
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @return array{path: string, json: array<string, mixed>}
     */
    private function whatsapp(Channel $channel, string $to, string $text, array $options): array
    {
        $base = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to];
        $path = $channel->external_id.'/messages';

        if ($options === []) {
            return ['path' => $path, 'json' => $base + ['type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000), 'preview_url' => true]]];
        }

        $body = ['text' => mb_substr($text !== '' ? $text : '...', 0, 1024)];

        if (count($options) <= 3) {
            $buttons = array_map(fn (array $o) => ['type' => 'reply', 'reply' => ['id' => mb_substr($o['id'], 0, 256), 'title' => $this->cut($o['label'], 20)]], $options);

            return ['path' => $path, 'json' => $base + ['type' => 'interactive', 'interactive' => ['type' => 'button', 'body' => $body, 'action' => ['buttons' => $buttons]]]];
        }

        $rows = array_map(fn (array $o) => ['id' => mb_substr($o['id'], 0, 200), 'title' => $this->cut($o['label'], 24)], array_slice($options, 0, 10));

        return ['path' => $path, 'json' => $base + ['type' => 'interactive', 'interactive' => [
            'type' => 'list',
            'body' => $body,
            'action' => ['button' => 'Choose', 'sections' => [['title' => 'Options', 'rows' => $rows]]],
        ]]];
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @return array{path: string, json: array<string, mixed>}
     */
    private function quickReplies(Channel $channel, string $to, string $text, array $options): array
    {
        $message = ['text' => mb_substr($text !== '' ? $text : '...', 0, 1900)];

        if ($options !== []) {
            $message['quick_replies'] = array_map(fn (array $o) => [
                'content_type' => 'text',
                'title' => $this->cut($o['label'], 20),
                'payload' => mb_substr($o['id'], 0, 1000),
            ], array_slice($options, 0, 13));
        }

        return [
            'path' => $channel->external_id.'/messages',
            'json' => ['recipient' => ['id' => $to], 'messaging_type' => 'RESPONSE', 'message' => $message],
        ];
    }

    private function cut(string $label, int $max): string
    {
        $label = trim($label);

        return mb_strlen($label) <= $max ? $label : rtrim(mb_substr($label, 0, $max - 1)).'…';
    }
}
