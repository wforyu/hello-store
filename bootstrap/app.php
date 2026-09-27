<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ThrottleRequests::class.':60,1',
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // PENTING: callback ini MENGGANTIKAN Request::expectsJson(), bukan
        // supplementing-nya (lihat Illuminate\Foundation\Exceptions\Handler
        // ::shouldReturnJson). Kalau hanya 'api/*', maka route web yang dipanggil
        // AJAX — /cart/count, /pos/*, /wishlist/toggle, /compare/toggle,
        // /products/suggestions — akan merender 302 HTML saat session expired
        // atau validasi gagal, sehingga response.json() di frontend throw.
        // Karena itu expectsJson() ikut disertakan; ini hanya menambah perilaku
        // JSON di tempat client memang memintanya lewat header Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
