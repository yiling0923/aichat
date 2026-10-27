<?php

namespace App\Services;

use App\Models\BotEvent;
use App\Models\Conversation;
use App\Models\Customer;

/**
 * Turns what the bot collected (name, phone, interest, ...) into a customer.
 */
class LeadService
{
    private const RANK = ['cold' => 1, 'warm' => 2, 'hot' => 3];

    public function __construct(private N8nNotifier $n8n) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function capture(Conversation $conversation, array $context, string $score = 'warm', bool $notify = true): Customer
    {
        $phone = $this->normalizePhone((string) ($context['phone'] ?? ''));
        $name = trim((string) ($context['name'] ?? '')) ?: null;

        $customer = $phone !== null ? Customer::query()->where('phone', $phone)->first() : null;
        $customer ??= new Customer;

        $extra = collect($context)->except(['name', 'phone', 'interest'])->filter(fn ($v) => is_scalar($v) && $v !== '')->all();

        $customer->fill([
            'name' => $name ?? $customer->name,
            'phone' => $phone ?? $customer->phone,
            'language' => $conversation->language ?? $customer->language,
            'interest' => ($context['interest'] ?? null) ?: $customer->interest,
            'details' => array_merge($customer->details ?? [], $extra),
            'score' => $this->higher($customer->score, $score),
        ]);
        $customer->consented_at ??= now();
        $customer->save();

        $conversation->forceFill([
            'customer_id' => $customer->id,
            'contact_name' => $customer->name ?? $conversation->contact_name,
        ])->save();

        $parts = array_filter([
            'Lead saved: '.($customer->name ?? 'no name').($customer->phone ? ', '.$customer->phone : ''),
            $customer->interest ? 'Interest: '.$customer->interest : null,
            isset($extra['preferred_time']) ? 'Preferred time: '.$extra['preferred_time'] : null,
            'Score: '.$customer->score,
        ]);
        $conversation->addMessage('out', 'system', implode('. ', $parts).'.', null, true);

        BotEvent::query()->create(['conversation_id' => $conversation->id, 'type' => 'lead', 'created_at' => now()]);

        if ($notify) {
            $this->n8n->send('lead.captured', [
                'business' => $conversation->business->slug,
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'interest' => $customer->interest,
                'preferred_time' => $extra['preferred_time'] ?? null,
                'score' => $customer->score,
            ]);
        }

        return $customer;
    }

    public function normalizePhone(string $raw): ?string
    {
        $plus = str_starts_with(trim($raw), '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits === '' ? null : $plus.$digits;
    }

    private function higher(?string $current, string $new): string
    {
        return (self::RANK[$current] ?? 0) >= (self::RANK[$new] ?? 0) ? (string) $current : $new;
    }
}
