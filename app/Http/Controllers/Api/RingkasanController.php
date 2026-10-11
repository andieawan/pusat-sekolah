<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** GET /api/v1/ringkasan — angka agregat saja (tanpa data pribadi), cocok untuk website publik. */
class RingkasanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->attributes->get('aplikasi')->punyaScope('ringkasan')) {
            return response()->json(['error' => ['kode' => 'scope_tidak_cukup', 'pesan' => 'Aplikasi ini tidak berhak membaca ringkasan.']], 403);
        }

        $data = Cache::remember('ringkasan.v1', 300, function () {
            $tahun = TahunAjaran::aktif();
            $aktif = Siswa::where('status', 'aktif');

            return [
                'tahun_ajaran' => $tahun?->nama,
                'jumlah_siswa_aktif' => (clone $aktif)->count(),
                'jumlah_guru' => Pegawai::where('jenis', 'guru')->where('aktif', true)->count(),
                'jumlah_staf' => Pegawai::where('jenis', 'staf')->where('aktif', true)->count(),
                'jumlah_kelas' => $tahun ? Kelas::where('tahun_ajaran_id', $tahun->id)->count() : 0,
                'per_tingkat' => Kelas::query()
                    ->join('siswa', 'siswa.kelas_id', '=', 'kelas.id')
                    ->whereNull('siswa.deleted_at')->whereNull('kelas.deleted_at')
                    ->where('siswa.status', 'aktif')
                    ->when($tahun, fn ($q) => $q->where('kelas.tahun_ajaran_id', $tahun->id))
                    ->groupBy('kelas.tingkat')->orderBy('kelas.tingkat')
                    ->selectRaw('kelas.tingkat as tingkat, count(siswa.id) as jumlah')->get()
                    ->map(fn ($r) => ['tingkat' => $r->tingkat, 'jumlah' => (int) $r->jumlah])->all(),
                'per_jurusan' => Jurusan::where('aktif', true)->orderBy('kode')->get()->map(fn ($j) => [
                    'kode' => $j->kode,
                    'nama' => $j->nama,
                    'jumlah' => Siswa::where('status', 'aktif')
                        ->whereHas('kelas', fn ($k) => $k->where('jurusan_id', $j->id)
                            ->when($tahun, fn ($q) => $q->where('tahun_ajaran_id', $tahun->id)))
                        ->count(),
                ])->all(),
            ];
        });

        return response()->json(['data' => $data, 'meta' => ['waktu_server' => now()->toIso8601String()]]);
    }
}
