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

/**
 * Alihkan peran "aplikasi absensi" dari Absensi ke Jurnal Guru (atau sebaliknya dengan --balik):
 *  1. akses akun di Absensi disalin ke Jurnal (peran yang sama; akses Jurnal yang sudah ada tetap),
 *  2. sambungan yang dilayani Absensi (Absensi → BK) dipindah ke Jurnal: BK otomatis memakai alamat & kunci Jurnal,
 *  3. opsional --nonaktifkan-lama: aplikasi Absensi dinonaktifkan di Pusat (hilang dari portal, login lewat Pusat ditolak).
 */
class AlihkanAbsensi extends Command
{
    protected $signature = 'pusat:alihkan-absensi
        {ke=jurnal : Kode aplikasi pengganti}
        {--dari=absensi : Kode aplikasi lama}
        {--nonaktifkan-lama : Nonaktifkan aplikasi lama di Pusat setelah dialihkan}
        {--balik : Kembalikan penyedia absensi ke aplikasi lama (akses yang sudah disalin tidak dihapus)}
        {--yakin : Lewati konfirmasi}';

    protected $description = 'Alihkan absensi dari aplikasi Absensi ke Jurnal Guru: salin akses akun, pindahkan sambungan ke BK';

    public function handle(): int
    {
        $dari = Aplikasi::where('kode', $this->option('dari'))->first();
        $ke = Aplikasi::where('kode', $this->argument('ke'))->first();
        if (! $dari || ! $ke) {
            $this->error('Aplikasi "'.$this->option('dari').'" atau "'.$this->argument('ke').'" belum terdaftar di Pusat.');

            return self::FAILURE;
        }

        if ($this->option('balik')) {
            Pengaturan::simpan('aplikasi_absensi', $dari->kode === 'absensi' ? null : $dari->kode);
            $dari->aktif || $dari->update(['aktif' => true]);
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
        $this->line("Sambungan yang dipindah ke {$ke->nama}: ".(implode(', ', array_keys(array_filter((array) config('pusat.sambungan'), fn ($d) => in_array('absensi', [$d['pemberi'], $d['peminta']], true)))) ?: '-'));
        if ($this->option('nonaktifkan-lama')) {
            $this->warn("{$dari->nama} akan DINONAKTIFKAN di Pusat.");
        }
        if (! $this->option('yakin') && ! $this->confirm('Lanjutkan?', true)) {
            return self::FAILURE;
        }

        $disalin = 0;
        foreach (User::whereIn('id', $pemegang)->get() as $u) {
            // Hanya akses aplikasi pengganti yang ditambah; akses aplikasi lain tidak disentuh.
            $punya = Akses::where('user_id', $u->id)->where('aplikasi_id', $ke->id)->pluck('peran')->all();
            $tambah = array_values(array_diff(
                array_intersect(Akses::where('user_id', $u->id)->where('aplikasi_id', $dari->id)->pluck('peran')->all(), $ke->peran_tersedia ?? []),
                $punya,
            ));
            if (! $tambah) {
                continue;
            }
            foreach ($tambah as $p) {
                Akses::create(['user_id' => $u->id, 'aplikasi_id' => $ke->id, 'peran' => $p]);
            }
            Outbox::akses($u, $ke, array_values(array_merge($punya, $tambah)));
            Audit::catat('akses.diubah', $u->username, ['aplikasi' => $ke->kode, 'peran' => array_merge($punya, $tambah), 'dari' => $dari->kode]);
            $disalin++;
        }

        Pengaturan::simpan('aplikasi_absensi', $ke->kode);
        $this->kabariSambungan([$dari, $ke]);
        if ($this->option('nonaktifkan-lama')) {
            $dari->update(['aktif' => false]);
        }
        Audit::catat('absensi.dialihkan', null, ['dari' => $dari->kode, 'ke' => $ke->kode, 'akses_disalin' => $disalin, 'lama_nonaktif' => (bool) $this->option('nonaktifkan-lama')]);

        $this->info("Selesai: akses $disalin akun disalin ke {$ke->nama}; sambungan absensi kini dilayani {$ke->nama}.");
        $this->line("Berikutnya: di {$ke->nama} tekan Sinkron sekarang; di BK buka Admin → Integrasi → Uji koneksi Absensi.");

        return self::SUCCESS;
    }

    /** Kabari kedua aplikasi + semua mitra sambungan absensi agar menarik sambungan terbaru. */
    private function kabariSambungan(array $apps): void
    {
        foreach (Sambungan::semua() as $jenis => $def) {
            $asli = config("pusat.sambungan.$jenis");
            if (! in_array('absensi', [$asli['pemberi'], $asli['peminta']], true)) {
                continue;
            }
            SambunganService::kabari($jenis);
            foreach ($apps as $a) { // aplikasi lama/baru yang tidak lagi/tidak tercakup juga diberi tahu
                if ($a->aktif && $a->webhook_url && ! in_array($a->kode, [$def['pemberi'], $def['peminta']], true)) {
                    Outbox::antre($a, 'sambungan.diperbarui', ['jenis' => $jenis]);
                }
            }
        }
    }
}
