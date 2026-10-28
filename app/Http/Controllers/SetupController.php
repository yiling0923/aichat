<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Models\Channel;
use App\Models\Service;
use App\Setup\SetupApplier;
use App\Setup\SetupDrafter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Owner only: the getting-started guide. Paste your website or price list, approve the draft, connect, test, go live. */
class SetupController extends Controller
{
    public function index(Request $request, Plans $plans): View
    {
        $business = $request->user()->business;
        $draft = $business->settings['setup_draft'] ?? null;

        return view('admin.setup', [
            'business' => $business,
            'active' => 'setup-guide',
            'draft' => $draft,
            'services' => Service::query()->count(),
            'channels' => Channel::query()->where('active', true)->count(),
            'planState' => $plans->state($business),
            'chatUrl' => route('chat.show', $business->slug),
            'address' => $business->settings['address'] ?? '',
        ]);
    }

    public function draft(Request $request, SetupDrafter $drafter): RedirectResponse
    {
        $data = $request->validate([
            'about' => ['required', 'string', 'min:10', 'max:6000'],
            'address' => ['nullable', 'string', 'max:200'],
        ]);

        $business = $request->user()->business;
        $draft = $drafter->draft($business, $data['about']);

        $settings = (array) $business->settings;
        $settings['setup_draft'] = $draft;
        $settings['address'] = $data['address'] ?? ($settings['address'] ?? '');
        $business->forceFill(['settings' => $settings])->save();

        $found = count($draft['services']) + count($draft['faqs']);

        return back()->with('status', $found > 0
            ? 'Here is a draft. Check it, fix anything wrong, and approve it. Nothing is saved to your business until you approve.'
            : 'We could not find prices in that text. Put one service per line, like "Facial - RM 188 (60 min)", or add them in Booking setup.');
    }

    public function approve(Request $request, SetupApplier $applier): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:200'],
            'services' => ['array', 'max:30'],
            'services.*.include' => ['nullable', 'boolean'],
            'services.*.name_en' => ['required_with:services.*.include', 'nullable', 'string', 'max:80'],
            'services.*.name_zh' => ['nullable', 'string', 'max:80'],
            'services.*.duration_min' => ['nullable', 'integer', 'between:5,480'],
            'services.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'faqs' => ['array', 'max:10'],
            'faqs.*.include' => ['nullable', 'boolean'],
            'faqs.*.title' => ['nullable', 'string', 'max:120'],
            'faqs.*.answer' => ['nullable', 'string', 'max:600'],
        ]);

        $business = $request->user()->business;
        $services = collect($data['services'] ?? [])->filter(fn ($r) => ! empty($r['include']))
            ->map(fn ($r) => ['name_en' => (string) $r['name_en'], 'name_zh' => (string) ($r['name_zh'] ?? ''), 'duration_min' => (int) ($r['duration_min'] ?? 60), 'price' => (float) ($r['price'] ?? 0)])->values()->all();
        $faqs = collect($data['faqs'] ?? [])->filter(fn ($r) => ! empty($r['include']))
            ->map(fn ($r) => ['title' => (string) ($r['title'] ?? ''), 'answer' => (string) ($r['answer'] ?? '')])->values()->all();

        $result = $applier->apply($business, $services, $faqs, $data['address'] ?? null);

        $settings = (array) $business->settings;
        unset($settings['setup_draft']);
        $settings['address'] = $data['address'] ?? ($settings['address'] ?? '');
        $business->forceFill(['settings' => $settings])->save();

        return back()->with('status', "Saved {$result['services']} services and {$result['faqs']} answers, and updated your chat menu.");
    }

    public function discard(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $settings = (array) $business->settings;
        unset($settings['setup_draft']);
        $business->forceFill(['settings' => $settings])->save();

        return back()->with('status', 'Draft discarded.');
    }

    public function goLive(Request $request): RedirectResponse
    {
        $request->user()->business->forceFill(['setup_done_at' => now()])->save();

        return back()->with('status', 'You are live. Share your chat link with customers.');
    }
}
