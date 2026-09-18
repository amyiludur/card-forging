<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Rules documents are markdown files written back to disk verbatim.
        // Trimming them would drop the trailing newline and make every save
        // look like a change.
        $middleware->trimStrings(except: [
            'body',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
