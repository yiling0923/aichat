<?php

namespace App\Http\Controllers;

use App\Models\QuickReply;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Library: saved replies staff can drop into a chat. Everyone can use them; owners and managers edit them.
 */
class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.library', [
            'business' => $request->user()->business,
            'active' => 'library',
            'replies' => QuickReply::query()->orderBy('sort')->orderBy('id')->get(),
            'canEdit' => $request->user()->canManage(),
        ]);
    }

    /** For the inbox picker. */
    public function list(): JsonResponse
    {
        return response()->json(['replies' => QuickReply::query()->orderBy('sort')->orderBy('id')->get(['id', 'title', 'body'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        QuickReply::create($this->data($request));

        return back()->with('status', 'Reply added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        QuickReply::query()->findOrFail($id)->update($this->data($request));

        return back()->with('status', 'Reply saved.');
    }

    public function destroy(int $id): RedirectResponse
    {
        QuickReply::query()->findOrFail($id)->delete();

        return back()->with('status', 'Reply deleted.');
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'body_en' => ['required', 'string', 'max:1500'],
            'body_zh' => ['nullable', 'string', 'max:1500'],
        ]);

        return ['title' => $data['title'], 'body' => array_filter(['en' => $data['body_en'], 'zh' => $data['body_zh'] ?? null])];
    }
}
