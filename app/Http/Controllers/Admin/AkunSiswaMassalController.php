<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Models\User;
use App\Services\AksesService;
use App\Services\AkunMassal;
use App\Services\AkunSiswaMassal;
use Illuminate\Http\Request;

/** Akun → Buat akun siswa sekaligus (per kelas). Daftar password awal hanya ditampilkan sekali. */
class AkunSiswaMassalController extends Controller
{
    public function form(Request $request)
    {
        $kelasIds = array_map('intval', (array) $request->query('kelas', []));
        $siswa = $kelasIds ? AkunSiswaMassal::belumPunyaAkun($kelasIds) : collect();
        $terpakai = [];
        $usulan = [];
        foreach ($siswa as $s) {
            $usulan[$s->id] = $terpakai[] = AkunMassal::username((string) $s->nis, $s->nama, $terpakai, 'siswa');
        }

        return view('admin.akun.siswa-massal', [
            'kelas' => AkunSiswaMassal::kelas(), 'dipilih' => $kelasIds, 'siswa' => $siswa, 'usulan' => $usulan,
            'aplikasi' => Aplikasi::where('aktif', true)->orderBy('nama')->get(), 'bawaan' => AkunSiswaMassal::BAWAAN, 'hasil' => session('akun_siswa'),
        ]);
    }

    public function buat(Request $request, AksesService $akses)
    {
        $d = $request->validate([
            'siswa' => ['required', 'array', 'min:1'], 'siswa.*' => ['integer'],
            'username' => ['nullable', 'array'], 'username.*' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'peran' => ['nullable', 'array'], 'peran.*' => ['nullable', 'string', 'max:40'],
        ], ['siswa.required' => 'Centang minimal satu siswa.', 'username.*.regex' => 'Username hanya huruf, angka, titik, garis bawah, atau strip.']);
        if (! $request->user()->superadmin && in_array('admin', \Illuminate\Support\Arr::flatten((array) ($d['peran'] ?? [])), true)) {
            return back()->withInput()->with('galat', 'Peran "admin" hanya bisa diberikan oleh administrator Pusat.');
        }
        $pilih = array_map('intval', $d['siswa']);
        $nama = array_filter(array_map(fn ($u) => strtolower(trim((string) $u)), array_intersect_key((array) ($d['username'] ?? []), array_flip($pilih))));
        $ganda = array_keys(array_filter(array_count_values($nama), fn ($n) => $n > 1));
        $dipakai = User::whereIn('username', $nama)->pluck('username')->all();
        if ($ganda || $dipakai) {
            return back()->withInput()->with('galat', 'Username sudah dipakai atau ganda: '.implode(', ', array_unique(array_merge($ganda, $dipakai))).'.');
        }
        $hasil = AkunSiswaMassal::buat($pilih, array_map(fn ($v) => (string) $v, (array) ($d['peran'] ?? [])), $akses, $nama);

        return redirect()->route('admin.akun.siswa')->with('akun_siswa', $hasil)
            ->with('sukses', count($hasil).' akun siswa dibuat. Cetak daftar password di bawah SEKARANG — daftar ini tidak bisa ditampilkan lagi.');
    }
}
