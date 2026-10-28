<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tag management: create, rename, recolour, merge and delete tags for customers and for chats.
 */
class TagController extends Controller
{
    public const PALETTE = ['#0f766e', '#2563eb', '#7c3aed', '#db2777', '#b91c1c', '#c2410c', '#b45309', '#15803d', '#475569'];

    /** One-click ideas shown for tags that do not exist yet. */
    public const SUGGESTIONS = [
        'customer' => ['VIP', 'First visit', 'Regular', 'Needs follow-up', 'Referred by a friend'],
        'chat' => ['Price question', 'Complaint', 'Booking help', 'Urgent', 'Feedback'],
    ];

    private function sorted($tags, string $count, ?string $sort)
    {
        return $sort === 'uses' ? $tags->sortByDesc($count)->values() : $tags->sortBy(fn ($t) => mb_strtolower($t->name))->values();
    }

    public function index(Request $request): View
    {
        return view('admin.tags', [
            'business' => $request->user()->business,
            'active' => 'tags',
            'customerTags' => $this->sorted(Tag::query()->where('type', 'customer')->withCount('customers')->get(), 'customers_count', $request->query('sort')),
            'chatTags' => $this->sorted(Tag::query()->where('type', 'chat')->withCount('conversations')->get(), 'conversations_count', $request->query('sort')),
            'sort' => $request->query('sort') === 'uses' ? 'uses' : 'name',
            'tab' => $request->query('tab') === 'chat' ? 'chat' : 'customer',
            'suggestions' => self::SUGGESTIONS,
            'palette' => self::PALETTE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(Tag::TYPES)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $name = trim($data['name']);

        if (Tag::query()->where('type', $data['type'])->where('name', $name)->exists()) {
            return back()->withErrors(['name' => 'A '.$data['type'].' tag called "'.$name.'" already exists.']);
        }

        Tag::create(['name' => $name, 'type' => $data['type'], 'color' => $data['color'] ?? '#0f766e']);

        return back()->with('status', 'Tag added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tag = Tag::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $name = trim($data['name']);

        if (Tag::query()->where('type', $tag->type)->where('name', $name)->where('id', '!=', $tag->id)->exists()) {
            return back()->withErrors(['name' => 'There is already a tag called "'.$name.'". Use Merge to combine them.']);
        }

        $tag->update(['name' => $name, 'color' => $data['color']]);

        return back()->with('status', 'Tag saved.');
    }

    public function merge(Request $request, int $id): RedirectResponse
    {
        $source = Tag::query()->findOrFail($id);
        $data = $request->validate(['into' => ['required', 'integer']]);
        $target = Tag::query()->where('type', $source->type)->findOrFail($data['into']);

        abort_if($target->id === $source->id, 422, 'Pick a different tag to merge into.');

        $source->customers()->get()->each(fn ($c) => $c->tags()->syncWithoutDetaching([$target->id]));
        $source->conversations()->get()->each(fn ($c) => $c->tags()->syncWithoutDetaching([$target->id]));
        $source->delete();

        return back()->with('status', 'Merged "'.$source->name.'" into "'.$target->name.'".');
    }

    public function destroy(int $id): RedirectResponse
    {
        Tag::query()->findOrFail($id)->delete();

        return back()->with('status', 'Tag deleted.');
    }
}
