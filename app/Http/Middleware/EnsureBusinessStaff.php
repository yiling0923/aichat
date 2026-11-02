<?php

namespace App\Http\Middleware;

use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For admin routes: signed-in staff only, working inside their own business.
 */
class EnsureBusinessStaff
{
    public function __construct(private CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        if ($user->active === false) {
            auth()->logout();

            return $request->expectsJson() ? response()->json(['message' => 'Account is switched off.'], 403) : redirect()->route('login');
        }

        if ($user->isPlatformOwner()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'The platform owner has no business inbox.'], 403)
                : redirect()->route('platform.index');
        }

        // A platform owner looking at a business for support is let in even when the owner's email is unconfirmed or the business is paused.
        $supporting = $request->session()->has('support');

        if ($user->email_verified_at === null && ! $supporting) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Confirm your email first.'], 403)
                : redirect()->route('verification.notice');
        }

        $business = $user->business;

        abort_if($business !== null && $business->status === 'paused' && ! $supporting, 403, 'This business is paused'.($business->paused_reason ? ': '.$business->paused_reason : '').'. Please contact support.');

        abort_if($business === null || (! $business->isActive() && ! $supporting), 403, 'No active business for this account.');

        $this->current->set($business);
        app()->setLocale(\App\Support\Lang::valid($user->getAttribute('locale')));
        \Carbon\Carbon::setLocale(app()->getLocale() === 'zh' ? 'zh' : 'en');

        // A read-only support view can look at everything but change nothing.
        if ($request->session()->get('support.mode') === 'read' && ! $request->isMethodSafe()) {
            abort(403, __('This is a read-only support view. Nothing can be changed here. Use "Return to the platform console" and choose "Act as owner" if you need to make a change.'));
        }

        return $next($request);
    }
}
