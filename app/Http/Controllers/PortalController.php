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
}
