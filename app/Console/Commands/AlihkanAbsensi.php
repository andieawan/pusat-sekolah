<?php

namespace App\Console\Commands;

use App\Models\Akses;
use App\Models\Aplikasi;
use App\Models\Pengaturan;
use App\Models\Sambungan;
use App\Models\User;
use App\Services\Audit;
use App\Services\Outbox;
use App\Services\SambunganService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Alihkan peran "aplikasi absensi" dari Absensi ke Jurnal Guru (kembalikan dengan --balik):
 *  1. akses akun di Absensi disalin ke Jurnal (peran yang sama; akses Jurnal yang sudah ada tetap),
 *  2. sambungan yang dilayani Absensi (Absensi → BK) dipindah ke Jurnal: BK otomatis memakai alamat & kunci Jurnal,
 *  3. opsional --nonaktifkan-lama: aplikasi Absensi dinonaktifkan di Pusat (hilang dari portal, login lewat Pusat ditolak).
 */
class AlihkanAbsensi extends Command
{
    protected $signature = 'pusat:alihkan-absensi
        {--nonaktifkan-lama : Nonaktifkan Absensi di Pusat setelah dialihkan}
        {--balik : Kembalikan penyedia absensi ke Absensi (akses Jurnal yang sudah disalin tidak dihapus)}
        {--yakin : Lewati konfirmasi}';

    protected $description = 'Alihkan absensi dari aplikasi Absensi ke Jurnal Guru: salin akses akun, pindahkan sambungan ke BK';

    public function handle(): int
    {
        $dari = Aplikasi::where('kode', 'absensi')->first();
        $ke = Aplikasi::where('kode', 'jurnal')->first();
        if (! $dari || ! $ke) {
            $this->error('Aplikasi "absensi" atau "jurnal" belum terdaftar di Pusat.');

            return self::FAILURE;
        }

        if ($this->option('balik')) {
            DB::transaction(function () use ($dari) {
                Pengaturan::simpan('aplikasi_absensi', null);
                $dari->aktif || $dari->update(['aktif' => true]);
            });
            $this->kabariSambungan([$dari, $ke]);
            Audit::catat('absensi.dialihkan', null, ['ke' => $dari->kode, 'balik' => true]);
            $this->info("Penyedia absensi dikembalikan ke {$dari->nama}.");

            return self::SUCCESS;
        }

        if (! $ke->aktif) {
            $this->error("{$ke->nama} belum aktif di Pusat. Aktifkan dulu di Administrasi → Aplikasi.");

            return self::FAILURE;
        }
        $pemegang = Akses::where('aplikasi_id', $dari->id)->distinct()->pluck('user_id');
        $this->line("Akun dengan akses {$dari->nama}: ".$pemegang->count());
        $this->line("Sambungan yang dipindah ke {$ke->nama}: ".(implode(', ', self::jenisAbsensi()) ?: '-'));
        if ($this->option('nonaktifkan-lama')) {
            $this->warn("{$dari->nama} akan DINONAKTIFKAN di Pusat.");
        }
        if (! $this->option('yakin') && ! $this->confirm('Lanjutkan?', true)) {
            return self::FAILURE;
        }

        // Semua perubahan database sekaligus; pemberitahuan ke aplikasi dikirim setelah tersimpan.
        $berubah = DB::transaction(function () use ($dari, $ke, $pemegang) {
            $berubah = [];
            foreach (User::whereIn('id', $pemegang)->get() as $u) {
                // Hanya akses Jurnal yang ditambah; akses aplikasi lain tidak disentuh.
                $punya = Akses::where('user_id', $u->id)->where('aplikasi_id', $ke->id)->pluck('peran')->all();
                $tambah = array_values(array_diff(
                    array_intersect(Akses::where('user_id', $u->id)->where('aplikasi_id', $dari->id)->pluck('peran')->all(), $ke->peran_tersedia ?? []),
                    $punya,
                ));
                foreach ($tambah as $p) {
                    Akses::create(['user_id' => $u->id, 'aplikasi_id' => $ke->id, 'peran' => $p]);
                }
                if ($tambah) {
                    $berubah[] = [$u, array_merge($punya, $tambah)];
                }
            }
            Pengaturan::simpan('aplikasi_absensi', $ke->kode);
            if ($this->option('nonaktifkan-lama')) {
                $dari->update(['aktif' => false]);
            }

            return $berubah;
        });

        foreach ($berubah as [$u, $peran]) {
            Outbox::akses($u, $ke, $peran);
            Audit::catat('akses.diubah', $u->username, ['aplikasi' => $ke->kode, 'peran' => $peran, 'dari' => $dari->kode]);
        }
        $this->kabariSambungan([$dari, $ke]);
        Audit::catat('absensi.dialihkan', null, ['dari' => $dari->kode, 'ke' => $ke->kode, 'akses_disalin' => count($berubah), 'lama_nonaktif' => (bool) $this->option('nonaktifkan-lama')]);

        $this->info('Selesai: akses '.count($berubah)." akun disalin ke {$ke->nama}; sambungan absensi kini dilayani {$ke->nama}.");
        $this->line("Berikutnya: di {$ke->nama} tekan Sinkron sekarang; di BK buka Admin → Integrasi → Uji koneksi Absensi.");

        return self::SUCCESS;
    }

    /** Jenis sambungan yang dilayani aplikasi absensi (menurut config, sebelum dialihkan). */
    private static function jenisAbsensi(): array
    {
        return array_keys(array_filter((array) config('pusat.sambungan'), fn ($d) => in_array('absensi', [$d['pemberi'], $d['peminta']], true)));
    }

    /** Kabari mitra sambungan absensi, juga Absensi/Jurnal yang tidak lagi/belum tercakup, agar menarik sambungan terbaru. */
    private function kabariSambungan(array $apps): void
    {
        $semua = Sambungan::semua();
        foreach (self::jenisAbsensi() as $jenis) {
            SambunganService::kabari($jenis);
            foreach ($apps as $a) {
                if ($a->aktif && $a->webhook_url && ! in_array($a->kode, [$semua[$jenis]['pemberi'], $semua[$jenis]['peminta']], true)) {
                    Outbox::antre($a, 'sambungan.diperbarui', ['jenis' => $jenis]);
                }
            }
        }
    }
}
