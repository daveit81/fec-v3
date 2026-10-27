<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // No middleware group at all: see routes/stateless.php.
            Route::group([], base_path('routes/stateless.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The ported desks post Joomla-style forms: the token travels as the
        // field NAME (JHtml form.token) and the legacy controllers verify it
        // through JRequest::checkToken(), so Laravel's own check is skipped there.
        $middleware->validateCsrfTokens(except: array_merge(
            ['index.php'],
            array_map(fn ($p) => trim((string) $p, '/'), (array) config('fec.legacy.desk_paths', [])),
        ));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
