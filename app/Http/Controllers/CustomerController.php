<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customers the bot has collected (one per phone number). Limited to the signed-in
 * business by the BusinessScope.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $score = (string) $request->query('score', '');
        $tag = (int) $request->query('tag', 0);
        $language = (string) $request->query('language', '');
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $customers = Customer::query()
            ->with('tags')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('interest', 'like', $like)))
            ->when(in_array($score, ['hot', 'warm', 'cold'], true), fn ($q) => $q->where('score', $score))
            ->when(in_array($language, config('aichat.languages'), true), fn ($q) => $q->where('language', $language))
            ->when($tag > 0, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers', [
            'business' => $request->user()->business,
            'active' => 'customers',
            'customers' => $customers,
            'tags' => Tag::query()->where('type', 'customer')->orderBy('name')->get(),
            'search' => $search, 'score' => $score, 'tag' => $tag, 'language' => $language,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $customer = Customer::query()->with(['tags', 'conversations', 'bookings.service', 'bookings.practitioner'])->findOrFail($id);

        return view('admin.customer', [
            'business' => $request->user()->business,
            'active' => 'customers',
            'customer' => $customer,
            'allTags' => Tag::query()->where('type', 'customer')->orderBy('name')->get(),
            'tasks' => Task::query()->where('customer_id', $customer->id)->whereNull('done_at')->get(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'language' => ['nullable', Rule::in(config('aichat.languages'))],
            'score' => ['nullable', Rule::in(['hot', 'warm', 'cold'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'tags' => ['array'],
        ]);

        $customer->update(collect($data)->except('tags')->all());
        $customer->tags()->sync(Tag::query()->where('type', 'customer')->whereIn('id', array_map('intval', $data['tags'] ?? []))->pluck('id'));

        return back()->with('status', 'Customer saved.');
    }

    /** Add or remove one tag for the ticked customers on the list. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'tag_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['add', 'remove'])],
        ]);

        $tag = Tag::query()->where('type', 'customer')->findOrFail($data['tag_id']);
        $customers = Customer::query()->whereIn('id', array_map('intval', $data['ids']))->get();

        foreach ($customers as $c) {
            $data['action'] === 'add' ? $c->tags()->syncWithoutDetaching([$tag->id]) : $c->tags()->detach($tag->id);
        }

        return back()->with('status', ($data['action'] === 'add' ? 'Added' : 'Removed').' "'.$tag->name.'" for '.$customers->count().' customer(s).');
    }
}
