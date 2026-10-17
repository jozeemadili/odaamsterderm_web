<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// PHP's built-in dev server ("php artisan serve") defaults max_execution_time
// to 30s, unlike the CLI SAPI's unlimited default. Raise it for local dev so
// a remote database's round-trip latency can't trip request timeouts here —
// never applies under php-fpm/production.
if (PHP_SAPI === 'cli-server') {
    ini_set('max_execution_time', '180');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'internal' => \App\Http\Middleware\EnsureUserIsInternal::class,
            'merchant' => \App\Http\Middleware\EnsureUserIsMerchant::class,
            'api.merchant' => \App\Http\Middleware\EnsureApiUserIsMerchant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
