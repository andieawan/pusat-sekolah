<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu sambungan antar-aplikasi. "pemberi" = aplikasi yang menyediakan API, "peminta" = aplikasi yang memanggilnya.
 * Daftar jenis yang dikenal ada di config('pusat.sambungan').
 */
class Sambungan extends Model
{
    protected $table = 'sambungan';

    protected $fillable = ['jenis', 'aktif'];

    protected $hidden = ['kunci'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'kunci' => 'encrypted', 'kunci_dibuat_pada' => 'datetime'];
    }

    /** @return array{nama:string,keterangan:string,pemberi:string,peminta:string,kunci:bool}|null */
    public static function definisi(string $jenis): ?array
    {
        return self::semua()[$jenis] ?? null;
    }

    /**
     * Semua jenis sambungan. Pemberi "absensi" diganti aplikasi penyedia absensi yang aktif
     * (Pengaturan "aplikasi_absensi": absensi atau jurnal) — lihat `php artisan pusat:alihkan-absensi`.
     *
     * @return array<string,array{nama:string,keterangan:string,pemberi:string,peminta:string,kunci:bool}>
     */
    public static function semua(): array
    {
        $penyedia = self::penyediaAbsensi();
        $hasil = [];
        foreach ((array) config('pusat.sambungan') as $jenis => $def) {
            foreach (['pemberi', 'peminta'] as $k) {
                if ($def[$k] === 'absensi') {
                    $def[$k] = $penyedia;
                }
            }
            if ($penyedia !== 'absensi') {
                $def['nama'] = str_replace('Absensi', 'Jurnal Guru', $def['nama']);
            }
            $hasil[$jenis] = $def;
        }

        return $hasil;
    }

    /** Kode aplikasi yang melayani data kehadiran untuk aplikasi lain (bawaan: absensi). */
    public static function penyediaAbsensi(): string
    {
        try {
            return Pengaturan::ambil('aplikasi_absensi') ?: 'absensi';
        } catch (\Throwable) {
            return 'absensi';
        }
    }

    public function buatKunci(): void
    {
        $this->kunci = 'sk_'.Str::lower(Str::random(45));
        $this->kunci_dibuat_pada = now();
    }
}
