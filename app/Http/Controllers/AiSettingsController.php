<?php

namespace App\Http\Controllers;

use App\Ai\AiAssistant;
use App\Ai\Knowledge;
use App\Models\AiUsage;
use App\Models\KnowledgeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner or manager: choose how the AI helps, teach it extra answers, try it, and watch the usage.
 */
class AiSettingsController extends Controller
{
    public const MODES = ['off', 'suggest', 'approve', 'auto'];

    public function index(Request $request, AiAssistant $ai, Knowledge $knowledge): View
    {
        $business = $request->user()->business;
        $month = AiUsage::query()->where('created_at', '>=', now()->startOfMonth());
        $calls = (clone $month)->count();
        $in = (int) (clone $month)->where('provider', 'claude')->sum('input_tokens');
        $out = (int) (clone $month)->where('provider', 'claude')->sum('output_tokens');
        $provider = (string) config('aichat.ai.provider');

        return view('admin.ai', [
            'business' => $business,
            'active' => 'ai',
            'provider' => $provider,
            'keySet' => (string) config('aichat.ai.api_key') !== '',
            'model' => (string) config('aichat.ai.model'),
            'mode' => $ai->mode($business),
            'instructions' => $knowledge->instructions($business),
            'cap' => (int) ($business->settings['ai']['monthly_cap'] ?? config('aichat.ai.default_monthly_cap')),
            'calls' => $calls,
            'blocked' => (clone $month)->where('status', 'blocked')->count(),
            'failed' => (clone $month)->where('status', 'error')->count(),
            'tokensIn' => $in,
            'tokensOut' => $out,
            'cost' => $in / 1_000_000 * (float) config('aichat.ai.price_input_per_million') + $out / 1_000_000 * (float) config('aichat.ai.price_output_per_million'),
            'entries' => KnowledgeEntry::query()->orderBy('id')->get(),
            'knowledgeText' => $knowledge->build($business),
            'recent' => AiUsage::query()->orderByDesc('id')->limit(8)->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(self::MODES)],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'monthly_cap' => ['required', 'integer', 'between:0,100000'],
        ]);

        $business = $request->user()->business;
        $settings = (array) $business->settings;
        $settings['ai'] = ['mode' => $data['mode'], 'instructions' => $data['instructions'] ?? '', 'monthly_cap' => (int) $data['monthly_cap']];
        $business->update(['settings' => $settings]);

        return back()->with('status', 'Saved.');
    }

    /** The test box: ask like a customer would. Nothing is sent to anyone. */
    public function preview(Request $request, AiAssistant $ai): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:500']]);
        $answer = $ai->preview($request->user()->business, $data['text']);

        return $answer === null
            ? response()->json(['error' => $ai->lastNote ?? 'No answer.'], 422)
            : response()->json(['reply' => $answer->text, 'intent' => $answer->intent, 'mood' => $answer->mood, 'wants_human' => $answer->wantsHuman]);
    }

    public function storeEntry(Request $request): RedirectResponse
    {
        KnowledgeEntry::create($this->entryData($request));

        return back()->with('status', 'Answer added. The AI can use it from now on.');
    }

    public function updateEntry(Request $request, int $id): RedirectResponse
    {
        KnowledgeEntry::query()->findOrFail($id)->update($this->entryData($request));

        return back()->with('status', 'Answer saved.');
    }

    public function destroyEntry(int $id): RedirectResponse
    {
        KnowledgeEntry::query()->findOrFail($id)->delete();

        return back()->with('status', 'Answer deleted.');
    }

    /** @return array<string, mixed> */
    private function entryData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'answer_en' => ['required', 'string', 'max:1500'],
            'answer_zh' => ['nullable', 'string', 'max:1500'],
        ]);

        return ['title' => $data['title'], 'answer' => array_filter(['en' => $data['answer_en'], 'zh' => $data['answer_zh'] ?? null]), 'active' => $request->boolean('active', true)];
    }
}
