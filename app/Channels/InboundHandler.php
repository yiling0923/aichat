<?php

namespace App\Channels;

use App\Bot\BotEngine;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\N8nNotifier;
use App\Support\CurrentBusiness;
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

            $conversation->forceFill([
                'last_inbound_at' => now(),
                'status' => 'open',
                'contact_name' => $conversation->contact_name ?: $event->name,
            ])->save();
            $channel->forceFill(['last_inbound_at' => now(), 'last_error' => null])->save();

            $incoming = $conversation->addMessage('in', 'customer', $event->text);
            $incoming->forceFill(['external_id' => $event->messageId])->save();

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
