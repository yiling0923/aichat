<?php

namespace App\Bot;

use App\Ai\AiAssistant;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Decides what the bot says. Phase 1: the guided menu bot. Phase 5b can add an
 * AI mode behind the same method, so the chat page, inbox and channels do not change.
 */
class BotEngine
{
    public function __construct(private MenuBot $menu, private FlowRepository $flows, private AiAssistant $ai) {}

    /**
     * @param  Flow|null  $flow  a flow to use instead of the published one (menu preview)
     * @return list<Message> messages the bot sent in reply
     */
    public function respond(Conversation $conversation, Message $incoming, ?string $optionId = null, ?Flow $flow = null): array
    {
        // A customer who is tired of waiting can type "menu" (or tap the button) to get the bot back.
        if ($conversation->isHandledByStaff() && $this->wantsMenu((string) $incoming->body, $optionId)) {
            $conversation->giveBackToBot();
            $conversation->forceFill(['state' => null])->save();
            if ($conversation->channel !== 'preview' && $conversation->business !== null) {
                app(\App\Services\Notifier::class)->notify($conversation->business, 'message', \App\Services\Notifier::chatName($conversation).' went back to the menu', 'The customer chose the bot again. The chat is back with the bot.', route('inbox', ['c' => $conversation->id]));
            }
        }

        // Staff are handling this chat, so the bot stays silent.
        if ($conversation->isHandledByStaff()) {
            if ($conversation->channel !== 'preview' && $conversation->business !== null && (string) $incoming->body !== '') {
                app(\App\Services\Notifier::class)->notify($conversation->business, 'message', 'New message from '.\App\Services\Notifier::chatName($conversation), (string) $incoming->body, route('inbox', ['c' => $conversation->id]));
            }

            // Staff are in charge, but the AI can still offer them a suggested reply (never sent by itself).
            if ($conversation->channel !== 'preview' && $conversation->business !== null && $this->ai->active($conversation->business)) {
                try {
                    $this->ai->suggest($conversation);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return [];
        }

        $flow ??= $this->flows->published($conversation->business);

        return $this->menu->respond($conversation, $flow, (string) $incoming->body, $optionId);
    }

    private function wantsMenu(string $text, ?string $optionId): bool
    {
        return $optionId === '__menu' || in_array(\App\Bot\Text::normalize($text), ['menu', 'main menu', 'back to menu', 'bot', '菜单', '主菜单', '返回菜单'], true);
    }
}
