<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Definisi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD generik untuk data induk, digerakkan oleh App\Support\Definisi. */
class MasterController extends Controller
{
    public function index(Request $request, string $sumber)
    {
        $d = Definisi::ambil($sumber);
        $q = $d['model']::query()->with($d['with'] ?? []);

        if (($t = trim((string) $request->query('q'))) !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(function ($w) use ($d, $like) {
                foreach ($d['cari'] as $kolom) {
                    $w->orWhere($kolom, 'like', $like);
                }
            });
        }
        $filter = $this->filter($sumber);
        foreach ($filter as $kolom => [, $pilihan]) {
            $v = (string) $request->query($kolom, '');
            if ($v !== '' && array_key_exists($v, $pilihan)) {
                $q->where($kolom, $v);
            }
        }
        $total = (clone $q)->count();
        [$kolom, $arah] = $d['urut'];
        $items = $q->orderBy($kolom, $arah)->orderBy('id')->simplePaginate(25)->withQueryString();

        return view('admin.master.index', ['d' => $d, 'sumber' => $sumber, 'items' => $items, 'filter' => $filter, 'total' => $total]);
    }

    /** Saringan daftar per sumber: kolom => [label, [nilai => teks]]. */
    private function filter(string $sumber): array
    {
        $ta = \App\Models\TahunAjaran::aktif();

        return match ($sumber) {
            'siswa' => [
                'kelas_id' => ['Semua kelas', \App\Models\Kelas::when($ta, fn ($q) => $q->where('tahun_ajaran_id', $ta->id))
                    ->orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")->orderBy('nama')->pluck('nama', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all()],
                'status' => ['Semua status', ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'keluar' => 'Keluar']],
            ],
            'pegawai' => ['jenis' => ['Guru & staf', ['guru' => 'Guru', 'staf' => 'Staf']]],
            'kelas' => ['tahun_ajaran_id' => ['Semua tahun ajaran', \App\Models\TahunAjaran::orderByDesc('nama')->pluck('nama', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all()]],
            default => [],
        };
    }

    public function create(string $sumber)
    {
        $d = Definisi::ambil($sumber);

        return view('admin.master.form', ['d' => $d, 'sumber' => $sumber, 'rec' => new $d['model']]);
    }

    public function store(Request $request, string $sumber)
    {
        $d = Definisi::ambil($sumber);
        $data = $this->validasi($request, $d, null);

        // Data dengan kunci sama yang pernah dihapus dipulihkan, bukan digandakan.
        $rec = isset($d['kunci'])
            ? $d['model']::onlyTrashed()->where($d['kunci'], $data[$d['kunci']])->first()
            : null;
        if ($rec) {
            $rec->restore();
            $rec->update($data);
        } else {
            $d['model']::create($data);
        }

        return redirect()->route('admin.master.index', $sumber)->with('sukses', $d['judul'].' berhasil disimpan.');
    }

    public function edit(string $sumber, int $id)
    {
        $d = Definisi::ambil($sumber);

        return view('admin.master.form', ['d' => $d, 'sumber' => $sumber, 'rec' => $d['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $sumber, int $id)
    {
        $d = Definisi::ambil($sumber);
        $rec = $d['model']::findOrFail($id);
        $rec->update($this->validasi($request, $d, $rec->id));

        return redirect()->route('admin.master.index', $sumber)->with('sukses', $d['judul'].' berhasil diperbarui.');
    }

    public function destroy(string $sumber, int $id)
    {
        $d = Definisi::ambil($sumber);
        $rec = $d['model']::findOrFail($id);

        if ($pesan = ($d['penghalang'])($rec)) {
            return back()->with('galat', $pesan);
        }
        $rec->delete();

        return redirect()->route('admin.master.index', $sumber)->with('sukses', $d['judul'].' dihapus.');
    }

    private function validasi(Request $request, array $d, ?int $abaikanId): array
    {
        $aturan = [];
        $centang = [];
        foreach ($d['field'] as $f) {
            [$nama, , $tipe, $a] = $f;
            $r = explode('|', $a);
            if ($tipe === 'checkbox') {
                $centang[] = $nama;
            }
            if (($d['kunci'] ?? null) === $nama) {
                $r[] = Rule::unique($d['tabel'], $nama)->ignore($abaikanId)->whereNull('deleted_at');
            }
            if (isset($d['unik']) && $d['unik'][0] === $nama) {
                [$kolom, $bantu] = $d['unik'];
                $r[] = Rule::unique($d['tabel'], $kolom)
                    ->where(fn ($q) => $q->where($bantu, $request->input($bantu))->whereNull('deleted_at'))
                    ->ignore($abaikanId);
            }
            $aturan[$nama] = $r;
        }

        $data = $request->validate($aturan);
        foreach ($centang as $nama) {
            $data[$nama] = $request->boolean($nama);
        }

        return $data;
    }
}
