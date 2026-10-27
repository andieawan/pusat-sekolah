<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Services\AksesService;
use App\Services\AkunMassal;
use Illuminate\Http\Request;

/** Akun → Buat akun guru & staf sekaligus. Daftar password awal hanya ditampilkan sekali (sesi), tidak disimpan. */
class AkunMassalController extends Controller
{
    public function form()
    {
        $pegawai = AkunMassal::belumPunyaAkun();
        $terpakai = [];
        $usulan = [];
        foreach ($pegawai as $p) {
            $usulan[$p->id] = $terpakai[] = AkunMassal::usulanUsername($p, $terpakai);
        }

        return view('admin.akun.massal', [
            'pegawai' => $pegawai, 'usulan' => $usulan, 'aplikasi' => Aplikasi::where('aktif', true)->orderBy('nama')->get(),
            'bawaan' => AkunMassal::BAWAAN, 'hasil' => session('akun_massal'),
        ]);
    }

    public function buat(Request $request, AksesService $akses)
    {
        $d = $request->validate([
            'pegawai' => ['required', 'array', 'min:1'], 'pegawai.*' => ['integer'],
            'username' => ['nullable', 'array'], 'username.*' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'peran' => ['nullable', 'array'], 'peran.*.guru' => ['nullable', 'string', 'max:40'], 'peran.*.staf' => ['nullable', 'string', 'max:40'],
        ], ['pegawai.required' => 'Centang minimal satu guru/staf.', 'username.*.regex' => 'Username hanya huruf, angka, titik, garis bawah, atau strip.']);
        $pilih = array_map('intval', $d['pegawai']);
        $nama = array_filter(array_map(fn ($u) => strtolower(trim((string) $u)), array_intersect_key((array) ($d['username'] ?? []), array_flip($pilih))));
        $ganda = array_keys(array_filter(array_count_values($nama), fn ($n) => $n > 1));
        $dipakai = \App\Models\User::whereIn('username', $nama)->pluck('username')->all();
        if ($ganda || $dipakai) {
            return back()->withInput()->with('galat', 'Username sudah dipakai atau ganda: '.implode(', ', array_unique(array_merge($ganda, $dipakai))).'.');
        }
        $hasil = AkunMassal::buat($pilih, (array) ($d['peran'] ?? []), $akses, $nama);

        return redirect()->route('admin.akun.massal')->with('akun_massal', $hasil)
            ->with('sukses', count($hasil).' akun dibuat. Cetak atau salin daftar password di bawah SEKARANG — daftar ini tidak bisa ditampilkan lagi.');
    }
}
