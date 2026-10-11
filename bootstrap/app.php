<?php

use App\Http\Middleware\AuthAplikasi;
use App\Http\Middleware\HanyaSuperadmin;
use App\Http\Middleware\HeaderKeamanan;
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
    ->withMiddleware(function (Middleware $middleware): void {
        // Di belakang NPMplus: isi TRUSTED_PROXIES dengan IP proxy (mis. 192.168.10.10) atau "*".
        $proxy = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: $proxy === '*' ? '*' : ($proxy ? array_map('trim', explode(',', $proxy)) : null),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->append(HeaderKeamanan::class);

        $middleware->alias([
            'app.client' => AuthAplikasi::class,
            'superadmin' => HanyaSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
