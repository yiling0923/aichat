<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For public chat routes: the business comes from the {slug} in the URL.
 */
class ResolveBusinessFromSlug
{
    public function __construct(private CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $business = Business::query()
            ->where('slug', (string) $request->route('slug'))
            ->where('status', 'active')
            ->first();

        abort_if($business === null, 404);

        $this->current->set($business);

        return $next($request);
    }
}
