<?php

namespace App\Console\Commands;

use App\Models\Aplikasi;
use App\Models\User;
use App\Models\WebhookOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Periksa extends Command
{
    protected $signature = 'pusat:periksa {--cepat : Lewati pemeriksaan yang butuh jaringan/DB}';

    protected $description = 'Periksa kesiapan produksi (.env, database, izin folder, cron, aplikasi terdaftar). Aman dijalankan kapan saja.';

    private int $gagal = 0;

    private int $peringatan = 0;

    private function ok(string $t): void
    {
        $this->line("  <info>[OK]</info>     $t");
    }

    private function warn2(string $t, string $saran = ''): void
    {
        $this->peringatan++;
        $this->line("  <comment>[PERINGATAN]</comment> $t".($saran ? "\n             → $saran" : ''));
    }

    private function gagalkan(string $t, string $saran = ''): void
    {
        $this->gagal++;
        $this->line("  <error>[GAGAL]</error>  $t".($saran ? "\n             → $saran" : ''));
    }

    public function handle(): int
    {
        $prod = app()->environment('production');
        $this->info('Pemeriksaan Pusat Sekolah ('.config('app.env').')');

        $this->newLine();
        $this->line('<options=bold>PHP & berkas</>');
        version_compare(PHP_VERSION, '8.3.0', '>=') ? $this->ok('PHP '.PHP_VERSION) : $this->gagalkan('PHP '.PHP_VERSION.' terlalu lama', 'Butuh PHP 8.3+');
        $ext = array_filter(['pdo_mysql', 'mbstring', 'openssl', 'curl', 'xml', 'zip', 'gd'], fn ($e) => ! extension_loaded($e));
        $ext ? $this->gagalkan('Ekstensi PHP belum ada: '.implode(', ', $ext), 'sudo apt install '.implode(' ', array_map(fn ($e) => 'php-'.str_replace('pdo_mysql', 'mysql', $e), $ext)).' lalu muat ulang php-fpm') : $this->ok('Ekstensi PHP lengkap');
        is_file(base_path('vendor/autoload.php')) ? $this->ok('vendor/ ada') : $this->gagalkan('vendor/ tidak ada', 'composer install --no-dev --optimize-autoloader');
        foreach (['storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'] as $d) {
            if (! is_writable(base_path($d))) {
                $this->gagalkan("Folder $d tidak bisa ditulis oleh user ini (".get_current_user().')', 'sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache');
            }
        }
        $this->ok('Folder storage & bootstrap/cache dicek');
        if (is_file(public_path('index.php')) === false) {
            $this->gagalkan('public/index.php tidak ditemukan');
        }

        $this->newLine();
        $this->line('<options=bold>.env</>');
        config('app.key') ? $this->ok('APP_KEY terisi') : $this->gagalkan('APP_KEY kosong', 'php artisan key:generate --force');
        if ($prod) {
            config('app.debug') ? $this->gagalkan('APP_DEBUG=true di produksi (membocorkan rahasia saat error)', 'Ubah ke APP_DEBUG=false') : $this->ok('APP_DEBUG=false');
            str_starts_with((string) config('app.url'), 'https://') ? $this->ok('APP_URL memakai https') : $this->warn2('APP_URL bukan https: '.config('app.url'), 'Pasang HTTPS (NPMplus) lalu ubah APP_URL');
            if (str_starts_with((string) config('app.url'), 'https://') && ! config('session.secure')) {
                $this->gagalkan('SESSION_SECURE_COOKIE belum true padahal HTTPS', 'Set SESSION_SECURE_COOKIE=true');
            }
            if (str_starts_with((string) config('app.url'), 'http://') && config('session.secure')) {
                $this->gagalkan('SESSION_SECURE_COOKIE=true tetapi APP_URL http — login akan terus gagal', 'Pakai HTTPS atau set SESSION_SECURE_COOKIE=false');
            }
            if (in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true) || str_ends_with((string) parse_url((string) config('app.url'), PHP_URL_HOST), '.test')) {
                $this->warn2('APP_URL menunjuk alamat lokal', 'Isi domain asli');
            }
            config('pusat.webhook.verify_ssl') ? $this->ok('PUSAT_WEBHOOK_VERIFY_SSL aktif') : $this->gagalkan('PUSAT_WEBHOOK_VERIFY_SSL=false di produksi', 'Hapus baris itu dari .env');
        }
        env('TRUSTED_PROXIES') ? $this->ok('TRUSTED_PROXIES = '.env('TRUSTED_PROXIES')) : $this->warn2('TRUSTED_PROXIES kosong', 'Jika di belakang NPMplus, isi IP-nya agar HTTPS & IP pengunjung terbaca benar');
        if (config('session.driver') === 'file' && $prod) {
            $this->warn2('SESSION_DRIVER=file', 'Disarankan database');
        }

        if ($this->option('cepat')) {
            return $this->akhir();
        }

        $this->newLine();
        $this->line('<options=bold>Database</>');
        try {
            DB::connection()->getPdo();
            $this->ok('Terhubung ke database "'.DB::connection()->getDatabaseName().'"');
        } catch (\Throwable $e) {
            $this->gagalkan('Tidak bisa terhubung ke database: '.mb_substr($e->getMessage(), 0, 160), 'Periksa DB_* di .env (DB_PASSWORD tidak boleh kosong), lalu php artisan config:clear');

            return $this->akhir();
        }
        if (! Schema::hasTable('migrations')) {
            $this->gagalkan('Tabel belum dibuat', 'php artisan migrate --seed --force');

            return $this->akhir();
        }
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
        $ran = DB::table('migrations')->pluck('migration');
        $belum = $files->diff($ran);
        $belum->isEmpty() ? $this->ok('Semua migrasi sudah dijalankan') : $this->gagalkan($belum->count().' migrasi belum dijalankan', 'php artisan migrate --force');

        $this->newLine();
        $this->line('<options=bold>Data</>');
        $sa = User::where('superadmin', true)->count();
        $sa > 0 ? $this->ok("Superadmin: $sa akun") : $this->gagalkan('Belum ada superadmin', 'php artisan pusat:buat-admin <username>');
        $apl = Aplikasi::where('aktif', true)->get();
        if ($apl->isEmpty()) {
            $this->warn2('Belum ada aplikasi terdaftar', 'Menu Aplikasi → Daftarkan, atau php artisan pusat:aplikasi-baru');
        }
        foreach ($apl as $a) {
            $msg = "Aplikasi {$a->kode}: callback ".($a->redirect_uris[0] ?? '-');
            $uri = implode(' ', (array) ($a->redirect_uris ?? []));
            if ($prod && preg_match('~http://|localhost|127\.0\.0\.1|\.test~', $uri.' '.$a->webhook_url)) {
                $this->warn2($msg.' (alamat lokal/http)', 'Ubah di menu Aplikasi ke alamat produksi (https)');
            } else {
                $this->ok($msg);
            }
        }

        $this->newLine();
        $this->line('<options=bold>Webhook & cron</>');
        $macet = WebhookOutbox::where('status', 'pending')->where('created_at', '<', now()->subMinutes(10))->count();
        $gagal = WebhookOutbox::where('status', 'gagal')->count();
        $macet > 0 ? $this->gagalkan("$macet webhook tertahan >10 menit", 'Cron `schedule:run` belum jalan (crontab -u www-data -l) atau aplikasi tujuan tak terjangkau; lihat menu Aplikasi → Webhook') : $this->ok('Tidak ada webhook tertahan');
        $gagal > 0 ? $this->warn2("$gagal webhook berstatus gagal", 'Periksa URL webhook & secret di aplikasi tujuan') : null;
        $this->line('  <comment>[INFO]</comment>   Cron wajib: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        return $this->akhir();
    }

    private function akhir(): int
    {
        $this->newLine();
        if ($this->gagal) {
            $this->error("{$this->gagal} masalah harus diperbaiki, {$this->peringatan} peringatan.");

            return self::FAILURE;
        }
        $this->info($this->peringatan ? "Siap, dengan {$this->peringatan} peringatan." : 'Semua siap untuk produksi.');

        return self::SUCCESS;
    }
}
