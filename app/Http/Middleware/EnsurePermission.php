<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For staff pages that the owner can switch on or off per person: perm:schedule, or perm:tags,library for "any of these". Use after "staff".
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        $user = $request->user();
        $allowed = $user !== null && collect($keys)->contains(fn (string $key) => $user->allows($key));

        if (! $allowed) {
            $message = __('You do not have access to this. Ask the business owner to switch it on for you (Settings > Team).');

            abort_if($request->expectsJson(), 403, $message);
            abort(403, $message);
        }

        return $next($request);
    }
}
