<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel administrasi Pusat: superadmin boleh semua; Operator TU hanya halaman di OPERATOR_TU
 * dan hanya untuk data siswa, guru & staf, serta kelas (tanpa hapus).
 */
class AdminPusat
{
    public const OPERATOR_TU = [
        'admin.dashboard',
        'admin.master.index', 'admin.master.create', 'admin.master.store', 'admin.master.edit', 'admin.master.update',
        'admin.impor.form', 'admin.impor.templat', 'admin.impor.pratinjau', 'admin.impor.terapkan',
        'admin.profil.edit', 'admin.profil.update',
        'admin.akun.massal', 'admin.akun.massal.buat', 'admin.akun.siswa', 'admin.akun.siswa.buat',
    ];

    public const SUMBER_TU = ['siswa', 'pegawai', 'kelas'];

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u || ! $u->aktif || (! $u->superadmin && ! $u->operator_tu)) {
            abort(403, 'Halaman ini hanya untuk administrator Pusat.');
        }
        if (! $u->superadmin && ! self::bolehTu($request)) {
            abort(403, 'Bagian ini hanya bisa diubah administrator Pusat.');
        }

        return $next($request);
    }

    public static function bolehTu(Request $request): bool
    {
        if (! in_array($request->route()?->getName(), self::OPERATOR_TU, true)) {
            return false;
        }
        $sumber = $request->route('sumber') ?? $request->input('sumber');

        return $sumber === null || in_array($sumber, self::SUMBER_TU, true);
    }
}
