<?php

namespace App\Http\Middleware;

use App\Models\Aplikasi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi server-ke-server: HTTP Basic dengan client_id:client_secret aplikasi.
 * Aplikasi terautentikasi tersedia sebagai $request->attributes->get('aplikasi').
 */
class AuthAplikasi
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->getUser();
        $secret = $request->getPassword();

        $app = $id ? Aplikasi::where('client_id', $id)->where('aktif', true)->first() : null;
        if (! $app || ! $app->cocokSecret((string) $secret)) {
            return response()->json(
                ['error' => ['kode' => 'klien_tidak_sah', 'pesan' => 'Kredensial aplikasi salah atau aplikasi nonaktif.']],
                401,
                ['WWW-Authenticate' => 'Basic realm="Pusat Sekolah"'],
            );
        }

        $request->attributes->set('aplikasi', $app);

        return $next($request);
    }
}
