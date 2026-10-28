<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * POST /api/v1/masuk-lokal   (Basic client_id:client_secret, scope "akun")
 * Body: username, password, ip (IP pengguna di aplikasi, untuk pembatasan percobaan)
 *
 * Dipakai formulir login biasa di aplikasi (Absensi, BK, TU, PKL) agar akun Pusat bisa masuk dengan
 * username & password Pusat tanpa pindah halaman. Password tidak disimpan aplikasi; hanya diperiksa di sini.
 * Balasan sukses = bentuk yang sama dengan /api/sso/userinfo.
 */
class MasukLokalController extends Controller
{
    public function __invoke(Request $request, SsoService $sso): JsonResponse
    {
        $app = $request->attributes->get('aplikasi');
        if (! $app->punyaScope('akun')) {
            return $this->galat('scope_tidak_cukup', 'Aplikasi ini tidak berhak memeriksa login akun.', 403);
        }
        $d = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:200'],
            'ip' => ['nullable', 'ip'],
        ]);
        $username = strtolower(trim($d['username']));
        $kunci = 'lokal|'.$app->id.'|'.$username.'|'.($d['ip'] ?? $request->ip());
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            return $this->galat('terlalu_banyak', 'Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunci) / 60).' menit.', 429);
        }

        $u = User::whereRaw('LOWER(username) = ?', [$username])->first();
        if (! $u || ! $u->aktif || ! Hash::check($d['password'], $u->password)) {
            RateLimiter::hit($kunci, 900);
            Audit::catat('login.lokal.gagal', mb_substr($username, 0, 60), ['aplikasi' => $app->kode]);

            return $this->galat('kredensial_salah', 'Username atau password salah, atau akun nonaktif.', 401);
        }
        RateLimiter::clear($kunci);
        if (! $u->bolehAkses($app)) {
            return $this->galat('tanpa_akses', 'Akun ini belum diberi akses ke aplikasi ini. Hubungi administrator.', 403);
        }
        if ($u->wajib_ganti_password) {
            return $this->galat('wajib_ganti_password', 'Password awal harus diganti dulu. Masuk sekali lewat Pusat Sekolah untuk menggantinya.', 403);
        }

        $u->forceFill(['login_terakhir' => now()])->saveQuietly();
        Audit::catat('login.lokal', $u->uuid, ['aplikasi' => $app->kode]);

        return response()->json($sso->userinfo($u, $app))->header('Cache-Control', 'no-store');
    }

    private function galat(string $kode, string $pesan, int $status): JsonResponse
    {
        return response()->json(['error' => ['kode' => $kode, 'pesan' => $pesan]], $status);
    }
}
