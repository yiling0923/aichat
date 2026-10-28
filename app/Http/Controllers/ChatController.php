<?php

namespace App\Http\Controllers;

use App\Bot\BotEngine;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\N8nNotifier;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * The built-in website test chat. Visitors have no account; a random token
 * in their browser identifies their conversation.
 */
class ChatController extends Controller
{
    public function __construct(private CurrentBusiness $current) {}

    public function show(string $slug): View
    {
        return view('chat.widget', ['business' => $this->current->get()]);
    }

    public function send(Request $request, string $slug, BotEngine $bot, N8nNotifier $n8n): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required_without:option_id', 'nullable', 'string', 'max:1000'],
            'option_id' => ['nullable', 'string', 'max:60'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        $conversation = ! empty($data['token'])
            ? Conversation::query()->where('token', $data['token'])->first()
            : null;

        $isNew = $conversation === null;

        if ($isNew) {
            $conversation = Conversation::startFor('web');
            $n8n->send('conversation.started', [
                'business' => $this->current->get()->slug,
                'conversation_id' => $conversation->id,
                'channel' => 'web',
            ]);
        }

        if (! $isNew && $conversation->status === 'resolved') {
            $conversation->reopen('The customer wrote again, so this chat is open again.');
            if ($conversation->business !== null) {
                app(\App\Services\Notifier::class)->notify($conversation->business, 'message', \App\Services\Notifier::chatName($conversation).' wrote again', 'A resolved chat is open again.', route('inbox', ['c' => $conversation->id]));
            }
        }

        $incoming = $conversation->addMessage('in', 'customer', (string) ($data['text'] ?? ''));
        $bot->respond($conversation, $incoming, $data['option_id'] ?? null);

        return response()->json([
            'token' => $conversation->token,
            'mode' => $conversation->fresh()->mode,
            'messages' => $this->messagesAfter($conversation, $incoming->id - 1),
        ]);
    }

    public function poll(Request $request, string $slug): JsonResponse
    {
        $token = (string) $request->query('token', '');
        $after = (int) $request->query('after', 0);

        $conversation = $token !== ''
            ? Conversation::query()->where('token', $token)->first()
            : null;

        abort_if($conversation === null, 404);

        return response()->json([
            'mode' => $conversation->mode,
            'messages' => $this->messagesAfter($conversation, $after),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messagesAfter(Conversation $conversation, int $afterId): array
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_private', false)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => $m->toChatArray())
            ->all();
    }
}
