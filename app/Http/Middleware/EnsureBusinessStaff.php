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

        if ($user->email_verified_at === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Confirm your email first.'], 403)
                : redirect()->route('verification.notice');
        }

        $business = $user->business;

        abort_if($business !== null && $business->status === 'paused', 403, 'This business is paused'.($business->paused_reason ? ': '.$business->paused_reason : '').'. Please contact support.');

        abort_if($business === null || ! $business->isActive(), 403, 'No active business for this account.');

        $this->current->set($business);
        app()->setLocale(\App\Support\Lang::valid($user->getAttribute('locale')));
        \Carbon\Carbon::setLocale(app()->getLocale() === 'zh' ? 'zh' : 'en');

        return $next($request);
    }
}
