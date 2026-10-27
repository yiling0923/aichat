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
            'text' => ['required', 'string', 'max:1000'],
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

        $incoming = $conversation->addMessage('in', 'customer', $data['text']);
        $bot->respond($conversation, $incoming);

        return response()->json([
            'token' => $conversation->token,
            'mode' => $conversation->fresh()->mode,
            'messages' => $this->messagesAfter($conversation, 0),
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
