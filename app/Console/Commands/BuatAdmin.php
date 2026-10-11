<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BuatAdmin extends Command
{
    protected $signature = 'pusat:buat-admin {username : Username (huruf/angka/. _ -)} {--nama= : Nama lengkap}
                            {--reset : Jika akun sudah ada, setel ulang password-nya}';

    protected $description = 'Buat akun superadmin Pusat (password diminta secara interaktif, tidak disimpan di riwayat shell)';

    public function handle(): int
    {
        $username = strtolower(trim($this->argument('username')));
        if (! preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
            $this->error('Username harus 3-60 karakter: huruf, angka, titik, garis bawah, atau strip.');

            return self::FAILURE;
        }

        $user = User::where('username', $username)->first();
        if ($user && ! $this->option('reset')) {
            $this->error("Akun \"{$username}\" sudah ada. Tambahkan --reset untuk mengganti password-nya.");

            return self::FAILURE;
        }

        $password = $this->secret('Password (min. 10 karakter, ada huruf dan angka)');
        if ($password !== $this->secret('Ulangi password')) {
            $this->error('Password tidak sama.');

            return self::FAILURE;
        }
        $v = Validator::make(['p' => $password], ['p' => [Password::min(10)->letters()->numbers()]]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }

        if ($user) {
            $user->update(['password' => $password, 'superadmin' => true, 'aktif' => true]);
            $this->info("Password akun \"{$username}\" diganti.");
        } else {
            User::create([
                'username' => $username,
                'nama' => $this->option('nama') ?: ucfirst($username),
                'password' => $password,
                'superadmin' => true,
                'aktif' => true,
            ]);
            $this->info("Superadmin \"{$username}\" dibuat.");
        }
        Audit::catat('admin.dibuat_via_cli', $username);

        return self::SUCCESS;
    }
}
