<?php

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
            'admin' => function ($request, $next) {
                if (!$request->user() || !$request->user()->isAdmin()) {
                    abort(403, 'Недостаточно прав доступа');
                }
                return $next($request);
            },
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
