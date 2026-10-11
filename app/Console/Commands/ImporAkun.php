<?php

namespace App\Console\Commands;

use App\Models\Akses;
use App\Models\Aplikasi;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\AksesService;
use App\Services\Audit;
use App\Support\CsvParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Membuat akun Pusat + peran di satu aplikasi dari CSV (mis. hasil `php artisan pusat:ekspor` di Absensi).
 * Kolom: username; nama; email; nip; peran  (peran dipisah "|", mis. guru|bk).
 * Tanpa --terapkan hanya simulasi. Password acak dicatat ke satu file yang HARUS dibagikan lalu dihapus.
 */
class ImporAkun extends Command
{
    protected $signature = 'pusat:impor-akun {berkas : Path CSV} {--aplikasi=absensi : Kode aplikasi tempat peran diberikan} {--terapkan : Tulis ke database (tanpa ini hanya simulasi)}';

    protected $description = 'Buat akun guru/staf dan peran di satu aplikasi dari CSV';

    public function handle(AksesService $akses): int
    {
        $app = Aplikasi::where('kode', $this->option('aplikasi'))->first();
        if (! $app) {
            $this->error('Aplikasi "'.$this->option('aplikasi').'" belum terdaftar (pusat:aplikasi-baru).');

            return self::FAILURE;
        }
        $path = $this->argument('berkas');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Berkas tidak ditemukan.');

            return self::FAILURE;
        }
        try {
            $csv = CsvParser::parse(file_get_contents($path));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (array_diff(['username', 'nama', 'peran'], $csv['header'])) {
            $this->error('Kolom wajib: username, nama, peran (opsional: email, nip).');

            return self::FAILURE;
        }

        $terapkan = (bool) $this->option('terapkan');
        $ok = $galat = 0;
        $baru = [];
        $terlihat = [];

        foreach ($csv['baris'] as $b) {
            $no = $b['_baris'] ?? '?';
            $username = strtolower(trim($b['username'] ?? ''));
            $nama = trim($b['nama'] ?? '');
            $email = trim($b['email'] ?? '') ?: null;
            $nip = trim($b['nip'] ?? '');
            $peran = array_values(array_filter(array_map(fn ($p) => strtolower(trim($p)), explode('|', $b['peran'] ?? ''))));
            $masalah = [];

            if (! preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
                $masalah[] = 'username tidak sah';
            } elseif (isset($terlihat[$username])) {
                $masalah[] = "username ganda (baris {$terlihat[$username]})";
            }
            if ($nama === '' || mb_strlen($nama) > 150) {
                $masalah[] = 'nama wajib (maks 150)';
            }
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $masalah[] = 'email tidak sah';
            }
            $tidakDikenal = array_diff($peran, $app->peran_tersedia ?? []);
            if ($peran === [] || $tidakDikenal) {
                $masalah[] = 'peran harus salah satu dari: '.implode(', ', $app->peran_tersedia ?? []);
            }
            $pegawai = null;
            if ($nip !== '' && ! ($pegawai = Pegawai::where('nip', $nip)->first())) {
                $masalah[] = "pegawai dengan NIP/ID \"{$nip}\" belum ada (impor guru dulu)";
            }

            if ($masalah) {
                $galat++;
                $this->warn("Baris {$no}: ".implode('; ', $masalah));

                continue;
            }
            $terlihat[$username] = $no;
            $ok++;

            if (! $terapkan) {
                continue;
            }

            $user = User::where('username', $username)->first();
            if (! $user) {
                $password = Str::password(14, symbols: false);
                $user = new User(['username' => $username, 'nama' => $nama, 'email' => $email, 'password' => $password, 'aktif' => true]);
                $user->pegawai_id = $pegawai?->id;
                $user->save();
                $baru[] = [$username, $nama, $password];
            } else {
                $user->nama = $nama;
                $user->email = $email ?? $user->email;
                $user->pegawai_id ??= $pegawai?->id;
                $user->save();
            }

            // Pertahankan peran di aplikasi lain; hanya aplikasi ini yang diganti.
            $peta = Akses::where('user_id', $user->id)->get()->groupBy('aplikasi_id')->map->pluck('peran')->map->all()->all();
            $peta[$app->id] = $peran;
            $akses->sinkron($user, $peta);
        }

        $this->info(($terapkan ? 'Diterapkan' : 'Simulasi')." — sah: {$ok}, bermasalah: {$galat}, akun baru: ".count($baru).'.');
        if (! $terapkan) {
            $this->line('Jalankan ulang dengan --terapkan untuk menulis ke database.');

            return $galat ? self::FAILURE : self::SUCCESS;
        }

        Audit::catat('impor.akun', $app->kode, ['sah' => $ok, 'baru' => count($baru), 'galat' => $galat]);
        if ($baru) {
            $file = storage_path('app/private/akun-baru-'.date('Ymd-His').'.csv');
            @mkdir(dirname($file), 0750, true);
            $h = fopen($file, 'w');
            fputcsv($h, ['username', 'nama', 'password_sementara'], ';', '"', '');
            foreach ($baru as $r) {
                fputcsv($h, $r, ';', '"', '');
            }
            fclose($h);
            chmod($file, 0600);
            $this->warn("Password sementara disimpan di: {$file}");
            $this->warn('Bagikan ke masing-masing guru, minta mereka menggantinya, lalu HAPUS berkas ini.');
        }

        return $galat ? self::FAILURE : self::SUCCESS;
    }
}
