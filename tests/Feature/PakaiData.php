<?php

namespace Tests\Feature;

use App\Models\Akses;
use App\Models\Aplikasi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pembantu bersama untuk tes fitur. */
abstract class PakaiData extends TestCase
{
    use RefreshDatabase;

    protected const REDIRECT = 'https://absensi.example.test/sso/callback';

    /** @return array{0:Aplikasi,1:string} [aplikasi, secret polos] */
    protected function buatAplikasi(string $kode = 'absensi', array $scope = ['siswa', 'kelas', 'akun'], array $ekstra = []): array
    {
        $app = new Aplikasi(array_merge([
            'kode' => $kode,
            'nama' => ucfirst($kode),
            'base_url' => 'https://'.$kode.'.example.test',
            'redirect_uris' => [self::REDIRECT],
            'scope' => $scope,
            'peran_tersedia' => ['admin', 'guru'],
            'aktif' => true,
        ], $ekstra));
        $app->uuid = (string) \Illuminate\Support\Str::uuid();
        if (isset($ekstra['webhook_secret'])) {
            $app->webhook_secret = $ekstra['webhook_secret']; // tidak fillable
        }
        [, $secret] = $app->buatKredensial();
        $app->save();

        return [$app, $secret];
    }

    protected function buatUser(array $atribut = [], ?Aplikasi $app = null, array $peran = []): User
    {
        $u = new User(array_merge([
            'username' => 'guru'.random_int(1000, 9999),
            'nama' => 'Guru Uji',
            'password' => 'Rahasia12345',
            'aktif' => true,
        ], $atribut));
        $u->uuid = (string) \Illuminate\Support\Str::uuid();
        $u->superadmin = $atribut['superadmin'] ?? false;
        $u->save();
        foreach ($peran as $p) {
            Akses::create(['user_id' => $u->id, 'aplikasi_id' => $app->id, 'peran' => $p]);
        }

        return $u;
    }

    protected function buatKelasDanSiswa(string $nis = '1001'): Siswa
    {
        $ta = TahunAjaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'X AK 1', 'tingkat' => 'X', 'tahun_ajaran_id' => $ta->id]);

        return Siswa::create(['nis' => $nis, 'nama' => 'Siswa '.$nis, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    }

    protected function basic(string $id, string $secret): array
    {
        return ['Authorization' => 'Basic '.base64_encode($id.':'.$secret)];
    }
}
