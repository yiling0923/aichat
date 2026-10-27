<?php

namespace App\Bot;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Decides what the bot says. This is the phase 0 placeholder: it only echoes.
 * Phase 1 replaces it with the guided menu bot behind the same method, so the
 * chat page, inbox and tests do not change.
 */
class BotEngine
{
    /**
     * @return list<Message> messages the bot sent in reply
     */
    public function respond(Conversation $conversation, Message $incoming): array
    {
        // Staff are handling this chat, so the bot stays silent.
        if ($conversation->isHandledByStaff()) {
            return [];
        }

        $text = trim((string) $incoming->body);
        $isFirstReply = ! $conversation->messages()->where('sender_type', 'bot')->exists();

        $body = $isFirstReply
            ? 'Hi! You are chatting with '.$conversation->business->name.'. The menu bot arrives in the next step, so for now I repeat what you type: "'.$text.'"'
            : 'You said: "'.$text.'". The menu bot is coming in the next step.';

        return [$conversation->addMessage('out', 'bot', $body)];
    }
}
