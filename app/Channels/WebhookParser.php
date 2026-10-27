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

                    if ($type === 'text') {
                        $text = (string) ($m['text']['body'] ?? '');
                    } elseif ($type === 'interactive') {
                        $reply = $m['interactive']['button_reply'] ?? $m['interactive']['list_reply'] ?? [];
                        $option = isset($reply['id']) ? (string) $reply['id'] : null;
                        $text = (string) ($reply['title'] ?? '');
                    } elseif ($type === 'button') {
                        $text = (string) ($m['button']['text'] ?? '');
                    } else {
                        continue; // images, audio, location ... not handled yet
                    }

                    if ($phoneId === '' || $from === '' || empty($m['id'])) {
                        continue;
                    }

                    $events[] = new InboundEvent('whatsapp', $phoneId, $from, (string) $m['id'], $text, $option, $names[$from] ?? null, isset($m['timestamp']) ? (int) $m['timestamp'] : null);
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

                if (empty($message['mid']) || (! isset($message['text']) && ! isset($message['quick_reply']))) {
                    continue; // attachments, reactions ... not handled yet
                }

                $option = isset($message['quick_reply']['payload']) ? (string) $message['quick_reply']['payload'] : null;
                $events[] = new InboundEvent($type, $accountId, $from, (string) $message['mid'], (string) ($message['text'] ?? ''), $option, null, $this->seconds($m['timestamp'] ?? null));
            }
        }

        return $events;
    }

    private function seconds(mixed $ms): ?int
    {
        return $ms ? (int) floor(((int) $ms) / 1000) : null;
    }
}
