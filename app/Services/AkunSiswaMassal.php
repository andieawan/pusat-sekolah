<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\AturanPassword;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Akun login siswa sekaligus per kelas (mis. siswa yang akan PKL). Username = NIS, password awal acak (wajib diganti),
 * peran awal per aplikasi. Akun otomatis nonaktif bila siswa lulus/pindah/keluar (lihat model Siswa).
 */
final class AkunSiswaMassal
{
    /** Peran awal bawaan: kode aplikasi => peran */
    public const BAWAAN = ['pkl' => 'siswa'];

    /** Kelas tahun ajaran aktif beserta jumlah siswa aktif yang belum punya akun. */
    public static function kelas(): Collection
    {
        $ta = TahunAjaran::aktif();

        return Kelas::when($ta, fn ($q) => $q->where('tahun_ajaran_id', $ta->id))
            ->withCount(['siswa as tanpa_akun' => fn ($q) => $q->where('status', 'aktif')->whereDoesntHave('akun')])
            ->orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")->orderBy('nama')->get();
    }

    /** @param int[] $kelasIds */
    public static function belumPunyaAkun(array $kelasIds): Collection
    {
        return Siswa::with('kelas')->where('status', 'aktif')->whereIn('kelas_id', $kelasIds)->whereDoesntHave('akun')
            ->get()->sortBy(fn ($s) => [$s->kelas?->nama, $s->nama])->values();
    }

    /**
     * @param  int[]  $siswaIds
     * @param  array<int,string>  $peran  aplikasi_id => peran ('' = tanpa peran)
     * @param  array<int,string>  $username  siswa_id => username pilihan admin
     * @return array<int,array{kelas:string,nis:string,nama:string,username:string,password:string}>
     */
    public static function buat(array $siswaIds, array $peran, AksesService $akses, array $username = []): array
    {
        $aplikasi = Aplikasi::all()->keyBy('id');
        $perAplikasi = [];
        foreach ($peran as $appId => $nilai) {
            if ($nilai !== '' && isset($aplikasi[$appId]) && in_array($nilai, $aplikasi[$appId]->peran_tersedia ?? [], true)) {
                $perAplikasi[(int) $appId] = [$nilai];
            }
        }
        $hasil = [];
        $terpakai = array_values($username);
        DB::transaction(function () use ($siswaIds, $perAplikasi, $akses, $username, &$hasil, &$terpakai) {
            $siswa = Siswa::with('kelas')->whereIn('id', $siswaIds)->where('status', 'aktif')->whereDoesntHave('akun')->get();
            foreach ($siswa as $s) {
                $nama = $username[$s->id] ?? null;
                if ($nama === null || User::where('username', $nama)->exists()) {
                    $nama = AkunMassal::username((string) $s->nis, $s->nama, $terpakai, 'siswa');
                    $terpakai[] = $nama;
                }
                $password = AturanPassword::acak();
                $u = new User(['username' => $nama, 'nama' => $s->nama, 'aktif' => true, 'siswa_id' => $s->id]);
                $u->password = $password;
                $u->wajib_ganti_password = true;
                $u->save();
                $akses->sinkron($u, $perAplikasi);
                $hasil[] = ['kelas' => (string) $s->kelas?->nama, 'nis' => (string) $s->nis, 'nama' => $s->nama, 'username' => $nama, 'password' => $password];
            }
        });
        usort($hasil, fn ($a, $b) => [$a['kelas'], $a['nama']] <=> [$b['kelas'], $b['nama']]);
        Audit::catat('akun.massal_siswa', null, ['jumlah' => count($hasil)]); // password tidak dicatat

        return $hasil;
    }
}
