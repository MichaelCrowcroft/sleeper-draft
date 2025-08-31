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
        // API rate limiting for MCP Actions endpoints
        $middleware->alias([
            'api.throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
