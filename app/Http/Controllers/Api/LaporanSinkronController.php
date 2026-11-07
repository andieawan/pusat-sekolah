<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LaporanSinkron;
use Illuminate\Http\Request;

/** POST /api/v1/sinkron/laporan — aplikasi melapor hasil sinkronnya (berhasil/gagal + ringkasan perubahan). */
class LaporanSinkronController extends Controller
{
    public function __invoke(Request $request)
    {
        $d = $request->validate([
            'ok' => ['required', 'boolean'],
            'pemicu' => ['nullable', 'string', 'max:20'],
            'galat' => ['nullable', 'string', 'max:2000'],
            'hasil' => ['nullable', 'array'],
        ]);
        $n = LaporanSinkron::terima($request->attributes->get('aplikasi'), $d);

        return response()->json(['ok' => true, 'notifikasi' => (bool) $n]);
    }
}
