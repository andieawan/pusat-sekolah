<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\AturanPassword;
use Illuminate\Support\Facades\Storage;

/** Profil sekolah satu sumber: kop surat, kepala sekolah, logo. Dibaca semua aplikasi lewat GET /api/v1/profil. */
final class ProfilSekolah
{
    /** kunci => [label, aturan validasi, contoh] */
    public const KOLOM = [
        'nama' => ['Nama sekolah', 'required|string|max:150', 'SMK Islam Bustanul Ulum Pakusari'],
        'nama_kop' => ['Nama di kop surat (huruf besar)', 'nullable|string|max:150', 'SMK ISLAM BUSTANUL ULUM PAKUSARI'],
        'yayasan' => ['Baris yayasan di kop', 'nullable|string|max:150', 'YAYASAN PENDIDIKAN ISLAM'],
        'yayasan2' => ['Baris yayasan ke-2', 'nullable|string|max:150', '“ BUSTANUL ULUM ”'],
        'bidang' => ['Bidang keahlian (kop)', 'nullable|string|max:200', 'Kelompok Bisnis Manajemen dan Teknologi Informasi Komunikasi'],
        'nss' => ['NSS', 'nullable|string|max:30', ''],
        'npsn' => ['NPSN', 'nullable|string|max:20', ''],
        'alamat' => ['Alamat (baris kop surat)', 'nullable|string|max:255', 'Jl. Himalaya No. 17 Pakusari – Jember'],
        'kota' => ['Kota/kecamatan untuk tanggal surat', 'nullable|string|max:60', 'Pakusari'],
        'telepon' => ['Telepon', 'nullable|string|max:50', ''],
        'email' => ['Email sekolah', 'nullable|email|max:150', ''],
        'kepsek' => ['Nama kepala sekolah (beserta gelar)', 'nullable|string|max:150', ''],
        'kepsek_nip' => ['NIP/NUPTK kepala sekolah', 'nullable|string|max:40', ''],
    ];

    public static function logoPath(): ?string
    {
        $p = Pengaturan::ambil('logo');

        return $p && Storage::disk('local')->exists($p) ? $p : null;
    }

    /** Nama guru yang berperan BK (di aplikasi mana pun), untuk surat-surat BK. @return string[] */
    public static function guruBk(): array
    {
        return User::where('aktif', true)->whereHas('akses', fn ($q) => $q->where('peran', 'bk'))->with('pegawai')->orderBy('nama')->get()
            ->map(fn ($u) => $u->pegawai?->nama ?? $u->nama)->unique()->values()->all();
    }

    public static function data(): array
    {
        $d = [];
        foreach (array_keys(self::KOLOM) as $k) {
            $d[$k] = Pengaturan::ambil($k);
        }
        $d['nama_kop'] ??= $d['nama'] !== null ? mb_strtoupper($d['nama']) : null;
        $logo = self::logoPath();
        $d['logo_url'] = $logo ? route('logo.sekolah') : null;
        $d['logo_versi'] = $logo ? Pengaturan::ambil('logo_versi') : null;
        $d['guru_bk'] = self::guruBk();
        $d['aturan_password'] = AturanPassword::nilai();
        $d['diisi'] = Pengaturan::ambil('nama') !== null;

        return $d;
    }

    /** Kabari semua aplikasi agar mengambil profil terbaru. */
    public static function kabari(): int
    {
        $n = 0;
        foreach (Aplikasi::where('aktif', true)->whereNotNull('webhook_url')->get() as $app) {
            Outbox::antre($app, 'profil.diperbarui', []);
            $n++;
        }

        return $n;
    }
}
