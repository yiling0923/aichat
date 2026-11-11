<?php

namespace App\Http\Controllers;

use App\Support\SetupGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The owner's first-time setup guide, and the Help page for everyone. */
class GuideController extends Controller
{
    public function index(Request $request, SetupGuide $guide): View
    {
        $user = $request->user();
        $business = $user->business;
        $steps = $guide->steps($business, $user);
        SetupGuide::forget($business, $user);

        return view('admin.guide', [
            'business' => $business,
            'active' => 'guide',
            'steps' => $steps,
            'progress' => $guide->progress($business, $user),
        ]);
    }

    /** "Mark as done" / "Not done" for one step (for things the app cannot see by itself). */
    public function mark(Request $request, string $key, SetupGuide $guide): RedirectResponse
    {
        $user = $request->user();
        $business = $user->business;
        abort_unless(in_array($key, $guide->keys($business, $user), true), 404);

        $settings = (array) $business->settings;
        $done = (array) ($settings['guide_done'] ?? []);
        $done[$key] = ! ($done[$key] ?? false);
        $settings['guide_done'] = array_filter($done);
        $business->update(['settings' => $settings]);
        SetupGuide::forget($business, $user);

        return redirect()->route('guide')->withFragment('step-'.$key);
    }

    /** Start the step-by-step mode at one step: the step's page opens with a bar that leads to the next step. */
    public function go(Request $request, string $key, SetupGuide $guide): RedirectResponse
    {
        $user = $request->user();
        $step = collect($guide->steps($user->business, $user))->firstWhere('key', $key);
        abort_if($step === null, 404);
        $request->session()->put('guide_active', $key);

        // The customer chat page has no staff menu, so that step is shown on the guide page with a button that opens the chat.
        return $key === 'chat' ? redirect()->route('guide') : redirect($step['url']);
    }

    /** "Done, next step": ticks the step (if the app cannot see it by itself) and opens the next essential step that is not done. */
    public function next(Request $request, SetupGuide $guide): RedirectResponse
    {
        $user = $request->user();
        $business = $user->business;
        $key = (string) $request->session()->get('guide_active', '');
        $steps = collect($guide->steps($business, $user));
        $current = $steps->firstWhere('key', $key);
        abort_if($current === null, 404);

        if (! $current['done']) {
            $settings = (array) $business->settings;
            $settings['guide_done'] = array_filter(((array) ($settings['guide_done'] ?? [])) + [$key => true]);
            $business->update(['settings' => $settings]);
            SetupGuide::forget($business, $user);
            $steps = collect($guide->steps($business->fresh(), $user));
        }

        $order = $steps->reject(fn ($s) => $s['optional'])->values();
        $at = $order->search(fn ($s) => $s['key'] === $key);
        $following = $order->slice(($at === false ? -1 : $at) + 1)->first(fn ($s) => ! $s['done']);

        if ($following === null) {
            $request->session()->forget('guide_active');

            return redirect()->route('guide')->with('status', __('All the essential steps are done. You are ready for customers.'));
        }

        $request->session()->put('guide_active', $following['key']);

        return $following['key'] === 'chat' ? redirect()->route('guide') : redirect($following['url']);
    }

    public function exit(Request $request): RedirectResponse
    {
        $request->session()->forget('guide_active');

        return redirect()->route('guide');
    }

    public function help(Request $request): View
    {
        return view(app()->getLocale() === 'zh' ? 'admin.help-zh' : 'admin.help', ['business' => $request->user()->business, 'active' => 'help', 'role' => $request->user()->role]);
    }
}
