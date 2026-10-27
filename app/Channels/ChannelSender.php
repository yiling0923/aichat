<?php

namespace App\Channels;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a saved message out through the conversation's channel and records whether it was delivered.
 * A failure never breaks the chat: the message stays in the inbox, marked "not delivered".
 */
class ChannelSender
{
    public function __construct(private MetaGraph $graph, private OutboundFormatter $formatter) {}

    public function deliver(Conversation $conversation, Message $message): void
    {
        $channel = $conversation->channelAccount;

        if ($channel === null || ! $channel->active || ! $conversation->external_id) {
            $this->mark($message, 'failed', 'This chat is no longer connected to an active channel.');

            return;
        }

        if (! $conversation->windowOpen()) {
            $this->mark($message, 'failed', $channel->type === 'whatsapp'
                ? 'The 24-hour window is closed. Send an approved template instead.'
                : 'The 24-hour window is closed. The customer has to message you first.');

            return;
        }

        try {
            $request = $this->formatter->format($channel, $conversation->external_id, $message);
            $response = $this->graph->orFail($this->graph->post($request['path'], $channel->access_token, $request['json']));

            $id = $response->json('messages.0.id') ?? $response->json('message_id');
            $this->mark($message, 'sent', null, $id);
        } catch (Throwable $e) {
            Log::warning('Channel send failed', ['channel' => $channel->id, 'error' => $e->getMessage()]);
            $channel->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            $this->mark($message, 'failed', $e->getMessage());
        }
    }

    private function mark(Message $message, string $status, ?string $error = null, ?string $externalId = null): void
    {
        $payload = (array) $message->payload;
        $payload['delivery'] = $status;
        if ($error !== null) {
            $payload['delivery_error'] = $error;
        }

        $message->forceFill(['payload' => $payload, 'external_id' => $externalId ?? $message->external_id])->save();
    }
}
