<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: __DIR__.'/../routes/health.php'
)->withMiddleware(function (Middleware $middleware) {
    // Register global middleware stacks or aliases here.
})->withExceptions(function (Exceptions $exceptions) {
    // Register custom exception handling here.
})->create();
