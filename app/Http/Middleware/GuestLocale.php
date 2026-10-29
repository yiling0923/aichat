<?php

namespace App\Http\Middleware;

use App\Support\Lang;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public pages (landing, sign-in, sign-up, legal) follow the language chosen with the 中文 / English switch, remembered in a cookie. Staff pages use the person's own setting instead. */
class GuestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*', 'chat/*', 'api/*', 'webhook/*')) {
            $locale = (string) $request->cookie('locale');
            if (array_key_exists($locale, Lang::LOCALES)) {
                app()->setLocale($locale);
                \Carbon\Carbon::setLocale($locale === 'zh' ? 'zh' : 'en');
            }
        }

        return $next($request);
    }
}
