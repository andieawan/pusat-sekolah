<?php

namespace App\Console\Commands;

use App\Models\Aplikasi;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\AksesService;
use App\Services\Audit;
use App\Support\RedirectUri;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Menyiapkan Pusat untuk uji coba di komputer sendiri: data awal, pendaftaran aplikasi (Absensi, Website),
 * dan (opsional) menuliskan kredensial langsung ke .env aplikasi tersebut. Aman dijalankan berulang.
 */
class SiapkanLokal extends Command
{
    protected $signature = 'pusat:siapkan-lokal
        {--absensi-url= : URL aplikasi Absensi, mis. http://absensi-pusat.test (bawaan: http://127.0.0.1:8001)}
        {--web-url= : URL Website; kosongkan bila tidak dipakai}
        {--pusat-url= : URL Pusat yang dilihat aplikasi lain (bawaan: APP_URL)}
        {--env-absensi= : Path berkas .env Absensi; PUSAT_* ditulis otomatis ke sana}
        {--env-web= : Path berkas .env Website}
        {--demo : Tambah data contoh (kelas, guru, siswa, akun guru) agar sinkron Absensi ada isinya}
        {--putar : Putar ulang rahasia aplikasi yang sudah terdaftar (rahasia lama tidak berlaku lagi)}
        {--paksa : Izinkan di lingkungan production (tidak disarankan)}';

    protected $description = 'Siapkan Pusat untuk uji coba lokal: data awal, daftarkan Absensi/Website, tulis kredensial ke .env mereka';

    public function handle(AksesService $akses): int
    {
        if (app()->isProduction() && ! $this->option('paksa')) {
            $this->error('Perintah ini untuk uji coba lokal. Di server sungguhan daftarkan aplikasi dengan pusat:aplikasi-baru. (Paksa: --paksa)');

            return self::FAILURE;
        }

        $pusatUrl = rtrim((string) ($this->option('pusat-url') ?: config('app.url')), '/');
        $targets = ['absensi' => rtrim((string) ($this->option('absensi-url') ?: 'http://127.0.0.1:8001'), '/')];
        if ($this->option('web-url')) {
            $targets['web'] = rtrim((string) $this->option('web-url'), '/');
        }
        foreach (['Pusat' => $pusatUrl] + $targets as $nama => $url) {
            if (! RedirectUri::sahBentuk($url)) {
                $this->error("URL {$nama} tidak sah: \"{$url}\" (harus http/https).");

                return self::FAILURE;
            }
        }

        $this->callSilent('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->info('Data awal (tahun ajaran, jurusan) siap.');

        $envPaths = ['absensi' => $this->option('env-absensi'), 'web' => $this->option('env-web')];
        $hasil = [];
        foreach ($targets as $kode => $base) {
            $bawaan = config("pusat.aplikasi_bawaan.{$kode}");
            $callback = $kode === 'absensi' ? '/sso/callback' : '/pusat/callback';
            $data = [
                'nama' => $bawaan['nama'],
                'base_url' => $base,
                'redirect_uris' => [$base.$callback],
                'webhook_url' => $base.($bawaan['webhook_path'] ?? '/pusat/webhook'),
                'scope' => $bawaan['scope'],
                'peran_tersedia' => $bawaan['peran'],
                'aktif' => true,
            ];
            $app = Aplikasi::where('kode', $kode)->first();
            $baru = ! $app;
            $app ??= new Aplikasi(['kode' => $kode]);
            $app->fill($data);

            $kredensial = null;
            if ($baru || $this->option('putar')) {
                [$clientId, $secret] = $app->buatKredensial();
                $app->webhook_secret = Str::random(48);
                $kredensial = [$clientId, $secret, $app->webhook_secret];
            }
            $app->save();
            Audit::catat($baru ? 'aplikasi.dibuat_via_cli' : 'aplikasi.diperbarui_via_cli', $kode);

            if ($kredensial) {
                $hasil[$kode] = $kredensial;
                $this->info(($baru ? 'Terdaftar' : 'Rahasia diputar').": {$app->nama} ({$base})");
                $this->line("  PUSAT_URL            = {$pusatUrl}");
                $this->line("  PUSAT_CLIENT_ID      = {$kredensial[0]}");
                $this->line("  PUSAT_CLIENT_SECRET  = {$kredensial[1]}");
                $this->line("  PUSAT_WEBHOOK_SECRET = {$kredensial[2]}");
                if ($path = $envPaths[$kode]) {
                    $this->tulisEnv($path, [
                        'PUSAT_ENABLED' => 'true',
                        'PUSAT_URL' => $pusatUrl,
                        'PUSAT_CLIENT_ID' => $kredensial[0],
                        'PUSAT_CLIENT_SECRET' => $kredensial[1],
                        'PUSAT_WEBHOOK_SECRET' => $kredensial[2],
                    ]);
                }
            } else {
                $this->line("Sudah terdaftar: {$app->nama}. URL diperbarui; rahasia tidak diubah (pakai --putar bila hilang).");
            }
        }

        if ($this->option('demo')) {
            $this->buatDemo($akses);
        }

        $this->newLine();
        $this->line('Superadmin Pusat otomatis berperan "admin" di semua aplikasi, jadi bisa langsung masuk lewat SSO.');
        $this->line('Berikutnya di Absensi: php artisan migrate, lalu Admin → Pusat → Uji koneksi → Periksa → Terapkan.');

        return self::SUCCESS;
    }

    private function buatDemo(AksesService $akses): void
    {
        $ta = TahunAjaran::where('aktif', true)->first() ?? TahunAjaran::first();
        $absensi = Aplikasi::where('kode', 'absensi')->first();
        $jur = Jurusan::pluck('id', 'kode');

        $guru = [];
        foreach ([
            ['DEMO-G1', 'Budi Santoso, S.Pd', 'Guru Produktif'],
            ['DEMO-G2', 'Siti Aminah, S.Pd', 'Guru Matematika'],
            ['DEMO-G3', 'Rahmat Hidayat, S.Kom', 'Guru Produktif'],
        ] as [$nip, $nama, $jabatan]) {
            $guru[] = Pegawai::firstOrCreate(['nip' => $nip], ['nama' => $nama, 'jenis' => 'guru', 'jabatan' => $jabatan, 'aktif' => true]);
        }

        $kelas = [];
        foreach ([['X DKV 1', 'X', 'DKV', 0], ['XI AK 1', 'XI', 'AK', 1]] as [$nama, $tingkat, $kodeJur, $wali]) {
            $kelas[] = Kelas::firstOrCreate(
                ['nama' => $nama, 'tahun_ajaran_id' => $ta->id],
                ['tingkat' => $tingkat, 'jurusan_id' => $jur[$kodeJur] ?? null, 'wali_id' => $guru[$wali]->id],
            );
        }

        $nama = ['Ahmad Fauzi', 'Dewi Lestari', 'Eko Prasetyo', 'Fitri Handayani', 'Gilang Ramadhan', 'Hana Pertiwi', 'Indra Kurniawan', 'Jihan Aulia', 'Khoirul Anam', 'Laila Safitri'];
        foreach ($nama as $i => $n) {
            Siswa::firstOrCreate(['nis' => sprintf('DEMO-%03d', $i + 1)], [
                'nama' => $n,
                'jenis_kelamin' => $i % 2 === 0 ? 'L' : 'P',
                'kelas_id' => $kelas[$i % 2]->id,
                'status' => 'aktif',
            ]);
        }
        $this->info('Data contoh: 3 guru, 2 kelas, 10 siswa (NIP/NIS berawalan DEMO-).');

        if ($absensi && ! User::where('username', 'guru.demo')->exists()) {
            $password = Str::random(12).'a1';
            $user = User::create([
                'username' => 'guru.demo', 'nama' => $guru[0]->nama, 'email' => 'guru.demo@example.test',
                'password' => $password, 'aktif' => true, 'pegawai_id' => $guru[0]->id,
            ]);
            $akses->sinkron($user, [$absensi->id => ['guru']]);
            $this->info('Akun contoh untuk Absensi (peran guru) — catat, tidak ditampilkan lagi:');
            $this->line("  username: guru.demo   password: {$password}");
        }
    }

    /** Ubah/tambah hanya kunci yang diberikan; sisa isi .env tidak disentuh. Cadangan sekali: .env.bak-pusat */
    private function tulisEnv(string $path, array $nilai): void
    {
        if (! is_file($path) || ! is_writable($path)) {
            $this->warn("Berkas .env tidak ditemukan/tidak bisa ditulis: {$path}. Salin nilai di atas secara manual.");

            return;
        }
        $isi = (string) file_get_contents($path);
        if (! is_file($path.'.bak-pusat')) {
            @copy($path, $path.'.bak-pusat');
        }
        $eol = str_contains($isi, "\r\n") ? "\r\n" : "\n";
        foreach ($nilai as $kunci => $v) {
            $baris = $kunci.'='.$v;
            $pola = '/^'.preg_quote($kunci, '/').'=[^\r\n]*/m';
            if (preg_match($pola, $isi)) {
                $isi = preg_replace($pola, str_replace(['\\', '$'], ['\\\\', '\\$'], $baris), $isi, 1);
            } else {
                $isi = rtrim($isi, "\r\n").$eol.$baris.$eol;
            }
        }
        file_put_contents($path, $isi);
        $this->info("PUSAT_* ditulis ke {$path} (cadangan: .env.bak-pusat). Jalankan php artisan config:clear di aplikasi itu.");
    }
}
