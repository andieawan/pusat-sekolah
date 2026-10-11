<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

/**
 * Data awal yang aman dijalankan berulang. Tidak membuat akun/password apa pun:
 * gunakan `php artisan pusat:buat-admin`. Jurusan di bawah mengikuti website sekolah; ubah di panel admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tahun ajaran berjalan: Juli-Juni.
        $mulai = now()->month >= 7 ? now()->year : now()->year - 1;
        TahunAjaran::firstOrCreate(
            ['nama' => $mulai.'/'.($mulai + 1)],
            ['mulai' => $mulai.'-07-01', 'selesai' => ($mulai + 1).'-06-30', 'aktif' => true],
        );

        foreach ([
            ['AK', 'Akuntansi dan Keuangan Lembaga'],
            ['DKV', 'Desain Komunikasi Visual'],
            ['BD', 'Bisnis Digital'],
        ] as [$kode, $nama]) {
            Jurusan::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'aktif' => true]);
        }
    }
}
