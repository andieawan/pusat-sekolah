<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HanyaSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->aktif || ! $user->superadmin) {
            abort(403, 'Halaman ini hanya untuk administrator Pusat.');
        }

        return $next($request);
    }
}
