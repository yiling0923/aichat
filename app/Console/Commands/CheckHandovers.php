<?php

namespace App\Console\Commands;

use App\Bot\Strings;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\N8nNotifier;
use App\Support\CurrentBusiness;
use Illuminate\Console\Command;

/**
 * Runs every minute (Laravel scheduler). When a handed-over chat has had no
 * staff reply for a while it escalates: holding message, reminder, reassign, owner alert.
 */
class CheckHandovers extends Command
{
    protected $signature = 'aichat:check-handovers';

    protected $description = 'Escalate handed-over chats that staff have not answered';

    private const EVENTS = ['handover.holding', 'handover.reminder', 'handover.reassign', 'handover.owner_alert'];

    public function handle(CurrentBusiness $current, N8nNotifier $n8n): int
    {
        $timeouts = array_values(config('aichat.handover_timeouts'));
        $count = 0;

        foreach (Business::query()->where('status', 'active')->get() as $business) {
            $current->runAs($business, function () use ($business, $timeouts, $n8n, &$count) {
                Conversation::query()
                    ->where('mode', Conversation::MODE_HUMAN)
                    ->where('status', 'open')
                    ->whereNotNull('handover_at')
                    ->where('channel', '!=', 'preview')
                    ->each(function (Conversation $c) use ($business, $timeouts, $n8n, &$count) {
                        $stage = (int) ($c->state['handover_stage'] ?? 0);
                        $minutes = $c->handover_at->diffInMinutes(now());

                        if ($stage >= count($timeouts) || $minutes < $timeouts[$stage]) {
                            return;
                        }

                        $answered = Message::query()->where('conversation_id', $c->id)
                            ->where('sender_type', 'staff')->where('is_private', false)
                            ->where('created_at', '>=', $c->handover_at)->exists();

                        $state = $c->state ?? [];
                        $state['handover_stage'] = $answered ? count($timeouts) : $stage + 1;
                        $c->forceFill(['state' => $state])->save();

                        if ($answered) {
                            return;
                        }

                        if ($stage === 0) {
                            $c->addMessage('out', 'bot', Strings::get('handover_holding', $c->language));
                        }

                        $n8n->send(self::EVENTS[$stage], [
                            'business' => $business->slug,
                            'conversation_id' => $c->id,
                            'waiting_minutes' => $minutes,
                        ]);
                        $count++;
                    });
            });
        }

        $this->info($count.' escalation(s).');

        return self::SUCCESS;
    }
}
