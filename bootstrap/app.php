<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        // Автоматическая подгрузка email каждую минуту (проверяем за последний час)
        $schedule->command('emails:fetch --minutes=60')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();

        // Очистка failed jobs каждый час
        $schedule->command('queue:failed')
                ->hourly()
                ->runInBackground();
    })
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
