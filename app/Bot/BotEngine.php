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

            // Staff are in charge, but the AI can still offer them a suggested reply. In Auto mode, while the customer is still
            // waiting for a first reply from a person, a plain factual answer (hours, prices, location) is sent straight away.
            if ($conversation->channel !== 'preview' && $conversation->business !== null && ($sent = $this->answerWhileWaiting($conversation, $incoming, $flow)) !== []) {
                return $sent;
            }

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

    /**
     * The customer asked for a person and nobody has answered yet. If the AI knows a plain factual answer, send it now
     * (at most 3 times, never when the customer is upset or asking for a person), and say the team will still reply.
     *
     * @return list<Message>
     */
    private function answerWhileWaiting(Conversation $conversation, Message $incoming, ?Flow $flow = null): array
    {
        $since = $conversation->handover_at;

        if ($since === null || Message::query()->where('conversation_id', $conversation->id)->where('sender_type', 'staff')->where('is_private', false)->where('created_at', '>=', $since)->exists()) {
            return [];
        }

        $already = Message::query()->where('conversation_id', $conversation->id)->where('payload->ai_waiting', true)->where('created_at', '>=', $since)->count();
        $normalized = Text::normalize((string) $incoming->body);

        if ($already >= 3 || Text::isCommand($normalized, (array) config('aichat.keywords.human')) || Text::isCommand($normalized, (array) config('aichat.keywords.upset'))) {
            return [];
        }

        $language = $conversation->language ?? 'en';

        // The menu's own information (prices, hours, location, promotions) needs no AI: if the customer's words point to it, send it.
        $flow ??= $this->flows->published($conversation->business);
        $info = $this->menu->infoAnswer($conversation, $flow, (string) $incoming->body);
        if ($info !== null) {
            return [$conversation->addMessage('out', 'bot', $info."\n\n".Strings::get('ai_waiting_note', $language), ['waiting_answer' => true, 'ai_waiting' => true])];
        }

        if ($this->ai->mode($conversation->business) !== 'auto' || ! $this->ai->active($conversation->business)) {
            return [];
        }

        $answer = $this->ai->answer($conversation, 'reply');

        if ($answer === null || $answer->intent !== 'faq' || $answer->wantsHuman || ! in_array($answer->mood, ['calm', 'happy'], true)) {
            return [];
        }

        $text = $answer->text;
        if (! Message::query()->where('conversation_id', $conversation->id)->where('sender_type', 'bot')->where('payload->ai', true)->exists()) {
            $text .= "\n".Strings::get('ai_disclosure', $language);
        }

        return [$conversation->addMessage('out', 'bot', $text."\n".Strings::get('ai_waiting_note', $language), ['ai' => true, 'ai_waiting' => true])];
    }

    private function wantsMenu(string $text, ?string $optionId): bool
    {
        return $optionId === '__menu' || in_array(\App\Bot\Text::normalize($text), ['menu', 'main menu', 'back to menu', 'bot', '菜单', '主菜单', '返回菜单', '菜單', '主菜單', '返回菜單'], true);
    }
}
