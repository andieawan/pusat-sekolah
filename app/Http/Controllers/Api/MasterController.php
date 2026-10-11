<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Representasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * GET /api/v1/master/{sumber}   (Basic client_id:client_secret)
 *   sumber: tahun-ajaran | jurusan | kelas | siswa | pegawai
 *   ?updated_since=ISO8601  -> hanya yang berubah sejak itu, TERMASUK yang sudah dihapus
 *                              (field "dihapus" terisi) agar salinan lokal bisa ikut menghapus
 *   ?per_page=1..500 (bawaan 200)  ?page=N  ?status=aktif|lulus|pindah|keluar (siswa)
 */
class MasterController extends Controller
{
    /** sumber => [model, relasi yang di-eager-load] */
    private const PETA = [
        'tahun-ajaran' => [TahunAjaran::class, []],
        'jurusan' => [Jurusan::class, []],
        'kelas' => [Kelas::class, ['jurusan', 'tahunAjaran', 'wali']],
        'siswa' => [Siswa::class, ['kelas']],
        'pegawai' => [Pegawai::class, []],
    ];

    public function index(Request $request, string $sumber): JsonResponse
    {
        $app = $request->attributes->get('aplikasi');
        $kunci = str_replace('-', '_', $sumber);
        $level = $app->aksesSumber($kunci);
        if ($level === null) {
            return $this->galat('scope_tidak_cukup', "Aplikasi ini tidak berhak membaca \"{$sumber}\".", 403);
        }

        $v = Validator::make($request->query(), [
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:aktif,lulus,pindah,keluar'],
        ]);
        if ($v->fails()) {
            return response()->json(['error' => [
                'kode' => 'parameter_tidak_valid',
                'pesan' => $v->errors()->first(),
                'rincian' => $v->errors(),
            ]], 422);
        }

        [$model, $relasi] = self::PETA[$sumber];
        $waktuServer = now(); // dicatat SEBELUM query: pakai ini (dikurangi beberapa detik) sebagai updated_since berikutnya
        $q = $model::query()->with($relasi);

        if ($request->filled('updated_since')) {
            $q->withTrashed()->where('updated_at', '>', \Illuminate\Support\Carbon::parse($request->query('updated_since')));
        }
        if ($sumber === 'siswa' && $request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        $hal = $q->orderBy('id')->paginate((int) $request->query('per_page', 200));

        return response()->json([
            'data' => $hal->getCollection()->map(fn ($m) => Representasi::dari($m, $level === 'lengkap'))->values(),
            'meta' => [
                'halaman' => $hal->currentPage(),
                'per_halaman' => $hal->perPage(),
                'total' => $hal->total(),
                'halaman_terakhir' => $hal->lastPage(),
                'waktu_server' => $waktuServer->toIso8601String(),
            ],
        ]);
    }

    private function galat(string $kode, string $pesan, int $status): JsonResponse
    {
        return response()->json(['error' => ['kode' => $kode, 'pesan' => $pesan]], $status);
    }
}
