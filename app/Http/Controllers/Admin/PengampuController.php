<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Pegawai;
use App\Models\Pengampu;
use App\Models\TahunAjaran;
use App\Services\PengampuService;
use App\Support\CsvParser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Administrasi → Pengampu Mapel: siapa mengajar mapel apa di kelas mana (tahun ajaran aktif). */
class PengampuController extends Controller
{
    public function index(Request $request)
    {
        $ta = TahunAjaran::aktif();
        $kelas = $ta ? Kelas::where('tahun_ajaran_id', $ta->id)->orderBy('tingkat')->orderBy('nama')->get() : collect();
        $q = Pengampu::query()->with(['pegawai', 'mapel', 'kelas'])->whereIn('kelas_id', $kelas->pluck('id'));
        if ($request->filled('pegawai_id')) {
            $q->where('pegawai_id', (int) $request->query('pegawai_id'));
        }
        if ($request->filled('kelas_id')) {
            $q->where('kelas_id', (int) $request->query('kelas_id'));
        }
        $daftar = $q->get()->sortBy(fn ($p) => [$p->pegawai?->nama, $p->mapel?->nama, $p->kelas?->nama])
            ->groupBy(fn ($p) => $p->pegawai_id.'-'.$p->mapel_id);
        $terisi = Pengampu::whereIn('kelas_id', $kelas->pluck('id'))->distinct()->pluck('kelas_id');

        return view('admin.pengampu.index', [
            'ta' => $ta,
            'kelas' => $kelas,
            'guru' => Pegawai::where('jenis', 'guru')->where('aktif', true)->orderBy('nama')->get(),
            'mapel' => Mapel::where('aktif', true)->orderBy('nama')->get(),
            'daftar' => $daftar,
            'total' => $daftar->flatten()->count(),
            'kelasKosong' => $kelas->whereNotIn('id', $terisi)->pluck('nama'),
        ]);
    }

    public function store(Request $request)
    {
        $ta = TahunAjaran::aktif();
        abort_unless($ta, 422, 'Belum ada tahun ajaran aktif.');
        $d = $request->validate([
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->whereNull('deleted_at')],
            'mapel_id' => ['required', 'integer', Rule::exists('mapel', 'id')->whereNull('deleted_at')],
            'kelas' => ['required', 'array', 'min:1'],
            'kelas.*' => ['integer', Rule::exists('kelas', 'id')->where('tahun_ajaran_id', $ta->id)->whereNull('deleted_at')],
        ], ['kelas.required' => 'Centang minimal satu kelas.']);
        $r = PengampuService::tambah($d['pegawai_id'], $d['mapel_id'], $d['kelas']);

        return redirect()->route('admin.pengampu.index', ['pegawai_id' => $d['pegawai_id']])
            ->with('sukses', "Pengampu disimpan: {$r['baru']} baru".($r['sama'] ? ", {$r['sama']} sudah ada" : '').'.');
    }

    public function destroy(Pengampu $pengampu)
    {
        $pengampu->delete();

        return back()->with('sukses', 'Pengampu dihapus.');
    }

    public function impor(Request $request)
    {
        $request->validate(['berkas' => ['required', 'file', 'max:2048', 'mimes:csv,txt']]);
        try {
            $csv = CsvParser::parse((string) file_get_contents($request->file('berkas')->getRealPath()));
        } catch (\InvalidArgumentException $e) {
            return back()->with('galat', $e->getMessage());
        }
        $r = PengampuService::impor($csv['baris']);
        $pesan = "Impor pengampu: {$r['baru']} baru, {$r['sama']} sudah ada, ".count($r['galat']).' dilewati.';

        return back()->with($r['galat'] && ! $r['baru'] ? 'galat' : 'sukses', $pesan)->with('rincian', array_slice($r['galat'], 0, 50));
    }

    public function templat()
    {
        return response("\u{FEFF}guru;mapel;kelas\r\n198701012010011001;MTK;X DKV 1, X DKV 2\r\nNama Guru;Bahasa Indonesia;XI TKJ 1\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="templat-pengampu.csv"',
        ]);
    }
}
