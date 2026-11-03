<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Mendaftarkan Satpam Role (Hak Akses) + satpam API nasabah
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'customer.active' => \App\Http\Middleware\EnsureActiveCustomer::class,
            'pin.ready' => \App\Http\Middleware\EnsurePinChanged::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Mempertahankan format JSON untuk API (Sangat berguna untuk aplikasi Mobile nanti)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

    })->create();