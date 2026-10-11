<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons. CSP tanpa inline script/style (semua aset dari domain sendiri).
 * Sengaja TIDAK memakai form-action: Chrome ikut memblokir redirect SSO ke aplikasi lain setelah login.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $res = $next($request);

        $res->headers->set('X-Content-Type-Options', 'nosniff');
        $res->headers->set('X-Frame-Options', 'DENY');
        $res->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $res->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $res->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
            ."base-uri 'self'; frame-ancestors 'none'; object-src 'none'",
        );
        if ($request->isSecure()) {
            $res->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if (! $res->headers->has('Cache-Control') || $request->is('api/sso/*', 'sso/*', 'login', 'admin*')) {
            $res->headers->set('Cache-Control', 'no-store');
        }

        return $res;
    }
}
