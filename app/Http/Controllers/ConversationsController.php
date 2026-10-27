<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Every conversation, including resolved ones, with search inside the message text.
 */
class ConversationsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');
        $mode = $request->query('mode', 'all');
        $tag = (int) $request->query('tag', 0);

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        $conversations = Conversation::query()
            ->where('channel', '!=', 'preview')
            ->with(['customer', 'tags', 'assignee'])
            ->when(in_array($status, ['open', 'resolved'], true), fn ($w) => $w->where('status', $status))
            ->when(in_array($mode, ['bot', 'human'], true), fn ($w) => $w->where('mode', $mode))
            ->when($tag > 0, fn ($w) => $w->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('contact_name', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))
                ->orWhereHas('messages', fn ($m) => $m->where('is_private', false)->where('body', 'like', $like))))
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        // One matching line per conversation, to show why it matched.
        $snippets = [];
        if ($q !== '') {
            $hits = Message::query()->whereIn('conversation_id', $conversations->pluck('id'))
                ->where('is_private', false)->where('body', 'like', $like)->orderBy('id')->get();
            foreach ($hits as $m) {
                $snippets[$m->conversation_id] ??= $m->body;
            }
        }

        return view('admin.conversations', [
            'business' => $request->user()->business,
            'active' => 'conversations',
            'conversations' => $conversations,
            'snippets' => $snippets,
            'tags' => Tag::query()->where('type', 'chat')->orderBy('name')->get(),
            'q' => $q, 'status' => $status, 'mode' => $mode, 'tag' => $tag,
        ]);
    }
}
