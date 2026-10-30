<?php

namespace App\Channels;

/**
 * Reads Meta's webhook body and returns the customer messages in it.
 * Delivery receipts, echoes of our own messages and unsupported types are skipped.
 */
class WebhookParser
{
    /** @return list<InboundEvent> */
    public function parse(array $body): array
    {
        return match ($body['object'] ?? '') {
            'whatsapp_business_account' => $this->whatsapp($body),
            'page' => $this->messaging($body, 'messenger'),
            'instagram' => $this->messaging($body, 'instagram'),
            default => [],
        };
    }

    /**
     * What happened to messages we sent: sent, delivered, read, or failed with a reason (WhatsApp only).
     *
     * @return list<array{id: string, status: string, errors: array<int, array<string, mixed>>}>
     */
    public function statuses(array $body): array
    {
        if (($body['object'] ?? '') !== 'whatsapp_business_account') {
            return [];
        }

        $found = [];

        foreach ((array) ($body['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $s) {
                    if (! empty($s['id']) && ! empty($s['status'])) {
                        $found[] = ['id' => (string) $s['id'], 'status' => (string) $s['status'], 'errors' => (array) ($s['errors'] ?? [])];
                    }
                }
            }
        }

        return $found;
    }

    /** @return list<InboundEvent> */
    private function whatsapp(array $body): array
    {
        $events = [];

        foreach ((array) ($body['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);
                $phoneId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $names = [];
                foreach ((array) ($value['contacts'] ?? []) as $contact) {
                    $names[(string) ($contact['wa_id'] ?? '')] = $contact['profile']['name'] ?? null;
                }

                foreach ((array) ($value['messages'] ?? []) as $m) {
                    $from = (string) ($m['from'] ?? '');
                    $type = (string) ($m['type'] ?? '');
                    $text = '';
                    $option = null;
                    $media = null;

                    if ($type === 'text') {
                        $text = (string) ($m['text']['body'] ?? '');
                    } elseif ($type === 'interactive') {
                        $reply = $m['interactive']['button_reply'] ?? $m['interactive']['list_reply'] ?? [];
                        $option = isset($reply['id']) ? (string) $reply['id'] : null;
                        $text = (string) ($reply['title'] ?? '');
                    } elseif ($type === 'button') {
                        $text = (string) ($m['button']['text'] ?? '');
                    } elseif (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
                        $item = (array) ($m[$type] ?? []);
                        $media = ['type' => $type, 'id' => (string) ($item['id'] ?? ''), 'mime' => (string) ($item['mime_type'] ?? ''), 'caption' => (string) ($item['caption'] ?? ''), 'filename' => (string) ($item['filename'] ?? '')];
                        $text = $media['caption'];
                    } elseif ($type === 'location') {
                        $loc = (array) ($m['location'] ?? []);
                        $media = ['type' => 'location', 'lat' => $loc['latitude'] ?? null, 'lng' => $loc['longitude'] ?? null, 'name' => (string) ($loc['name'] ?? ''), 'address' => (string) ($loc['address'] ?? '')];
                    } else {
                        continue; // reactions, contacts, orders ... not handled
                    }

                    if ($phoneId === '' || $from === '' || empty($m['id'])) {
                        continue;
                    }

                    $events[] = new InboundEvent('whatsapp', $phoneId, $from, (string) $m['id'], $text, $option, $names[$from] ?? null, isset($m['timestamp']) ? (int) $m['timestamp'] : null, $media);
                }
            }
        }

        return $events;
    }

    /** @return list<InboundEvent> */
    private function messaging(array $body, string $type): array
    {
        $events = [];

        foreach ((array) ($body['entry'] ?? []) as $entry) {
            $accountId = (string) ($entry['id'] ?? '');

            foreach ((array) ($entry['messaging'] ?? []) as $m) {
                $from = (string) ($m['sender']['id'] ?? '');
                $message = (array) ($m['message'] ?? []);

                if ($accountId === '' || $from === '' || $from === $accountId || ! empty($message['is_echo'])) {
                    continue;
                }

                if (isset($m['postback'])) {
                    $option = (string) ($m['postback']['payload'] ?? '');
                    $events[] = new InboundEvent($type, $accountId, $from, 'pb_'.$from.'_'.($m['timestamp'] ?? '').'_'.$option, (string) ($m['postback']['title'] ?? ''), $option !== '' ? $option : null, null, $this->seconds($m['timestamp'] ?? null));

                    continue;
                }

                $media = $this->attachment($message);

                if (empty($message['mid']) || (! isset($message['text']) && ! isset($message['quick_reply']) && $media === null)) {
                    continue; // reactions, read marks ... not handled
                }

                $option = isset($message['quick_reply']['payload']) ? (string) $message['quick_reply']['payload'] : null;
                $events[] = new InboundEvent($type, $accountId, $from, (string) $message['mid'], (string) ($message['text'] ?? ''), $option, null, $this->seconds($m['timestamp'] ?? null), $media);
            }
        }

        return $events;
    }

    /** The first attachment of a Messenger or Instagram message (photo, video, voice note, file or location pin). */
    private function attachment(array $message): ?array
    {
        $item = (array) (((array) ($message['attachments'] ?? []))[0] ?? []);
        $type = (string) ($item['type'] ?? '');

        if ($type === '') {
            return null;
        }

        $payload = (array) ($item['payload'] ?? []);

        if ($type === 'location') {
            $c = (array) ($payload['coordinates'] ?? []);

            return ['type' => 'location', 'lat' => $c['lat'] ?? null, 'lng' => $c['long'] ?? null, 'name' => (string) ($item['title'] ?? ''), 'address' => ''];
        }

        $type = match ($type) {
            'image' => 'image', 'video' => 'video', 'audio' => 'audio', 'file' => 'document', default => 'other',
        };

        return ['type' => $type, 'url' => (string) ($payload['url'] ?? ''), 'mime' => '', 'caption' => (string) ($message['text'] ?? ''), 'filename' => ''];
    }

    private function seconds(mixed $ms): ?int
    {
        return $ms ? (int) floor(((int) $ms) / 1000) : null;
    }
}
