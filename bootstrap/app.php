<?php

use App\Http\Middleware\EnsureBusinessStaff;
use App\Http\Middleware\ResolveBusinessFromSlug;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'business.slug' => ResolveBusinessFromSlug::class,
            'staff' => EnsureBusinessStaff::class,
            'owner' => \App\Http\Middleware\EnsureOwner::class,
            'platform' => \App\Http\Middleware\EnsurePlatformOwner::class,
            'manager' => \App\Http\Middleware\EnsureManager::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // ngrok (and later a real server) sits in front of the app.
        $middleware->trustProxies(at: '*');

        // The visitor chat API uses a random token, not a login cookie,
        // and may later be embedded on other websites.
        $middleware->validateCsrfTokens(except: ['api/chat/*', 'webhook/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
