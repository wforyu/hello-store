<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SerializeSessionRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        // Anti race keranjang POS: request satu session jalan berurutan.
        // Append = berjalan SETELAH StartSession, jadi session sudah tersedia.
        $middleware->web(append: [
            SerializeSessionRequests::class,
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

        // Server POS wajib bicara. 500 polos "Server Error" sudah berbulan-bulan
        // membuat diagnosis mustahil di InfinityFree (tanpa SSH, tanpa akses log).
        // Untuk request /pos/* ber-Accept-JSON dari admin/kasir yang sudah login,
        // kirim pesan exception aslinya — banner merah POS membaca data.error dan
        // menampilkannya langsung di layar kasir.
        // 4xx (validasi, NotFound, CSRF) dibiarkan lewat jalur default, dan untuk
        // pengunjung yang belum login/tidak berhak, exception tetap dirender
        // normal tanpa membocorkan detail.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('pos/*') || ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException || $e instanceof HttpException) {
                return null;
            }

            $user = $request->user();
            if (! $user || ! in_array($user->role, ['admin', 'cashier'], true)) {
                return null;
            }

            return response()->json([
                'error' => class_basename($e).': '.$e->getMessage(),
            ], 500);
        });
    })->create();
