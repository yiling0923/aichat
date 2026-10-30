<?php

namespace App\Channels;

use App\Bot\BotEngine;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\N8nNotifier;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Takes one customer message from Meta, finds or starts the conversation, saves the message
 * and lets the menu bot answer (the answer goes out through ChannelSender).
 */
class InboundHandler
{
    public function __construct(private CurrentBusiness $current, private BotEngine $bot, private N8nNotifier $n8n) {}

    public function handle(InboundEvent $event): void
    {
        $channel = Channel::query()->withoutGlobalScopes()
            ->where('type', $event->type)->where('external_id', $event->channelKey)->first();

        if ($channel === null || ! $channel->active) {
            Log::info('Meta message for an unknown or switched-off channel', ['type' => $event->type, 'key' => $event->channelKey]);
            \App\Http\Controllers\MetaWebhookController::note($channel === null ? 'unknown_channel' : 'channel_off', 1);
            try {
                \Illuminate\Support\Facades\Cache::put('meta_webhook_unknown_id', (string) $event->channelKey, now()->addDays(14));
            } catch (\Throwable) {
            }

            return;
        }

        $business = $channel->business;

        if (! $business->isActive()) {
            return;
        }

        $this->current->runAs($business, function () use ($event, $channel, $business): void {
            // Meta retries a delivery it thinks failed: never handle the same message twice.
            if (Message::query()->where('external_id', $event->messageId)->exists()) {
                return;
            }

            $conversation = Conversation::query()->where('channel_id', $channel->id)->where('external_id', $event->from)->first();
            $isNew = $conversation === null;

            if ($isNew) {
                $conversation = Conversation::create([
                    'channel' => $channel->type,
                    'channel_id' => $channel->id,
                    'external_id' => $event->from,
                    'token' => Str::random(40),
                    'contact_name' => $event->name,
                    'mode' => Conversation::MODE_BOT,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);

                $this->n8n->send('conversation.started', ['business' => $business->slug, 'conversation_id' => $conversation->id, 'channel' => $channel->type]);
            }

            // The time the customer really wrote it (Meta can deliver late), never in the future and never going backwards.
            $wrote = $event->timestamp ? Carbon::createFromTimestamp($event->timestamp) : now();
            $wrote = $wrote->gt(now()) ? now() : $wrote;
            $stale = $wrote->lt(now()->subMinutes((int) config('aichat.stale_message_minutes')));

            $conversation->forceFill([
                'last_inbound_at' => $conversation->last_inbound_at && $conversation->last_inbound_at->gt($wrote) ? $conversation->last_inbound_at : $wrote,
                'status' => 'open',
                'contact_name' => $conversation->contact_name ?: $event->name,
            ])->save();
            $channel->forceFill(['last_inbound_at' => now(), 'last_error' => null])->save();

            $incoming = $conversation->addMessage('in', 'customer', $event->text);
            $incoming->forceFill(['external_id' => $event->messageId])->save();

            // A message that arrives long after it was written (the tunnel was down, Meta retried): keep it for the
            // team, but do not answer it now. The customer has moved on and a late answer would only confuse them.
            if ($stale) {
                $conversation->addMessage('out', 'system', 'This message arrived '.$wrote->diffForHumans(null, true).' late (it was written at '.$wrote->setTimezone($business->timezone ?: 'UTC')->format('d M H:i').'). The bot did not answer it.', null, true);
                $this->n8n->send('customer.message', ['business' => $business->slug, 'conversation_id' => $conversation->id, 'channel' => $channel->type, 'text' => Str::limit($event->text, 200), 'late' => true]);

                return;
            }

            $replies = $this->bot->respond($conversation, $incoming, $event->optionId);

            // A trace for troubleshooting: what arrived and what the bot did with it.
            Log::info('Meta message handled', [
                'channel' => $channel->type, 'conversation' => $conversation->id, 'option' => $event->optionId,
                'mode' => $conversation->fresh()->mode, 'replies' => count($replies),
            ]);

            if ($replies === [] && $conversation->isHandledByStaff()) {
                $this->n8n->send('customer.message', [
                    'business' => $business->slug,
                    'conversation_id' => $conversation->id,
                    'channel' => $channel->type,
                    'text' => Str::limit($event->text, 200),
                ]);
            }
        });
    }
}
