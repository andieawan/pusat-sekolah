<?php

namespace App\Http\Controllers;

use App\Models\Aplikasi;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $semua = Aplikasi::where('aktif', true)->orderBy('nama')->get();
        $aplikasi = $semua->filter(fn ($a) => $user->bolehAkses($a))->values();

        return view('portal', [
            'aplikasi' => $aplikasi->map(fn ($a) => ['model' => $a, 'peran' => $user->peranUntuk($a)]),
        ]);
    }

    /** Buka aplikasi: langsung ke awal SSO aplikasi itu (tidak perlu klik "Masuk lewat Pusat" lagi). */
    public function buka(Request $request, string $kode)
    {
        $app = Aplikasi::where('kode', $kode)->where('aktif', true)->first();
        abort_unless($app && $request->user()->bolehAkses($app), 404);

        return redirect()->away($app->urlMasuk());
    }
}
