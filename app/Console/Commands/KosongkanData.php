<?php

namespace App\Console\Commands;

use App\Services\Audit;
use App\Support\CadanganDb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kosongkan data induk Pusat untuk memulai ulang (mis. ganti data dummy).
 * DIHAPUS : siswa, kelas, guru & staf, mapel, pengampu, akun non-superadmin (beserta akses & sesi), antrean webhook.
 * DISIMPAN: akun superadmin, tahun ajaran, jurusan, aplikasi terdaftar, sambungan, profil sekolah, audit.
 * Tidak mengirim webhook: aplikasi lain dikosongkan dengan perintah masing-masing lalu disinkron ulang.
 */
class KosongkanData extends Command
{
    protected $signature = 'pusat:kosongkan-data
        {--yakin : Lewati pertanyaan konfirmasi (untuk skrip)}
        {--tanpa-cadangan : Jangan buat cadangan database dulu (tidak disarankan)}';

    protected $description = 'Hapus semua data induk & akun (kecuali superadmin) — cadangan dibuat otomatis lebih dulu';

    private const TABEL = ['pengampu', 'siswa', 'kelas', 'pegawai', 'mapel'];

    public function handle(): int
    {
        $jumlah = collect(self::TABEL)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all()
            + ['akun (selain superadmin)' => DB::table('users')->where('superadmin', false)->count()];
        $this->warn('Data yang akan DIHAPUS PERMANEN dari Pusat:');
        $this->table(['Data', 'Jumlah'], collect($jumlah)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Tetap disimpan: akun superadmin, tahun ajaran, jurusan, aplikasi, sambungan, profil sekolah, audit.');

        if (! $this->option('yakin') && $this->ask('Ketik HAPUS SEMUA untuk melanjutkan') !== 'HAPUS SEMUA') {
            $this->info('Dibatalkan. Tidak ada yang dihapus.');

            return self::FAILURE;
        }

        if (! $this->option('tanpa-cadangan')) {
            try {
                $this->info('Cadangan dibuat: '.CadanganDb::buat('pusat-sebelum-kosongkan'));
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $this->line('Tidak ada yang dihapus. Perbaiki masalah cadangan, atau jalankan ulang dengan --tanpa-cadangan bila sudah punya cadangan lain.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () {
            $hapus = DB::table('users')->where('superadmin', false)->pluck('id');
            DB::table('akses')->whereIn('user_id', $hapus)->delete();
            foreach (['sso_kode', 'sso_token', 'webhook_outbox'] as $t) {
                DB::table($t)->delete();
            }
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->delete(); // semua orang perlu masuk ulang
            }
            DB::table('users')->whereIn('id', $hapus)->delete();
            DB::table('users')->update(['pegawai_id' => null, 'siswa_id' => null]);
            DB::table('kelas')->update(['wali_id' => null]);
            foreach (self::TABEL as $t) {
                DB::table($t)->delete();
            }
        });
        Audit::catat('data.dikosongkan', null, ['oleh' => 'artisan pusat:kosongkan-data'] + array_map('intval', $jumlah));

        $this->info('Selesai. Data induk Pusat kosong.');
        $this->line('Berikutnya: kosongkan juga Absensi/BK/TU/PKL/Website (perintah *:kosongkan-data di tiap server),');
        $this->line('lalu impor data baru di Pusat (Guru & staf -> Mapel -> Kelas -> Siswa -> Pengampu) dan Sinkron di tiap aplikasi.');

        return self::SUCCESS;
    }
}
