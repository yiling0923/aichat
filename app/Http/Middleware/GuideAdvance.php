<?php

namespace App\Http\Middleware;

use App\Support\SetupGuide;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-by-step setup guide: when the owner saves the page of the current step and that finishes the step (opening hours set,
 * menu published, ...), they are taken straight to the next step, so they do not have to come back to the guide.
 */
class GuideAdvance
{
    /** Steps that finish with one save. Adding services and trying the chat use the "Done, next step" button instead. */
    private const AUTO = ['business', 'hours', 'menu', 'live'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $key = $request->hasSession() ? (string) $request->session()->get('guide_active', '') : '';
        $user = $request->user();

        if ($key === '' || ! in_array($key, self::AUTO, true) || $user === null || $user->role !== 'owner'
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true) || str_contains($request->path(), 'admin/guide')
            || $request->session()->get('guide_base', false)) {
            return $response;
        }

        $guide = app(SetupGuide::class);
        $business = $user->business;
        $isPublish = $key === 'menu' && $request->is('admin/api/menu/publish');
        $okay = $response->isRedirection() ? ! $request->session()->has('errors') : $response->isSuccessful();

        if (! $okay) {
            return $response;
        }

        $step = collect($guide->steps($business->fresh(), $user))->firstWhere('key', $key);
        if (! $isPublish && ! ($step['done'] ?? false)) {
            return $response;
        }

        if ($isPublish) {
            $settings = (array) $business->settings;
            $settings['guide_done'] = array_filter(((array) ($settings['guide_done'] ?? [])) + [$key => true]);
            $business->update(['settings' => $settings]);
        }
        SetupGuide::forget($business, $user);

        $following = $guide->following($business->fresh(), $user, $key);
        if ($following === null) {
            $request->session()->forget(['guide_active', 'guide_base']);
            $url = route('guide');
            $message = __('All the essential steps are done. You are ready for customers.');
        } else {
            $guide->activate($request, $business->fresh(), $user, $following['key']);
            $url = SetupGuide::target($following);
            $message = __('Step done. Next: :title.', ['title' => __($following['title'])]);
        }

        if ($response->isRedirection()) {
            return redirect($url)->with('status', $message);
        }

        // A save made by the page's own script (the menu): tell the script where to go.
        $response->headers->set('X-Guide-Next', $url);

        return $response;
    }
}
