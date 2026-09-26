<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if ($request->is('admin*') === false && $request->is('livewire*') === false) {
            $response->headers->set('Referrer-Policy', $this->referrerPolicy($request));
        }

        return $response;
    }

    private function referrerPolicy(Request $request): string
    {
        // token guest order ada di query string — jangan bocorkan lewat Referer
        if ($request->routeIs('guest-orders.*') || $request->is('api/guest-orders/*')) {
            return 'no-referrer';
        }

        return 'strict-origin-when-cross-origin';
    }
}
