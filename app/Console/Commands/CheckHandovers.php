<?php

namespace App\Console\Commands;

use App\Bot\Strings;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\N8nNotifier;
use App\Services\Notifier;
use App\Services\StaffAlerts;
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

    /**
     * The team gets a new bell alert (and the chime) at every step, so a waiting customer is never forgotten.
     * At the last step the owner and managers get an urgent one.
     */
    private function alertStaff(Business $business, Conversation $c, int $stage, int $minutes): void
    {
        $name = Notifier::chatName($c);
        $url = route('inbox', ['c' => $c->id]);
        $urgent = $stage >= 3;

        // Outside the browser (email, WhatsApp) only at 10 minutes and at the urgent 30, so it never turns into spam.
        if (in_array($stage, [1, 3], true)) {
            app(StaffAlerts::class)->waiting($business, $c, $minutes, $urgent);
        }

        app(Notifier::class)->notify(
            $business, 'handover',
            ($urgent ? 'URGENT: ' : '').$name.' has waited '.\App\Support\Duration::words($minutes),
            $c->handover_reason ?: 'Nobody has replied yet.',
            $url,
            $urgent ? ['owner', 'manager'] : null,
        );
    }

    /** Nobody answered for the time the business chose: the bot takes the chat back and shows the menu again. */
    private function returnToBot(Business $business, Conversation $c, int $minutes, N8nNotifier $n8n): void
    {
        $c->giveBackToBot();
        $c->forceFill(['state' => null])->save();
        $c->addMessage('out', 'system', 'Nobody answered for '.\App\Support\Duration::words($minutes).', so the chat went back to the bot.', null, true);
        $c->addMessage('out', 'bot', Strings::get('handover_return', $c->language));

        // Show the main menu again so the customer has buttons to tap.
        try {
            app(\App\Bot\MenuBot::class)->respond($c, app(\App\Bot\FlowRepository::class)->published($business), 'menu', '__menu');
        } catch (\Throwable $e) {
            report($e);
        }

        app(Notifier::class)->notify($business, 'handover', Notifier::chatName($c).' went back to the bot', 'Nobody answered for '.\App\Support\Duration::words($minutes).'. Reply any time to take over again.', route('inbox', ['c' => $c->id]));
        $n8n->send('handover.returned_to_bot', ['business' => $business->slug, 'conversation_id' => $c->id, 'waiting_minutes' => $minutes]);
    }

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

                        $answered = Message::query()->where('conversation_id', $c->id)
                            ->where('sender_type', 'staff')->where('is_private', false)
                            ->where('created_at', '>=', $c->handover_at)->exists();

                        // The business can choose to let the bot take the chat back when nobody has answered for long enough.
                        $returnAfter = (int) (((array) $business->settings)['handover_return_minutes'] ?? 0);
                        if (! $answered && $returnAfter > 0 && $minutes >= $returnAfter) {
                            $this->returnToBot($business, $c, (int) $minutes, $n8n);
                            $count++;

                            return;
                        }

                        if ($stage >= count($timeouts) || $minutes < $timeouts[$stage]) {
                            return;
                        }

                        $state = $c->state ?? [];
                        $state['handover_stage'] = $answered ? count($timeouts) : $stage + 1;
                        $c->forceFill(['state' => $state])->save();

                        if ($answered) {
                            return;
                        }

                        if ($stage === 0) {
                            $c->addMessage('out', 'bot', Strings::get('handover_holding', $c->language));
                        }

                        // After 20 minutes the customer gets a second, honest word and a way back to the menu.
                        if ($stage === 2) {
                            $c->addMessage('out', 'bot', Strings::get('handover_sorry', $c->language));
                        }

                        $this->alertStaff($business, $c, $stage, (int) $minutes);

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
