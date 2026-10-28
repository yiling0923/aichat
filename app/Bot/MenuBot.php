<?php

namespace App\Bot;

use App\Ai\AiAssistant;
use App\Marketing\Outreach;
use App\Marketing\Surveys;
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

    public function __construct(private LeadService $leads, private HandoverService $handovers, private BookingStep $booking, private Surveys $surveys, private AiAssistant $ai) {}

    /**
     * @return list<\App\Models\Message>
     */
    public function respond(Conversation $conversation, Flow $flow, string $text, ?string $optionId = null): array
    {
        $state = $conversation->state ?? [];
        $state += ['node' => null, 'awaiting' => null, 'data' => [], 'misses' => 0, 'history' => []];
        $out = [];
        $language = $conversation->language ?? $flow->defaultLanguage();
        $normalized = Text::normalize($text);

        // An open satisfaction survey: a 1 to 5 answer is recorded, anything else just carries on as normal.
        if (! empty($state['survey'])) {
            $answer = $this->surveys->answer($conversation, (int) $state['survey'], $text, $optionId, $conversation->language ?? $language);
            unset($state['survey']);

            if ($answer !== null) {
                $state['history'] = [];

                return $this->enter($conversation, $flow, $state, $flow->mainNodeId(), array_merge($out, $answer));
            }
        }

        // Buttons sent by automatic messages ("Book now") jump straight to booking.
        if (in_array($optionId, ['__book', '__menu'], true)) {
            $target = $flow->mainNodeId();
            if ($optionId === '__book') {
                foreach ($flow->nodes() as $id => $n) {
                    if (($n['type'] ?? '') === 'booking') {
                        $target = (string) $id;
                        break;
                    }
                }
            }
            $state['history'] = [];
            $state['misses'] = 0;

            return $this->enter($conversation, $flow, $state, $target, $out);
        }

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
            $state['history'] = [];

            return $this->enter($conversation, $flow, $state, $conversation->language ? $flow->mainNodeId() : $flow->start(), $out);
        }

        $node = $state['node'] !== null ? $flow->node($state['node']) : null;

        $typedBack = $optionId === null && in_array($state['awaiting'], ['options', 'my_booking'], true)
            && Text::isCommand($normalized, config('aichat.keywords.back'));

        if ($node !== null && ($optionId === '__back' || $typedBack)) {
            return $this->goBack($conversation, $flow, $state, $out);
        }

        if ($node === null) {
            return $this->enter($conversation, $flow, $state, $flow->start(), $out);
        }

        if ($state['awaiting'] === 'options') {
            $option = $this->match($flow, $node, $text, $optionId);

            if ($option === null) {
                // Free text the menu cannot match: in AI mode the assistant answers, otherwise the usual "choose one".
                if ($optionId === null && $this->aiShouldAnswer($conversation, $text)) {
                    $handled = $this->askAi($conversation, $flow, $state, $node, $out, $language);

                    if ($handled !== null) {
                        return $handled;
                    }
                }

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

            $state['history'][] = $state['node'];
            $state['history'] = array_slice($state['history'], -10);

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
                // Coming back to the step we left (for example after a message) should not offer Back to itself.
                while (! empty($state['history']) && end($state['history']) === $nodeId) {
                    array_pop($state['history']);
                }
                $out[] = $conversation->addMessage('out', 'bot', $text, ['options' => $this->optionList($flow, $node, $language, $state)]);
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
            $options = $this->optionList($flow, $node, $language, $state);
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

        if ($result['status'] === 'back') {
            return $this->goBack($conversation, $flow, $state, $out);
        }

        if ($result['status'] === 'done') {
            $state['awaiting'] = null;
            unset($state['booking']);

            return $this->enter($conversation, $flow, $state, (string) ($node['goto'] ?? $flow->mainNodeId()), $out);
        }

        $state['awaiting'] = $state['awaiting'] === 'my_booking' ? 'my_booking' : 'booking';

        return $this->finish($conversation, $state, $out);
    }

    private function aiShouldAnswer(Conversation $conversation, string $text): bool
    {
        $business = $conversation->business;
        $length = mb_strlen(trim($text));

        return $conversation->channel !== 'preview'
            && in_array($this->ai->mode($business), ['approve', 'auto'], true)
            && $this->ai->provider() !== null
            && $length >= 2 && $length <= 500
            && ! ctype_digit(trim($text));
    }

    /**
     * Let the AI answer typed text. Returns null when the AI has nothing usable, so the caller falls back to the menu.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $node
     * @param  list<\App\Models\Message>  $out
     * @return list<\App\Models\Message>|null
     */
    private function askAi(Conversation $conversation, Flow $flow, array $state, array $node, array $out, string $language): ?array
    {
        $answer = $this->ai->answer($conversation, 'reply');

        if ($answer === null) {
            return null;
        }

        $state['misses'] = 0;
        $language = $conversation->language ?? $language;
        $mode = $this->ai->mode($conversation->business);

        // Upset customer, or someone asking for a person: say sorry (or OK) and hand over.
        if ($answer->wantsHuman || in_array($answer->intent, ['complaint', 'handover'], true)) {
            $out[] = $conversation->addMessage('out', 'bot', $answer->text, ['ai' => true]);

            return $this->handover($conversation, $state, $out, 'AI: customer needs a person ('.$answer->intent.', '.$answer->mood.')', $language, null);
        }

        // Approval mode: staff see the drafted answer and decide. The customer is told we are checking.
        if ($mode === 'approve') {
            $this->ai->saveDraft($conversation, $answer);
            $out[] = $conversation->addMessage('out', 'bot', Strings::get('ai_checking', $language));

            return $this->handover($conversation, $state, $out, 'AI reply waiting for your approval', $language, null);
        }

        $text = $answer->text;
        $disclosed = \App\Models\Message::query()->where('conversation_id', $conversation->id)->where('sender_type', 'bot')->where('payload->ai', true)->exists();
        if (! $disclosed) {
            $text .= "\n".Strings::get('ai_disclosure', $language);
        }

        $options = $answer->intent === 'booking'
            ? array_merge(Outreach::bookNowButton($language), [['id' => '__menu', 'label' => Strings::get('back_to_menu', $language)]])
            : $this->optionList($flow, $node, $language, $state);

        $out[] = $conversation->addMessage('out', 'bot', $text, ['options' => $options, 'ai' => true]);

        return $this->finish($conversation, $state, $out);
    }

    /**
     * The buttons of a step, with an automatic Back button once the customer has moved on from another step.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $state
     * @return list<array{id: string, label: string}>
     */
    private function optionList(Flow $flow, array $node, string $language, array $state): array
    {
        $options = array_map(fn (array $o) => [
            'id' => (string) ($o['id'] ?? ''),
            'label' => $flow->localize($o['label'] ?? [], $language),
        ], array_values($node['options'] ?? []));

        if (! empty($state['history'])) {
            $options[] = ['id' => '__back', 'label' => '← '.Strings::get('opt_back', $language)];
        }

        return $options;
    }

    /**
     * Go to the step the customer came from (the main menu when there is none).
     *
     * @param  array<string, mixed>  $state
     * @param  list<\App\Models\Message>  $out
     * @return list<\App\Models\Message>
     */
    private function goBack(Conversation $conversation, Flow $flow, array $state, array $out): array
    {
        $state['misses'] = 0;
        $state['awaiting'] = null;
        unset($state['booking']);
        $target = array_pop($state['history']) ?? $flow->mainNodeId();

        // Back to the very first question (language): forget the language so they can choose again.
        if ($target === $flow->start() && $conversation->language !== null) {
            $conversation->forceFill(['language' => null])->save();
        }

        return $this->enter($conversation, $flow, $state, $flow->hasNode($target) ? $target : $flow->mainNodeId(), $out);
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
