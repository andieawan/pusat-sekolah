<?php

namespace App\Http\Controllers;

use App\Models\Aplikasi;
use App\Services\Audit;
use App\Services\SsoService;
use App\Support\RedirectUri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Login tunggal (alur "authorization code", mirip OAuth2/OIDC sederhana).
 *
 *  1. Aplikasi mengarahkan browser ke GET /sso/authorize?client_id&redirect_uri&state[&code_challenge]
 *  2. Pengguna login di Pusat (jika belum), Pusat mengarahkan balik ke redirect_uri?code&state
 *  3. Server aplikasi POST /api/sso/token (client_id:client_secret) menukar code -> access_token + profil
 *  4. Aplikasi membuat sesi lokal; boleh memeriksa ulang lewat GET /api/sso/userinfo
 */
class SsoController extends Controller
{
    public function otorisasi(Request $request, SsoService $sso)
    {
        $q = $request->query();
        $v = Validator::make($q, [
            'client_id' => ['required', 'string', 'max:60'],
            'redirect_uri' => ['required', 'string', 'max:500'],
            'response_type' => ['required', 'in:code'],
            'state' => ['required', 'string', 'max:200'],
            'code_challenge' => ['nullable', 'string', 'min:43', 'max:128'],
            'code_challenge_method' => ['nullable', 'in:S256'],
        ]);

        $app = Aplikasi::where('client_id', (string) ($q['client_id'] ?? ''))->where('aktif', true)->first();
        $redirectUri = (string) ($q['redirect_uri'] ?? '');
        $tantangan = $q['code_challenge'] ?? null;

        // Parameter salah / redirect_uri tidak terdaftar: JANGAN redirect (anti open-redirect), tampilkan galat.
        if ($v->fails()
            || ! $app
            || ! RedirectUri::cocok($redirectUri, $app->redirect_uris ?? [])
            || ($tantangan !== null && ($q['code_challenge_method'] ?? '') !== 'S256')) {
            return response()->view('sso.galat', [
                'pesan' => 'Permintaan login tidak valid. Hubungi administrator aplikasi yang Anda buka.',
            ], 400);
        }

        if (! Auth::check()) {
            return redirect()->guest(route('login')); // setelah login kembali ke URL ini
        }

        $user = Auth::user();
        if ($user->wajib_ganti_password) {
            $request->session()->put('url.intended', $request->fullUrl()); // setelah ganti password langsung lanjut ke aplikasi

            return redirect()->route('profil.password')->with('galat', 'Ini login pertama Anda: ganti password awal terlebih dahulu.');
        }
        if (! $user->bolehAkses($app)) {
            Audit::catat('sso.ditolak', $app->kode);

            return response()->view('sso.tanpa-akses', ['app' => $app], 403);
        }

        $kode = $sso->buatKode($user, $app, $redirectUri, $tantangan);
        Audit::catat('sso.otorisasi', $app->kode);

        return redirect()->away(RedirectUri::tambahQuery($redirectUri, ['code' => $kode, 'state' => $q['state']]));
    }

    public function token(Request $request, SsoService $sso): JsonResponse
    {
        [$id, $secret] = $this->kredensialKlien($request);
        $app = $id ? Aplikasi::where('client_id', $id)->where('aktif', true)->first() : null;
        if (! $app || ! $app->cocokSecret((string) $secret)) {
            return $this->galat('klien_tidak_sah', 'Kredensial aplikasi salah atau aplikasi nonaktif.', 401);
        }
        if ($request->input('grant_type') !== 'authorization_code') {
            return $this->galat('grant_tidak_didukung', 'grant_type harus "authorization_code".', 400);
        }

        $hasil = $sso->tukarKode(
            $app,
            (string) $request->input('code'),
            (string) $request->input('redirect_uri'),
            $request->input('code_verifier'),
        );
        if (! $hasil) {
            return $this->galat('kode_tidak_valid', 'Kode salah, kedaluwarsa, sudah dipakai, atau akun tidak berhak.', 400);
        }

        return response()->json($hasil)->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    public function userinfo(Request $request, SsoService $sso): JsonResponse
    {
        $plain = $request->bearerToken();
        $tok = $plain ? $sso->tokenValid($plain) : null;
        if (! $tok) {
            return $this->galat('token_tidak_valid', 'Token tidak valid atau kedaluwarsa.', 401);
        }
        if (! $tok->aplikasi->aktif || ! $tok->user->bolehAkses($tok->aplikasi)) {
            return $this->galat('akses_dicabut', 'Akun tidak lagi berhak mengakses aplikasi ini.', 403);
        }

        return response()->json($sso->userinfo($tok->user, $tok->aplikasi))->header('Cache-Control', 'no-store');
    }

    public function cabut(Request $request, SsoService $sso): JsonResponse
    {
        $plain = $request->bearerToken();
        if ($plain && ($tok = $sso->tokenValid($plain))) {
            $sso->cabutToken($tok);
        }

        return response()->json(['ok' => true]); // selalu ok: tidak membocorkan keabsahan token
    }

    /** Keluar dari Pusat (dan semua aplikasi) lalu kembali ke aplikasi asal bila URL-nya se-asal. */
    public function keluar(Request $request, SsoService $sso)
    {
        if ($user = $request->user()) {
            Audit::catat('logout.sso');
            $sso->cabutUser($user);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $app = Aplikasi::where('client_id', (string) $request->query('client_id'))->where('aktif', true)->first();
        $tujuan = (string) $request->query('post_logout_redirect_uri', '');
        if ($app && $tujuan !== '' && RedirectUri::seAsal($tujuan, $app->redirect_uris ?? [])) {
            return redirect()->away($tujuan);
        }

        return redirect()->route('login');
    }

    /** @return array{0:?string,1:?string} */
    private function kredensialKlien(Request $request): array
    {
        if ($request->getUser()) {
            return [$request->getUser(), $request->getPassword()];
        }

        return [$request->input('client_id'), $request->input('client_secret')];
    }

    private function galat(string $kode, string $pesan, int $status): JsonResponse
    {
        return response()->json(['error' => ['kode' => $kode, 'pesan' => $pesan]], $status)
            ->header('Cache-Control', 'no-store');
    }
}
