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

    public function help(Request $request): View
    {
        return view('admin.help', ['business' => $request->user()->business, 'active' => 'help', 'role' => $request->user()->role]);
    }
}
