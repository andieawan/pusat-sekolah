<?php

namespace App\Console\Commands;

use App\Models\Aplikasi;
use App\Services\Audit;
use App\Support\RedirectUri;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AplikasiBaru extends Command
{
    protected $signature = 'pusat:aplikasi-baru {kode : mis. absensi, bk, tu, pkl, web}
                            {--base-url= : URL aplikasi, mis. https://absensi.smkibupakusari.sch.id}
                            {--redirect= : Redirect URI SSO (bawaan: <base-url>/sso/callback)}
                            {--webhook= : URL penerima webhook (bawaan: <base-url>/pusat/webhook; Absensi: <base-url>/api/pusat/webhook)}';

    protected $description = 'Daftarkan aplikasi dengan pengaturan bawaan, lalu tampilkan kredensialnya SEKALI';

    public function handle(): int
    {
        $kode = strtolower($this->argument('kode'));
        $bawaan = config("pusat.aplikasi_bawaan.{$kode}");
        $base = rtrim((string) $this->option('base-url'), '/');

        if (! $bawaan) {
            $this->error('Kode tidak dikenal. Pilihan bawaan: '.implode(', ', array_keys(config('pusat.aplikasi_bawaan'))).'. Untuk aplikasi lain gunakan panel admin.');

            return self::FAILURE;
        }
        if (! RedirectUri::sahBentuk($base)) {
            $this->error('Opsi --base-url wajib diisi dengan URL http/https yang sah.');

            return self::FAILURE;
        }
        // Salah isi yang pernah terjadi: --base-url=https://pkl.../login → redirect & webhook ikut salah.
        if (preg_match('#/(login|sso/callback|sso/login|admin|api/pusat/webhook|pusat/webhook)$#i', (string) parse_url($base, PHP_URL_PATH))) {
            $this->error('Isi --base-url dengan alamat dasar aplikasi saja, mis. https://pkl.smkibupakusari.sch.id (tanpa /login, /admin, atau /sso/...).');

            return self::FAILURE;
        }
        if (Aplikasi::where('kode', $kode)->exists()) {
            $this->error("Aplikasi \"{$kode}\" sudah terdaftar. Putar rahasianya lewat panel admin jika perlu.");

            return self::FAILURE;
        }

        $app = new Aplikasi([
            'kode' => $kode,
            'nama' => $bawaan['nama'],
            'base_url' => $base,
            'redirect_uris' => [$this->option('redirect') ?: $base.'/sso/callback'],
            'webhook_url' => $this->option('webhook') ?: $base.($bawaan['webhook_path'] ?? '/pusat/webhook'),
            'scope' => $bawaan['scope'],
            'peran_tersedia' => $bawaan['peran'],
            'aktif' => true,
        ]);
        [$clientId, $secret] = $app->buatKredensial();
        $webhookSecret = Str::random(48);
        $app->webhook_secret = $webhookSecret;
        $app->save();
        Audit::catat('aplikasi.dibuat_via_cli', $kode);

        $this->info("Aplikasi \"{$app->nama}\" terdaftar. SIMPAN nilai berikut sekarang, rahasia tidak ditampilkan lagi:");
        $this->line("  PUSAT_CLIENT_ID      = {$clientId}");
        $this->line("  PUSAT_CLIENT_SECRET  = {$secret}");
        $this->line("  PUSAT_WEBHOOK_SECRET = {$webhookSecret}");
        $this->line('  Redirect URI         = '.implode(', ', $app->redirect_uris));
        $this->line("  Webhook URL          = {$app->webhook_url}");

        return self::SUCCESS;
    }
}
