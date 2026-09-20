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
        // Style Push-addonet registrerer selv sin InjectAssets-middleware.
        // Projektets egen InjectStyles ledte efter `<!-- __STYLES__ -->`, som
        // ingen tag udskriver længere — addonets yield_minified skriver
        // `<!-- __YIELD_STYLES__ -->`, og det er addonets middleware der fylder
        // den ud. To middlewares om samme opgave er én for meget.

        $middleware->web(append: [
            \App\Http\Middleware\RecordPageViews::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
