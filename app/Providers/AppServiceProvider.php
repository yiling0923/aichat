<?php

namespace App\Providers;

use App\Support\CurrentBusiness;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One shared "which business are we working for" holder per request.
        $this->app->singleton(CurrentBusiness::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a tunnel (ngrok, localhost.run, Cloudflare) the app only sees plain http, so it would
        // write http:// into forms and links. When APP_URL is https and the visitor arrives on a real
        // host name (not localhost), always build https addresses.
        if (! $this->app->runningInConsole() && self::shouldForceHttps((string) config('app.url'), request()->getHost())) {
            URL::forceScheme('https');
        }

        // Separate counters: numeric throttle limits share one key per IP, so chat
        // polling could otherwise lock staff out of the login page.
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by('login|'.$r->ip()));
        RateLimiter::for('signup', fn (Request $r) => Limit::perHour(5)->by('signup|'.$r->ip()));
        RateLimiter::for('chat-send', fn (Request $r) => Limit::perMinute(60)->by('chat-send|'.$r->ip()));
        RateLimiter::for('meta-webhook', fn (Request $r) => Limit::perMinute(600)->by('meta|'.$r->ip()));
        RateLimiter::for('chat-poll', fn (Request $r) => Limit::perMinute(240)->by('chat-poll|'.$r->ip()));
    }

    /** True when APP_URL is https and the visitor came in through a real host name (a tunnel), not localhost. */
    public static function shouldForceHttps(string $appUrl, string $host): bool
    {
        return str_starts_with($appUrl, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
