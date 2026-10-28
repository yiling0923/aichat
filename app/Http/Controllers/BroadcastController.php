<?php

namespace App\Http\Controllers;

use App\Marketing\Broadcasts;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner or manager: write a message and send it to the customers who agreed to receive messages.
 */
class BroadcastController extends Controller
{
    public function index(Request $request, Broadcasts $service): View
    {
        $broadcasts = Broadcast::query()->orderByDesc('id')->limit(50)->get();

        return view('admin.broadcasts', [
            'business' => $request->user()->business,
            'active' => 'broadcasts',
            'broadcasts' => $broadcasts,
            'audienceCounts' => $broadcasts->where('status', 'draft')->mapWithKeys(fn (Broadcast $b) => [$b->id => $service->audience($b)->count()]),
            'tags' => Tag::query()->where('type', 'customer')->orderBy('name')->get(),
            'limit' => Broadcasts::LIMIT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'message_en' => ['required', 'string', 'max:1000'],
            'message_zh' => ['nullable', 'string', 'max:1000'],
            'template_name' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]*$/'],
            'tag_id' => ['nullable', 'integer'],
            'score' => ['nullable', Rule::in(['hot', 'warm', 'cold'])],
            'language' => ['nullable', Rule::in(config('aichat.languages'))],
        ]);

        $tagId = ! empty($data['tag_id']) ? Tag::query()->where('type', 'customer')->findOrFail($data['tag_id'])->id : null;

        Broadcast::create([
            'name' => $data['name'],
            'message' => array_filter(['en' => $data['message_en'], 'zh' => $data['message_zh'] ?? null]),
            'template_name' => ($data['template_name'] ?? null) ?: null,
            'audience' => array_filter(['tag_id' => $tagId, 'score' => $data['score'] ?? null, 'language' => $data['language'] ?? null]),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Saved as a draft. Check the number of people, then press Send.');
    }

    /** How many people a draft with these filters would reach, for the live count in the form. */
    public function audience(Request $request, Broadcasts $service): \Illuminate\Http\JsonResponse
    {
        $draft = new Broadcast(['audience' => array_filter([
            'tag_id' => (int) $request->query('tag_id') ?: null,
            'score' => in_array($request->query('score'), ['hot', 'warm', 'cold'], true) ? $request->query('score') : null,
            'language' => in_array($request->query('language'), config('aichat.languages'), true) ? $request->query('language') : null,
        ])]);

        return response()->json(['count' => min($service->audience($draft)->count(), Broadcasts::LIMIT), 'everyone' => Customer::query()->count()]);
    }

    public function show(Request $request, int $id): View
    {
        $broadcast = Broadcast::query()->findOrFail($id);

        return view('admin.broadcast', [
            'business' => $request->user()->business,
            'active' => 'broadcasts',
            'broadcast' => $broadcast,
            'recipients' => BroadcastRecipient::query()->with('customer')->where('broadcast_id', $broadcast->id)->orderBy('id')->limit(300)->get(),
        ]);
    }

    public function send(int $id, Broadcasts $service): RedirectResponse
    {
        $broadcast = $service->send(Broadcast::query()->findOrFail($id));

        return redirect()->route('broadcasts.show', $broadcast->id)->with('status', 'Sent to '.$broadcast->sent_count.' customer(s), skipped '.$broadcast->skipped_count.', failed '.$broadcast->failed_count.'.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $broadcast = Broadcast::query()->findOrFail($id);
        abort_if($broadcast->status === 'sent', 422, 'A sent broadcast is kept for your records.');
        $broadcast->delete();

        return back()->with('status', 'Draft deleted.');
    }
}
