<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsApproved;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA authentication: session cookies + CSRF for the React app.
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        // Behind Render's load balancer: TRUSTED_PROXIES=* (string, not ['*']) so that
        // HTTPS, client IPs (rate limiting) and hosts are read from X-Forwarded-*.
        $proxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(at: match (true) {
            blank($proxies) => null,
            $proxies === '*' => '*',
            default => array_map('trim', explode(',', $proxies)),
        });

        $middleware->alias([
            'role' => EnsureRole::class,
            'approved' => EnsureUserIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
