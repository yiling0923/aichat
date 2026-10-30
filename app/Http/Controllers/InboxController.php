<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Ai\AiAssistant;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tag;
use App\Models\User;
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
        return view('admin.inbox', ['business' => $request->user()->business, 'active' => 'inbox']);
    }

    public function conversations(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');

        $items = Conversation::query()
            ->where('channel', '!=', 'preview')
            ->with('tags')
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
                    'name' => $c->contact_name ?: __('Visitor :n', ['n' => $c->id]),
                    'channel' => $c->channel,
                    'mode' => $c->mode,
                    'status' => $c->status,
                    'preview' => $last?->body,
                    'last_from' => $last?->direction,
                    'needs_reply' => $c->status === 'open' && $c->mode === 'human',
                    'assigned_to' => $c->assigned_to,
                    'tags' => $c->tags->map->only(['id', 'name', 'color'])->values()->all(),
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                ];
            });

        return response()->json(['conversations' => $items]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::query()->with('customer')->findOrFail($id);
        $after = (int) $request->query('after', 0);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get()
            ->reject(fn (Message $m) => $m->sender_type === 'ai' && ($m->payload['status'] ?? 'pending') !== 'pending')
            ->values()
            ->map(fn (Message $m) => $m->toChatArray());

        $receipts = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'out')->where('is_private', false)->whereIn('sender_type', ['staff', 'bot'])
            ->latest('id')->limit(15)->get()->map(fn (Message $m) => ['id' => $m->id, 'delivery' => $m->payload['delivery'] ?? null, 'delivery_error' => $m->payload['delivery_error'] ?? null, 'receipt' => $m->payload['receipt'] ?? null])->values();

        return response()->json([
            'receipts' => $receipts,
            'conversation' => [
                'id' => $conversation->id,
                'name' => $conversation->contact_name ?: __('Visitor :n', ['n' => $conversation->id]),
                'channel' => $conversation->channel,
                'language' => $conversation->language ?? $conversation->customer?->language,
                'mode' => $conversation->mode,
                'status' => $conversation->status,
                'handover_reason' => $conversation->handover_reason,
                'assigned_to' => $conversation->assigned_to,
                'ai_intent' => $conversation->ai_intent,
                'ai_mood' => $conversation->ai_mood,
                'ai_on' => app(AiAssistant::class)->active($conversation->business),
                'channel_label' => $conversation->channel_id ? ($conversation->channelAccount?->label() ?? ucfirst($conversation->channel)) : 'Website',
                'window_open' => $conversation->windowOpen(),
                'can_template' => $conversation->channel_id && $conversation->channelAccount?->type === 'whatsapp',
                'customer' => $conversation->customer ? [
                    'id' => $conversation->customer->id,
                    'name' => $conversation->customer->name,
                    'phone' => $conversation->customer->phone,
                    'score' => $conversation->customer->score,
                    'url' => route('customers.show', $conversation->customer->id),
                ] : null,
                'tags' => $conversation->tags()->get(['tags.id', 'name', 'color'])->map->only(['id', 'name', 'color'])->all(),
            ],
            'templates' => $conversation->channel_id && $conversation->channelAccount?->type === 'whatsapp'
                ? MessageTemplate::query()->where('channel_id', $conversation->channel_id)->where('status', 'APPROVED')->orderBy('name')->get(['id', 'name', 'language', 'body'])
                : [],
            'all_tags' => Tag::query()->where('type', 'chat')->orderBy('name')->get(['id', 'name', 'color']),
            'team' => User::query()->where('business_id', $conversation->business_id)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);

        return $this->sendStaffReply(Conversation::query()->findOrFail($id), $data['text']);
    }

    private function sendStaffReply(Conversation $conversation, string $text, ?array $payload = null): JsonResponse
    {
        if (! $conversation->windowOpen()) {
            return response()->json(['error' => $conversation->channelAccount?->type === 'whatsapp'
                ? 'The 24-hour window is closed. Send an approved template instead.'
                : 'The 24-hour window is closed. The customer has to message you first.'], 422);
        }

        // A staff reply means a person now owns the chat; the bot goes quiet.
        if (! $conversation->isHandledByStaff()) {
            $conversation->handOverToStaff('Staff replied');
        }

        // Writing in a resolved chat means it is not finished after all.
        $conversation->reopen();

        $message = $conversation->addMessage('out', 'staff', $text, $payload);

        return response()->json(['message' => $message->toChatArray(), 'mode' => $conversation->mode]);
    }

    /** Send a photo, video, voice note or file to the customer (the box text becomes its caption). */
    public function attach(Request $request, int $id): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:16384'], 'caption' => ['nullable', 'string', 'max:1000']]);
        $conversation = Conversation::query()->findOrFail($id);
        $file = $request->file('file');
        $mime = strtolower((string) $file->getMimeType());

        $type = \App\Support\ChatFile::type($mime);

        if ($type === null) {
            return response()->json(['error' => \App\Support\ChatFile::REFUSED], 422);
        }

        $path = 'chat-media/'.$conversation->business_id.'/out-'.\Illuminate\Support\Str::random(24);
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $caption = trim((string) $request->input('caption', ''));
        $name = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string) $file->getClientOriginalName()), 0, 80);

        $media = ['type' => $type, 'mime' => $mime, 'filename' => $name, 'caption' => $caption, 'path' => $path, 'token' => \Illuminate\Support\Str::random(40)];

        return $this->sendStaffReply($conversation, $caption !== '' ? $caption : \App\Channels\InboundEvent::mediaLabel($type), ['media' => $media]);
    }

    /** Send the AI's drafted reply (optionally edited) as a staff message. */
    public function aiSend(Request $request, int $id, int $messageId): JsonResponse
    {
        $data = $request->validate(['text' => ['nullable', 'string', 'max:2000']]);
        $conversation = Conversation::query()->findOrFail($id);
        $draft = Message::query()->where('conversation_id', $conversation->id)->where('sender_type', 'ai')->findOrFail($messageId);

        abort_unless(($draft->payload['status'] ?? 'pending') === 'pending', 422, 'This suggestion was already used.');

        $text = trim((string) ($data['text'] ?? '')) !== '' ? $data['text'] : (string) $draft->body;
        // A booking answer says "tap Book now", so it must carry that button, like the bot's own answers do.
        $payload = ($draft->payload['intent'] ?? '') === 'booking'
            ? ['options' => \App\Marketing\Outreach::bookNowButton($conversation->language ?? 'en')]
            : null;
        $response = $this->sendStaffReply($conversation, $text, $payload);

        if ($response->getStatusCode() === 200) {
            $draft->forceFill(['payload' => array_merge((array) $draft->payload, ['status' => 'used'])])->save();
        }

        return $response;
    }

    public function aiDismiss(int $id, int $messageId): JsonResponse
    {
        $draft = Message::query()->where('conversation_id', $id)->where('sender_type', 'ai')->findOrFail($messageId);
        $draft->forceFill(['payload' => array_merge((array) $draft->payload, ['status' => 'dismissed'])])->save();

        return response()->json(['ok' => true]);
    }

    public function summarize(int $id, AiAssistant $ai): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($id);
        $summary = $ai->summarize($conversation);

        return $summary === null
            ? response()->json(['error' => $ai->lastNote ?? 'The AI could not summarise this chat.'], 422)
            : response()->json(['summary' => $summary]);
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

    public function reopen(int $id): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($id);
        $conversation->reopen('Staff reopened this chat.');

        return response()->json(['status' => $conversation->status]);
    }

    public function tag(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['tag_id' => ['required', 'integer'], 'on' => ['required', 'boolean']]);
        $conversation = Conversation::query()->findOrFail($id);
        $tag = Tag::query()->where('type', 'chat')->findOrFail($data['tag_id']);

        $data['on'] ? $conversation->tags()->syncWithoutDetaching([$tag->id]) : $conversation->tags()->detach($tag->id);

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer']]);
        $conversation = Conversation::query()->findOrFail($id);
        $userId = ! empty($data['user_id'])
            ? User::query()->where('business_id', $conversation->business_id)->where('active', true)->findOrFail($data['user_id'])->id
            : null;

        $conversation->forceFill(['assigned_to' => $userId])->save();

        return response()->json(['assigned_to' => $userId]);
    }
}
