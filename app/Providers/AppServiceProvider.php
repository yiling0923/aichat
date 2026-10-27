<?php

namespace App\Providers;

use App\Support\CurrentBusiness;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Separate counters: numeric throttle limits share one key per IP, so chat
        // polling could otherwise lock staff out of the login page.
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by('login|'.$r->ip()));
        RateLimiter::for('chat-send', fn (Request $r) => Limit::perMinute(60)->by('chat-send|'.$r->ip()));
        RateLimiter::for('meta-webhook', fn (Request $r) => Limit::perMinute(600)->by('meta|'.$r->ip()));
        RateLimiter::for('chat-poll', fn (Request $r) => Limit::perMinute(240)->by('chat-poll|'.$r->ip()));
    }
}
