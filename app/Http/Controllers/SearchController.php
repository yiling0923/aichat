<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The quick search box at the top of every staff page: customers and chats. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 60));

        if (mb_strlen($q) < 2) {
            return response()->json(['customers' => [], 'chats' => []]);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        $customers = Customer::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')->limit(6)->get()
            ->map(fn (Customer $c) => ['title' => $c->name ?: '(no name)', 'sub' => $c->phone, 'url' => route('customers.show', $c->id)]);

        $chats = Conversation::query()->where('channel', '!=', 'preview')
            ->where(fn ($w) => $w->where('contact_name', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like))
                ->orWhereHas('messages', fn ($m) => $m->where('is_private', false)->where('body', 'like', $like)))
            ->orderByDesc('last_message_at')->limit(6)->get()
            ->map(fn (Conversation $c) => ['title' => $c->contact_name ?: 'Visitor '.$c->id, 'sub' => ucfirst($c->channel).' · '.($c->last_message_at?->diffForHumans() ?? ''), 'url' => route('inbox', ['c' => $c->id])]);

        return response()->json(['customers' => $customers, 'chats' => $chats]);
    }
}
