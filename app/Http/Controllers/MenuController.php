<?php

namespace App\Http\Controllers;

use App\Bot\BotEngine;
use App\Bot\DefaultFlow;
use App\Bot\Flow;
use App\Bot\FlowRepository;
use App\Bot\FlowValidator;
use App\Models\BotEvent;
use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The menu builder (owner only). Edits a draft; Publish makes it live.
 */
class MenuController extends Controller
{
    public function __construct(private FlowRepository $flows, private FlowValidator $validator) {}

    public function index(Request $request): View
    {
        return view('admin.menu', ['business' => $request->user()->business]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate(['flow' => ['required', 'array']]);
        $record = $this->flows->forBusiness($request->user()->business);
        $record->update(['draft' => $this->validator->clean($data['flow'])]);

        return response()->json($this->payload($request));
    }

    public function publish(Request $request): JsonResponse
    {
        $record = $this->flows->forBusiness($request->user()->business);
        $result = $this->validator->validate($record->draft);

        if ($result['errors'] !== []) {
            return response()->json($this->payload($request), 422);
        }

        $record->update(['published' => $record->draft, 'published_at' => now()]);

        return response()->json($this->payload($request));
    }

    public function reset(Request $request): JsonResponse
    {
        $record = $this->flows->forBusiness($request->user()->business);
        $record->update(['draft' => DefaultFlow::make()]);

        return response()->json($this->payload($request));
    }

    /** Try the draft menu in a throwaway chat. Leads and handovers are not saved. */
    public function preview(Request $request, BotEngine $bot): JsonResponse
    {
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:1000'],
            'option_id' => ['nullable', 'string', 'max:60'],
            'restart' => ['nullable', 'boolean'],
        ]);

        $key = 'menu_preview_'.$request->user()->id;
        $conversation = ! $request->boolean('restart') && session()->has($key)
            ? Conversation::query()->where('channel', 'preview')->find(session($key))
            : null;

        if ($conversation === null) {
            $conversation = Conversation::startFor('preview');
            session([$key => $conversation->id]);
        }

        $incoming = $conversation->addMessage('in', 'customer', (string) ($data['text'] ?? ''));
        $messages = $bot->respond($conversation, $incoming, $data['option_id'] ?? null, $this->flows->draft($request->user()->business));

        return response()->json(['messages' => array_map(fn ($m) => $m->toChatArray(), $messages)]);
    }

    public function stats(Request $request): JsonResponse
    {
        $since = now()->subDays(30);

        $count = fn (string $type) => BotEvent::query()->where('type', $type)->where('created_at', '>=', $since)->count();

        $options = BotEvent::query()
            ->where('type', 'option')->where('created_at', '>=', $since)
            ->select('node', 'option_id', DB::raw('count(*) as taps'))
            ->groupBy('node', 'option_id')->orderByDesc('taps')->limit(8)->get();

        return response()->json([
            'days' => 30,
            'chats' => Conversation::query()->where('channel', '!=', 'preview')->where('created_at', '>=', $since)->count(),
            'leads' => $count('lead'),
            'handovers' => $count('handover'),
            'top_options' => $options,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $record = $this->flows->forBusiness($request->user()->business);
        $check = $this->validator->validate($record->draft);

        return [
            'flow' => $record->draft,
            'errors' => $check['errors'],
            'warnings' => $check['warnings'],
            'unpublished' => $record->hasUnpublishedChanges(),
            'published_at' => $record->published_at?->toIso8601String(),
            'languages' => config('aichat.languages'),
            'fields' => FlowValidator::FIELDS,
            'types' => FlowValidator::TYPES,
        ];
    }
}
