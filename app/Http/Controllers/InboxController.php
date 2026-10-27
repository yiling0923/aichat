<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff inbox. Every query here is limited to the signed-in user's
 * business by the BusinessScope, so another business's chats return 404.
 */
class InboxController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.inbox', ['business' => $request->user()->business]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');

        $items = Conversation::query()
            ->when(in_array($status, ['open', 'resolved'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(function (Conversation $c) {
                $last = Message::query()
                    ->where('conversation_id', $c->id)
                    ->where('is_private', false)
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => $c->id,
                    'name' => $c->contact_name ?: 'Visitor '.$c->id,
                    'channel' => $c->channel,
                    'mode' => $c->mode,
                    'status' => $c->status,
                    'preview' => $last?->body,
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                ];
            });

        return response()->json(['conversations' => $items]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($id);
        $after = (int) $request->query('after', 0);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => $m->toChatArray());

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'name' => $conversation->contact_name ?: 'Visitor '.$conversation->id,
                'channel' => $conversation->channel,
                'mode' => $conversation->mode,
                'status' => $conversation->status,
                'handover_reason' => $conversation->handover_reason,
            ],
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $conversation = Conversation::query()->findOrFail($id);

        // A staff reply means a person now owns the chat; the bot goes quiet.
        if (! $conversation->isHandledByStaff()) {
            $conversation->handOverToStaff('Staff replied');
        }

        $message = $conversation->addMessage('out', 'staff', $data['text']);

        return response()->json(['message' => $message->toChatArray(), 'mode' => $conversation->mode]);
    }

    public function note(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $conversation = Conversation::query()->findOrFail($id);

        $message = $conversation->addMessage('out', 'staff', $data['text'], null, true);

        return response()->json(['message' => $message->toChatArray()]);
    }

    public function giveBack(int $id): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($id);
        $conversation->giveBackToBot();
        $conversation->addMessage('out', 'system', 'Staff gave this chat back to the bot.', null, true);

        return response()->json(['mode' => $conversation->mode]);
    }

    public function resolve(int $id): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($id);
        $conversation->forceFill(['status' => 'resolved'])->save();

        return response()->json(['status' => $conversation->status]);
    }
}
