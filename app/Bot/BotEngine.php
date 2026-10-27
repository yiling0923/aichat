<?php

namespace App\Bot;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Decides what the bot says. Phase 1: the guided menu bot. Phase 5b can add an
 * AI mode behind the same method, so the chat page, inbox and channels do not change.
 */
class BotEngine
{
    public function __construct(private MenuBot $menu, private FlowRepository $flows) {}

    /**
     * @param  Flow|null  $flow  a flow to use instead of the published one (menu preview)
     * @return list<Message> messages the bot sent in reply
     */
    public function respond(Conversation $conversation, Message $incoming, ?string $optionId = null, ?Flow $flow = null): array
    {
        // Staff are handling this chat, so the bot stays silent.
        if ($conversation->isHandledByStaff()) {
            return [];
        }

        $flow ??= $this->flows->published($conversation->business);

        return $this->menu->respond($conversation, $flow, (string) $incoming->body, $optionId);
    }
}
