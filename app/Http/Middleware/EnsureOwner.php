<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For owner-only admin pages such as the menu builder. Use after "staff".
 */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'owner', 403, 'Only the business owner can do this.');

        return $next($request);
    }
}
