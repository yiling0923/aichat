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

        $business = $user->business;

        abort_if($business === null || ! $business->isActive(), 403, 'No active business for this account.');

        $this->current->set($business);

        return $next($request);
    }
}
