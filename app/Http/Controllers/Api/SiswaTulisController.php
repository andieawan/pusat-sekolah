<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\Audit;
use App\Services\Representasi;
use App\Support\BarisImpor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Penulisan data siswa oleh aplikasi lain (scope "siswa_tulis", untuk mutasi di Tata Usaha).
 * Sengaja sempit: tambah siswa (mutasi masuk) dan ubah status/kelas (mutasi keluar, pindah kelas).
 * Perubahan lain (nama, NIS, data pribadi) tetap lewat panel Pusat. Webhook ke aplikasi lain terkirim otomatis.
 *
 *   POST  /api/v1/siswa          {nis, nama, jenis_kelamin L|P, kelas_uuid, nisn?, tempat_lahir?, tanggal_lahir?, no_hp?, no_hp_ortu?, nama_ortu?, petugas?}
 *   PATCH /api/v1/siswa/{uuid}   {status? aktif|lulus|pindah|keluar, kelas_uuid?, petugas?}
 */
class SiswaTulisController extends Controller
{
    private function galat(string $kode, string $pesan, int $status, array $rincian = []): JsonResponse
    {
        return response()->json(['error' => ['kode' => $kode, 'pesan' => $pesan] + ($rincian ? ['rincian' => $rincian] : [])], $status);
    }

    private function izin(Request $r): ?JsonResponse
    {
        return $r->attributes->get('aplikasi')->punyaScope('siswa_tulis')
            ? null : $this->galat('scope_tidak_cukup', 'Aplikasi ini tidak berhak mengubah data siswa.', 403);
    }

    private function catat(Request $r, string $aksi, Siswa $s, array $detail): void
    {
        Audit::catat($aksi, $s->uuid, $detail + [
            'aplikasi' => $r->attributes->get('aplikasi')->kode,
            'petugas' => mb_substr(trim((string) $r->input('petugas', '')), 0, 100) ?: null,
        ]);
    }

    public function store(Request $r): JsonResponse
    {
        if ($e = $this->izin($r)) {
            return $e;
        }
        $v = Validator::make($r->all(), [
            'nis' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/.\-]+$/'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'kelas_uuid' => ['required', 'string', 'max:36'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date_format:Y-m-d'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'no_hp_ortu' => ['nullable', 'string', 'max:30'],
            'nama_ortu' => ['nullable', 'string', 'max:150'],
            'petugas' => ['nullable', 'string', 'max:100'],
        ]);
        if ($v->fails()) {
            return $this->galat('parameter_tidak_valid', $v->errors()->first(), 422, $v->errors()->toArray());
        }
        $d = $v->validated();
        $kelas = Kelas::where('uuid', $d['kelas_uuid'])->first();
        if (! $kelas) {
            return $this->galat('kelas_tidak_ada', 'Kelas tidak ditemukan di Pusat.', 422);
        }
        $nis = trim($d['nis']);
        if (Siswa::where('nis', $nis)->exists()) {
            return $this->galat('nis_sudah_ada', "NIS {$nis} sudah dipakai siswa lain.", 409);
        }
        $data = [
            'nis' => $nis, 'nama' => trim($d['nama']), 'jenis_kelamin' => $d['jenis_kelamin'], 'kelas_id' => $kelas->id, 'status' => 'aktif',
            'nisn' => preg_replace('/\D/', '', (string) ($d['nisn'] ?? '')) ?: null, 'tempat_lahir' => $d['tempat_lahir'] ?? null,
            'tanggal_lahir' => $d['tanggal_lahir'] ?? null, 'no_hp' => BarisImpor::telepon($d['no_hp'] ?? null),
            'no_hp_ortu' => BarisImpor::telepon($d['no_hp_ortu'] ?? null), 'nama_ortu' => $d['nama_ortu'] ?? null,
        ];

        $s = DB::transaction(function () use ($nis, $data) {
            // NIS milik siswa yang pernah dihapus: pulihkan (riwayat di aplikasi lain tetap tersambung lewat uuid).
            $lama = Siswa::onlyTrashed()->where('nis', $nis)->first();
            if ($lama) {
                $lama->restore();
                $lama->update($data);

                return $lama;
            }

            return Siswa::create($data);
        });
        $this->catat($r, 'siswa.dibuat_api', $s, ['nis' => $s->nis]);

        return response()->json(['data' => Representasi::siswa($s->fresh('kelas'), true)], 201);
    }

    public function update(Request $r, string $uuid): JsonResponse
    {
        if ($e = $this->izin($r)) {
            return $e;
        }
        $v = Validator::make($r->all(), [
            'status' => ['nullable', 'in:'.implode(',', BarisImpor::STATUS_SISWA)],
            'kelas_uuid' => ['nullable', 'string', 'max:36'],
            'petugas' => ['nullable', 'string', 'max:100'],
        ]);
        if ($v->fails()) {
            return $this->galat('parameter_tidak_valid', $v->errors()->first(), 422, $v->errors()->toArray());
        }
        $s = Siswa::where('uuid', $uuid)->first();
        if (! $s) {
            return $this->galat('tidak_ditemukan', 'Siswa tidak ditemukan di Pusat.', 404);
        }
        $data = [];
        if ($r->filled('status')) {
            $data['status'] = $r->input('status');
        }
        if ($r->filled('kelas_uuid')) {
            $kelas = Kelas::where('uuid', $r->input('kelas_uuid'))->first();
            if (! $kelas) {
                return $this->galat('kelas_tidak_ada', 'Kelas tidak ditemukan di Pusat.', 422);
            }
            $data['kelas_id'] = $kelas->id;
        }
        if (! $data) {
            return $this->galat('parameter_tidak_valid', 'Kirim status dan/atau kelas_uuid.', 422);
        }
        $lama = ['status' => $s->status, 'kelas_id' => $s->kelas_id];
        $s->update($data);
        $this->catat($r, 'siswa.diubah_api', $s, ['dari' => $lama, 'menjadi' => $data]);

        return response()->json(['data' => Representasi::siswa($s->fresh('kelas'), true)]);
    }
}
