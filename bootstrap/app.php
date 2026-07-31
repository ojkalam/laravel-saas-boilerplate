<?php

use App\Http\Middleware\EnsureTeamIsSubscribed;
use App\Http\Middleware\ExpireImpersonation;
use App\Http\Middleware\RequiresRole;
use App\Http\Middleware\SetCurrentTeam;
use App\Http\Middleware\SetTeamFromApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ExpireImpersonation::class,
            SetCurrentTeam::class,
        ]);

        $middleware->alias([
            'team.role' => RequiresRole::class,
            'subscribed' => EnsureTeamIsSubscribed::class,
        ]);

        // The team must be bound before the throttle middleware runs so
        // the per-plan API rate limiter can key off the current team.
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            SetTeamFromApiToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // No-op unless SENTRY_LARAVEL_DSN is configured.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
