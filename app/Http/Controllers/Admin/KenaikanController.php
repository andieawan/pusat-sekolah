<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Services\Audit;
use App\Services\KenaikanService;
use Illuminate\Http\Request;

/** Administrasi → Kenaikan Kelas: dari tahun ajaran aktif ke tahun ajaran berikutnya. */
class KenaikanController extends Controller
{
    /** "2026/2027" → "2027/2028". */
    private static function namaBerikut(?string $nama): string
    {
        return preg_match('/^(\d{4})\/(\d{4})$/', (string) $nama, $m) ? ($m[1] + 1).'/'.($m[2] + 1) : '';
    }

    public function index(Request $request)
    {
        $semua = TahunAjaran::orderByDesc('nama')->get();
        $dari = $semua->firstWhere('id', (int) $request->query('dari')) ?? TahunAjaran::aktif();
        $ke = $semua->firstWhere('id', (int) $request->query('ke'))
            ?? ($dari ? $semua->first(fn ($t) => $t->id !== $dari->id && $t->nama > $dari->nama) : null);

        return view('admin.kenaikan.index', [
            'semua' => $semua, 'dari' => $dari, 'ke' => $ke,
            'rencana' => $dari && $ke && $dari->id !== $ke->id ? KenaikanService::rencana($dari, $ke) : collect(),
            'kelasKe' => $ke ? Kelas::where('tahun_ajaran_id', $ke->id)->orderBy('tingkat')->orderBy('nama')->get() : collect(),
            'usulNama' => self::namaBerikut($dari?->nama),
        ]);
    }

    public function tahunBaru(Request $request)
    {
        $d = $request->validate(['nama' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/', 'unique:tahun_ajaran,nama']],
            ['nama.regex' => 'Format tahun ajaran: 2027/2028.', 'nama.unique' => 'Tahun ajaran itu sudah ada.']);
        $t = TahunAjaran::create(['nama' => $d['nama'], 'aktif' => false]);
        Audit::catat('kenaikan.tahun_baru', $t->nama);

        return redirect()->route('admin.kenaikan.index', ['dari' => TahunAjaran::aktif()?->id, 'ke' => $t->id])
            ->with('sukses', "Tahun ajaran {$t->nama} dibuat (belum aktif). Periksa usulan kenaikan di bawah.");
    }

    public function terapkan(Request $request)
    {
        $d = $request->validate([
            'dari' => ['required', 'integer', 'exists:tahun_ajaran,id'], 'ke' => ['required', 'integer', 'exists:tahun_ajaran,id', 'different:dari'],
            'tujuan' => ['array'], 'tujuan.*' => ['string', 'max:60'],
            'siswa' => ['array'], 'siswa.*' => ['in:naik,tinggal,keluar,lulus'],
            'aktifkan' => ['nullable', 'boolean'], 'yakin' => ['accepted'],
        ], ['yakin.accepted' => 'Centang konfirmasi terlebih dahulu.', 'ke.different' => 'Tahun ajaran tujuan harus berbeda.']);
        $dari = TahunAjaran::findOrFail($d['dari']);
        $ke = TahunAjaran::findOrFail($d['ke']);
        $kecuali = array_filter((array) ($d['siswa'] ?? []), fn ($a) => $a !== 'naik');
        try {
            $h = KenaikanService::terapkan($dari, $ke, (array) ($d['tujuan'] ?? []), $kecuali, (bool) ($d['aktifkan'] ?? false));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('galat', $e->getMessage());
        }

        return redirect()->route('admin.kenaikan.index', ['dari' => $dari->id, 'ke' => $ke->id])->with('sukses', sprintf(
            'Kenaikan %s → %s selesai: %d naik, %d tinggal kelas, %d lulus, %d keluar.%s%s Semua aplikasi dikabari otomatis.',
            $dari->nama, $ke->nama, $h['naik'], $h['tinggal'], $h['lulus'], $h['keluar'],
            $h['kelas_baru'] ? ' Kelas baru: '.implode(', ', $h['kelas_baru']).' — atur wali kelasnya di menu Kelas.' : '',
            ! empty($d['aktifkan']) ? " Tahun ajaran aktif sekarang {$ke->nama}." : '',
        ));
    }
}
