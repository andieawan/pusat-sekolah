<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Akun dengan password awal (dibuat massal) harus mengganti password sebelum memakai Pusat atau masuk ke aplikasi lain. */
class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && $u->wajib_ganti_password && ! $request->routeIs('profil.password', 'profil.password.ubah', 'logout')) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('profil.password')->with('galat', 'Ini login pertama Anda: ganti password awal terlebih dahulu.');
        }

        return $next($request);
    }
}
