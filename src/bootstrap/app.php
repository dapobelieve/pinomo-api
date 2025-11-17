<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.key' => \App\Http\Middleware\ApiKeyAuthMiddleware::class,
            'ramp.request-response' => \Ramp\Logger\Middleware\RequestResponseLoggerMiddleware::class,
            'ramp.apm' => \Ramp\Logger\Middleware\APMMiddleware::class,
        ]);
        
        // Add request logging and APM middleware to web and api groups
        $middleware->web(append: [
            \Ramp\Logger\Middleware\APMMiddleware::class,
            \Ramp\Logger\Middleware\RequestResponseLoggerMiddleware::class,
        ]);
        
        $middleware->api(append: [
            \Ramp\Logger\Middleware\APMMiddleware::class,
            \Ramp\Logger\Middleware\RequestResponseLoggerMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
