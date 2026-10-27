<?php

namespace App\Bot;

use App\Models\BotEvent;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\HandoverService;
use App\Services\LeadService;

/**
 * The guided menu bot. It shows a step, waits for a tap (or a typed number,
 * label or keyword), and moves on. Where the customer is lives in
 * conversations.state: {node, awaiting: options|ask|null, data: {...}, misses: n}.
 */
class MenuBot
{
    private const MAX_CHAIN = 12;

    public function __construct(private LeadService $leads, private HandoverService $handovers, private BookingStep $booking) {}

    /**
     * @return list<\App\Models\Message>
     */
    public function respond(Conversation $conversation, Flow $flow, string $text, ?string $optionId = null): array
    {
        $state = $conversation->state ?? [];
        $state += ['node' => null, 'awaiting' => null, 'data' => [], 'misses' => 0];
        $out = [];
        $language = $conversation->language ?? $flow->defaultLanguage();
        $normalized = Text::normalize($text);

        if ($optionId === null && Text::isCommand($normalized, config('aichat.keywords.opt_out'))) {
            $this->optOut($conversation);
            $out[] = $conversation->addMessage('out', 'bot', Strings::get('opt_out', $language));

            return $this->finish($conversation, $state, $out);
        }

        if ($optionId === null && Text::isCommand($normalized, config('aichat.keywords.human'))) {
            return $this->handover($conversation, $state, $out, 'Customer asked for a person', $language);
        }

        if ($optionId === null && Text::isCommand($normalized, config('aichat.keywords.menu')) && $state['node'] !== null) {
            $state['misses'] = 0;

            return $this->enter($conversation, $flow, $state, $conversation->language ? $flow->mainNodeId() : $flow->start(), $out);
        }

        $node = $state['node'] !== null ? $flow->node($state['node']) : null;

        if ($node === null) {
            return $this->enter($conversation, $flow, $state, $flow->start(), $out);
        }

        if ($state['awaiting'] === 'options') {
            $option = $this->match($flow, $node, $text, $optionId);

            if ($option === null) {
                return $this->miss($conversation, $flow, $state, $out, $language, 'sorry_choose');
            }

            $state['misses'] = 0;
            $this->event($conversation, 'option', $state['node'], $option['id'] ?? null);

            if (! empty($option['language'])) {
                $conversation->forceFill(['language' => $option['language']])->save();
            }
            foreach ((array) ($option['set'] ?? []) as $field => $value) {
                $state['data'][$field] = $value;
            }
            if (($option['action'] ?? '') === 'handover') {
                return $this->handover($conversation, $state, $out, (string) ($option['reason'] ?? 'Customer chose staff'), $conversation->language ?? $language);
            }

            return $this->enter($conversation, $flow, $state, (string) ($option['goto'] ?? $flow->mainNodeId()), $out);
        }

        if ($state['awaiting'] === 'booking') {
            $result = $this->booking->handle($conversation, $state, $text, $optionId, $conversation->language ?? $language);

            return $this->afterBooking($conversation, $flow, $state, $node, $out, $result, $language);
        }

        if ($state['awaiting'] === 'my_booking') {
            $result = $this->booking->handleSummary($conversation, $text, $optionId, $conversation->language ?? $language);

            if ($result['status'] === 'retry') {
                return $this->finish($conversation, $state, array_merge($out, $result['out']));
            }

            return $this->afterBooking($conversation, $flow, $state, $node, $out, $result, $language);
        }

        if ($state['awaiting'] === 'ask') {
            $value = trim($text);
            $field = (string) ($node['field'] ?? 'note');

            if (($node['validate'] ?? '') === 'phone' || $field === 'phone') {
                $phone = $this->leads->normalizePhone($value);
                $digits = $phone === null ? 0 : strlen(ltrim($phone, '+'));
                if ($digits < 7 || $digits > 15) {
                    return $this->miss($conversation, $flow, $state, $out, $language, 'invalid_phone', false);
                }
                $value = $phone;
            } elseif ($value === '') {
                return $this->miss($conversation, $flow, $state, $out, $language, 'sorry_ask', false);
            }

            $state['misses'] = 0;
            $state['data'][$field] = mb_substr($value, 0, 200);

            return $this->enter($conversation, $flow, $state, (string) ($node['goto'] ?? $flow->mainNodeId()), $out);
        }

        return $this->enter($conversation, $flow, $state, $flow->mainNodeId(), $out);
    }

