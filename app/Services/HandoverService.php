<?php

namespace App\Services;

use App\Models\BotEvent;
use App\Models\Conversation;
use App\Bot\BusinessHours;

/**
 * Passes a chat from the bot to staff: marks it, leaves a private note,
 * and tells n8n so staff are alerted.
 */
class HandoverService
{
    public function __construct(private N8nNotifier $n8n) {}

    /**
     * @param  array<string, mixed>  $context  what the bot has collected so far
     */
    public function start(Conversation $conversation, string $reason, array $context = []): void
    {
        // The menu preview never alerts staff or n8n.
        if ($conversation->channel === 'preview') {
            return;
        }

        $conversation->handOverToStaff($reason);

        $state = $conversation->state ?? [];
        $state['handover_stage'] = 0;
        $conversation->forceFill(['state' => $state])->save();

        $summary = collect($context)->filter(fn ($v) => is_scalar($v) && $v !== '')
            ->map(fn ($v, $k) => $k.': '.$v)->implode(', ');

        $conversation->addMessage(
            'out',
            'system',
            'Handover to staff. Reason: '.$reason.'. Language: '.($conversation->language ?: 'not chosen').'.'.($summary !== '' ? ' Collected: '.$summary.'.' : ''),
            null,
            true,
        );

        BotEvent::query()->create([
            'conversation_id' => $conversation->id,
            'type' => 'handover',
            'created_at' => now(),
        ]);

        $business = $conversation->business;

        $this->n8n->send('handover.requested', [
            'business' => $business->slug,
            'conversation_id' => $conversation->id,
            'reason' => $reason,
            'language' => $conversation->language,
            'open_now' => BusinessHours::isOpen($business),
        ]);
    }
}
