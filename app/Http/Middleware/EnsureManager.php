<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For admin pages for owners and managers (library, tags). Use after "staff".
 */
class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canManage(), 403, 'Only an owner or manager can do this.');

        return $next($request);
    }
}
