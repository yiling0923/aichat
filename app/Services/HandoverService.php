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

    /** When switched on in Settings, a chat that needs a person goes to the least busy active team member. */
    private function autoAssign(Conversation $conversation): void
    {
        $business = $conversation->business;

        if ($conversation->assigned_to !== null || ! ($business->settings['auto_assign'] ?? false)) {
            return;
        }

        $load = \App\Models\Conversation::query()->withoutGlobalScopes()->where('business_id', $business->id)->where('status', 'open')->where('mode', 'human')
            ->whereNotNull('assigned_to')->selectRaw('assigned_to, count(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');

        $user = \App\Models\User::query()->where('business_id', $business->id)->where('active', true)->whereIn('role', ['owner', 'manager', 'staff'])->orderBy('id')->get()
            ->sortBy(fn ($u) => (int) ($load[$u->id] ?? 0))->first();

        if ($user !== null) {
            $conversation->forceFill(['assigned_to' => $user->id])->save();
        }
    }

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
        $this->autoAssign($conversation);

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

        app(Notifier::class)->notify($business, 'handover', Notifier::chatName($conversation).' needs a person', $reason, route('inbox', ['c' => $conversation->id]));

        $this->n8n->send('handover.requested', [
            'business' => $business->slug,
            'conversation_id' => $conversation->id,
            'reason' => $reason,
            'language' => $conversation->language,
            'open_now' => BusinessHours::isOpen($business),
        ]);
    }
}