    /**
     * Show a step and keep going through steps that do not need an answer.
     *
     * @param  array<string, mixed>  $state
     * @param  list<\App\Models\Message>  $out
     * @return list<\App\Models\Message>
     */
    private function enter(Conversation $conversation, Flow $flow, array $state, string $nodeId, array $out): array
    {
        for ($i = 0; $i < self::MAX_CHAIN; $i++) {
            $node = $flow->node($nodeId);
            $language = $conversation->language ?? $flow->defaultLanguage();

            if ($node === null) {
                return $this->handover($conversation, $state, $out, 'Menu step missing: '.$nodeId, $language, 'fallback_missing');
            }

            $state['node'] = $nodeId;
            $this->event($conversation, 'node', $nodeId);
            $type = $node['type'] ?? 'message';
            $text = Text::fill($flow->localize($node['text'] ?? [], $language), $this->placeholders($conversation, $state));

            if (! empty($node['link']['url'])) {
                $label = $flow->localize($node['link']['label'] ?? [], $language);
                $text .= "\n".($label !== '' ? $label.': ' : '').$node['link']['url'];
            }

            if ($type === 'options') {
                $options = array_map(fn (array $o) => [
                    'id' => (string) ($o['id'] ?? ''),
                    'label' => $flow->localize($o['label'] ?? [], $language),
                ], array_values($node['options'] ?? []));
                $out[] = $conversation->addMessage('out', 'bot', $text, ['options' => $options]);
                $state['awaiting'] = 'options';

                return $this->finish($conversation, $state, $out);
            }

            if ($type === 'booking') {
                $state['data'] = array_merge($state['data'], ['name' => $state['data']['name'] ?? $conversation->customer?->name, 'phone' => $state['data']['phone'] ?? $conversation->customer?->phone]);
                $result = $this->booking->begin($conversation, $state, $language);
                $state['awaiting'] = 'booking';

                return $this->afterBooking($conversation, $flow, $state, $node, $out, $result, $language);
            }

            if ($type === 'my_booking') {
                $result = $this->booking->summary($conversation, $language);

                if ($result === null) {
                    $out[] = $conversation->addMessage('out', 'bot', Strings::get('booking_none_upcoming', $language));
                    $nodeId = (string) ($node['goto'] ?? $flow->mainNodeId());

                    continue;
                }

                $state['awaiting'] = 'my_booking';

                return $this->finish($conversation, $state, array_merge($out, $result['out']));
            }

            if ($type === 'ask') {
                $out[] = $conversation->addMessage('out', 'bot', $text);
                $state['awaiting'] = 'ask';

                return $this->finish($conversation, $state, $out);
            }

            if ($type === 'handover') {
                if ($text !== '') {
                    $out[] = $conversation->addMessage('out', 'bot', $text);
                }

                return $this->handover($conversation, $state, $out, (string) ($node['reason'] ?? 'Menu handover step'), $language, null);
            }

            if ($type === 'save_lead') {
                $this->leads->capture($conversation, $state['data'], (string) ($node['score'] ?? 'warm'), (bool) ($node['notify'] ?? true));
                $text = Text::fill($flow->localize($node['text'] ?? [], $language), $this->placeholders($conversation, $state));
            }

            if ($text !== '') {
                $out[] = $conversation->addMessage('out', 'bot', $text);
            }

            $nodeId = (string) ($node['goto'] ?? $flow->mainNodeId());
        }

        return $this->handover($conversation, $state, $out, 'Menu loop detected', $conversation->language ?? $flow->defaultLanguage(), 'fallback_missing');
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function match(Flow $flow, array $node, string $text, ?string $optionId): ?array
    {
        $options = array_values($node['options'] ?? []);

        if ($optionId !== null) {
            foreach ($options as $o) {
                if (($o['id'] ?? null) === $optionId) {
                    return $o;
                }
            }

            return null;
        }

        $normalized = Text::normalize($text);

        if ($normalized === '') {
            return null;
        }

        if (ctype_digit($normalized) && isset($options[(int) $normalized - 1])) {
            return $options[(int) $normalized - 1];
        }

        foreach ($options as $o) {
            foreach ((array) ($o['label'] ?? []) as $label) {
                if (Text::normalize((string) $label) === $normalized) {
                    return $o;
                }
            }
        }

        // The option whose matching keyword is longest wins ("cancel booking" beats "booking").
        // A tie between different options means we cannot tell, so we ask again.
        $best = 0;
        $found = [];
        foreach ($options as $o) {
            $score = 0;
            foreach ((array) ($o['keywords'] ?? []) as $keyword) {
                if (Text::containsKeyword($normalized, (string) $keyword)) {
                    $score = max($score, mb_strlen(Text::normalize((string) $keyword)));
                }
            }
            if ($score > 0 && $score >= $best) {
                $found = $score > $best ? [$o] : array_merge($found, [$o]);
                $best = $score;
            }
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<\App\Models\Message>  $out
     * @return list<\App\Models\Message>
     */
    private function miss(Conversation $conversation, Flow $flow, array $state, array $out, string $language, string $key, bool $resend = true): array
    {
        $state['misses'] = ((int) $state['misses']) + 1;

        if ($state['misses'] > (int) config('aichat.max_misses')) {
            return $this->handover($conversation, $state, $out, 'Bot could not understand the customer', $language);
        }

        $out[] = $conversation->addMessage('out', 'bot', Strings::get($key, $language));

        if ($resend) {
            $node = $flow->node((string) $state['node']);
            $options = array_map(fn (array $o) => [
                'id' => (string) ($o['id'] ?? ''),
                'label' => $flow->localize($o['label'] ?? [], $language),
            ], array_values($node['options'] ?? []));
            $text = Text::fill($flow->localize($node['text'] ?? [], $language), $this->placeholders($conversation, $state));
            $out[] = $conversation->addMessage('out', 'bot', $text, ['options' => $options]);
        }

        return $this->finish($conversation, $state, $out);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<\App\Models\Message>  $out
     * @return list<\App\Models\Message>
     */
    private function handover(Conversation $conversation, array $state, array $out, string $reason, string $language, ?string $message = 'handover'): array
    {
        // $message: 'handover' picks the open or closed wording, null says nothing (already said).
        if ($message === 'handover') {
            $message = BusinessHours::isOpen($conversation->business) ? 'handover' : 'handover_closed';
        }

        if ($message !== null) {
            $out[] = $conversation->addMessage('out', 'bot', Strings::get($message, $language));
        }

        $state['awaiting'] = null;
        $state['misses'] = 0;
        $conversation->forceFill(['state' => $state])->save();
        $this->handovers->start($conversation, $reason, $state['data'] ?? []);

        return $out;
    }

    /**
     * Carry on after a booking step: keep waiting, move to the next menu step, or hand over.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $node
     * @param  list<\App\Models\Message>  $out
     * @param  array{out: list<\App\Models\Message>, status: string, reason: ?string}  $result
     * @return list<\App\Models\Message>
     */
    private function afterBooking(Conversation $conversation, Flow $flow, array $state, array $node, array $out, array $result, string $language): array
    {
        $out = array_merge($out, $result['out']);
        $language = $conversation->language ?? $language;

        if ($result['status'] === 'handover') {
            // The booking step already explained when it had something to say.
            return $this->handover($conversation, $state, $out, (string) $result['reason'], $language, $result['out'] === [] ? 'handover' : null);
        }

        if ($result['status'] === 'done') {
            $state['awaiting'] = null;
            unset($state['booking']);

            return $this->enter($conversation, $flow, $state, (string) ($node['goto'] ?? $flow->mainNodeId()), $out);
        }

        $state['awaiting'] = $state['awaiting'] === 'my_booking' ? 'my_booking' : 'booking';

        return $this->finish($conversation, $state, $out);
    }

    private function optOut(Conversation $conversation): void
    {
        $conversation->forceFill(['opted_out_at' => now()])->save();

        if ($conversation->customer_id) {
            Customer::query()->whereKey($conversation->customer_id)->update(['opted_out_at' => now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<\App\Models\Message>  $out
     * @return list<\App\Models\Message>
     */
    private function finish(Conversation $conversation, array $state, array $out): array
    {
        $conversation->forceFill(['state' => $state])->save();

        return $out;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function placeholders(Conversation $conversation, array $state): array
    {
        return ($state['data'] ?? []) + ['business' => $conversation->business->name];
    }

    private function event(Conversation $conversation, string $type, ?string $node, ?string $optionId = null): void
    {
        BotEvent::query()->create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'node' => $node,
            'option_id' => $optionId,
            'created_at' => now(),
        ]);
    }
}
