<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\Audit;
use App\Services\Representasi;
use App\Support\BarisImpor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * PATCH /api/v1/siswa/{uuid}/kontak  {no_hp_ortu?, nama_ortu?, petugas?} — scope "siswa_kontak".
 * Absensi (wali kelas/admin) memperbarui kontak orang tua; perubahan diteruskan ke semua aplikasi lewat webhook.
 */
class SiswaKontakController extends Controller
{
    public function update(Request $r, string $uuid): JsonResponse
    {
        $app = $r->attributes->get('aplikasi');
        if (! $app->punyaScope('siswa_kontak')) {
            return response()->json(['error' => ['kode' => 'scope_tidak_cukup', 'pesan' => 'Aplikasi ini tidak berhak mengubah kontak orang tua (izin siswa_kontak).']], 403);
        }
        $v = Validator::make($r->all(), [
            'no_hp_ortu' => ['nullable', 'string', 'max:30'], 'nama_ortu' => ['nullable', 'string', 'max:150'], 'petugas' => ['nullable', 'string', 'max:100'],
        ]);
        if ($v->fails()) {
            return response()->json(['error' => ['kode' => 'parameter_tidak_valid', 'pesan' => $v->errors()->first()]], 422);
        }
        $s = Siswa::where('uuid', $uuid)->first();
        if (! $s) {
            return response()->json(['error' => ['kode' => 'tidak_ada', 'pesan' => 'Siswa tidak ditemukan di Pusat.']], 404);
        }
        $data = [];
        if ($r->exists('no_hp_ortu')) {
            $data['no_hp_ortu'] = BarisImpor::telepon($r->input('no_hp_ortu'));
        }
        if ($r->exists('nama_ortu')) {
            $data['nama_ortu'] = trim((string) $r->input('nama_ortu')) ?: null;
        }
        $lama = $s->only(array_keys($data));
        $s->update($data);
        Audit::catat('siswa.kontak_api', $s->nis, ['aplikasi' => $app->kode, 'petugas' => $r->input('petugas'), 'dari' => $lama, 'menjadi' => $data]);

        return response()->json(['data' => Representasi::siswa($s->fresh('kelas'), true)]);
    }
}
