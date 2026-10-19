<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Akun yang dinonaktifkan langsung keluar pada request berikutnya (tidak menunggu sesi habis). */
class AkunAktif
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && ! Auth::user()->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Akun Anda dinonaktifkan.']);
        }

        return $next($request);
    }
}
