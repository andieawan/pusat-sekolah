<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membuat akun login untuk guru & staf yang belum punya akun. Username = NIP (bila NIP tidak layak/terpakai:
 * nama depan + angka), password awal acak (wajib diganti saat pertama masuk), peran awal per aplikasi
 * dibedakan untuk guru dan staf.
 */
final class AkunMassal
{
    /** Peran awal bawaan: [kode aplikasi => [guru => peran|null, staf => peran|null]] */
    public const BAWAAN = ['absensi' => ['guru' => 'guru', 'staf' => null], 'tu' => ['guru' => 'pegawai', 'staf' => 'pegawai']];

    /** @return Collection<int,Pegawai> */
    public static function belumPunyaAkun(): Collection
    {
        return Pegawai::where('aktif', true)->whereDoesntHave('akun')->orderBy('jenis')->orderBy('nama')->get();
    }

    public static function usulanUsername(Pegawai $p, array $terpakai = []): string
    {
        return self::username((string) $p->nip, $p->nama, $terpakai, 'guru');
    }

    /** Kode (NIP/NIS) bila layak & belum dipakai; selain itu nama depan + angka. */
    public static function username(string $kode, string $nama, array $terpakai = [], string $cadangan = 'akun'): string
    {
        $kode = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', $kode));
        $bebas = fn (string $u) => ! in_array($u, $terpakai, true) && ! User::where('username', $u)->exists();
        if (strlen($kode) >= 3 && strlen($kode) <= 60 && $bebas($kode)) {
            return $kode;
        }
        $dasar = strtolower(preg_replace('/[^a-z]/i', '', \Illuminate\Support\Str::ascii(explode(' ', trim(preg_replace('/^(drs?|dra|ir|h|hj)\.?\s+/i', '', $nama)))[0]))) ?: $cadangan;
        $dasar = substr(str_pad($dasar, 3, 'x'), 0, 40);
        for ($i = 1; ; $i++) {
            $u = $i === 1 ? $dasar : $dasar.$i;
            if ($bebas($u)) {
                return $u;
            }
        }
    }

    /** 6 huruf + 4 angka, tanpa huruf yang mirip (l/I/O): mudah dibaca dari kertas, memenuhi aturan (≥10, huruf & angka). */
    public static function passwordAwal(): string
    {
        $huruf = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $pw = '';
        for ($i = 0; $i < 6; $i++) {
            $pw .= $huruf[random_int(0, strlen($huruf) - 1)];
        }

        return $pw.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  int[]  $pegawaiIds
     * @param  array<int,array{guru?:string,staf?:string}>  $peran  aplikasi_id => peran untuk guru / staf ('' = tanpa peran)
     * @return array<int,array{nama:string,jenis:string,username:string,password:string}>
     */
    public static function buat(array $pegawaiIds, array $peran, AksesService $akses, array $username = []): array
    {
        $aplikasi = Aplikasi::all()->keyBy('id');
        $hasil = [];
        $terpakai = array_values($username);
        DB::transaction(function () use ($pegawaiIds, $peran, $akses, $aplikasi, $username, &$hasil, &$terpakai) {
            foreach (self::belumPunyaAkun()->whereIn('id', $pegawaiIds) as $p) {
                $nama = $username[$p->id] ?? null;
                if ($nama === null || User::where('username', $nama)->exists()) {
                    $nama = self::usulanUsername($p, $terpakai);
                    $terpakai[] = $nama;
                }
                $password = \App\Support\AturanPassword::acak();
                $email = $p->email && ! User::where('email', $p->email)->exists() ? $p->email : null;
                $u = new User(['username' => $nama, 'nama' => $p->nama, 'email' => $email, 'aktif' => true, 'pegawai_id' => $p->id]);
                $u->password = $password;
                $u->wajib_ganti_password = true;
                $u->save();
                $jenis = $p->jenis === 'staf' ? 'staf' : 'guru';
                $perAplikasi = [];
                foreach ($peran as $appId => $pilih) {
                    $nilai = (string) ($pilih[$jenis] ?? '');
                    if ($nilai !== '' && isset($aplikasi[$appId]) && in_array($nilai, $aplikasi[$appId]->peran_tersedia ?? [], true)) {
                        $perAplikasi[(int) $appId] = [$nilai];
                    }
                }
                $akses->sinkron($u, $perAplikasi);
                $hasil[] = ['nama' => $p->nama, 'jenis' => $jenis, 'username' => $nama, 'password' => $password];
            }
        });
        Audit::catat('akun.massal', null, ['jumlah' => count($hasil)]); // password tidak dicatat

        return $hasil;
    }
}
