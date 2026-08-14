<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Disable CSRF for OneKhusa webhooks
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/payments',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();