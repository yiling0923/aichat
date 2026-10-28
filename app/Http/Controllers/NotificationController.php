<?php

namespace App\Http\Controllers;

use App\Models\StaffNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The bell: the signed-in person's own notifications. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mine = StaffNotification::query()->where('user_id', $request->user()->id);

        return response()->json([
            'unread' => (clone $mine)->whereNull('read_at')->count(),
            'items' => (clone $mine)->orderByDesc('updated_at')->orderByDesc('id')->limit(15)->get()->map(fn (StaffNotification $n) => [
                'id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body, 'url' => $n->url,
                'read' => $n->read_at !== null, 'time' => $n->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        $mine = StaffNotification::query()->where('user_id', $request->user()->id);
        $one = (clone $mine)->whereKey($id)->first();

        if ($one !== null) {
            // Opening a chat or page clears everything that pointed there.
            (clone $mine)->whereNull('read_at')->where(fn ($q) => $q->whereKey($id)->when($one->url, fn ($w) => $w->orWhere('url', $one->url)))->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        StaffNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
