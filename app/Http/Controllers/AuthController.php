<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\SsoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function formLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $username = strtolower(trim($data['username']));

        // Maks. 5 percobaan gagal per username+IP dalam 15 menit.
        $kunci = 'login|'.$username.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        if (! Auth::attempt(['username' => $username, 'password' => $data['password'], 'aktif' => true])) {
            RateLimiter::hit($kunci, 900);
            Audit::catat('login.gagal', mb_substr($username, 0, 60));
            throw ValidationException::withMessages(['username' => 'Username atau password salah, atau akun nonaktif.']);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();
        $request->user()->forceFill(['login_terakhir' => now()])->saveQuietly();
        Audit::catat('login');

        return redirect()->intended(route('portal'));
    }

    public function logout(Request $request, SsoService $sso)
    {
        if ($user = $request->user()) {
            Audit::catat('logout');
            $sso->cabutUser($user); // keluar dari Pusat = semua aplikasi ikut kehilangan token
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
