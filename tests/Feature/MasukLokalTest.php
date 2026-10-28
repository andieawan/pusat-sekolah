<?php

namespace Tests\Feature;

use App\Models\Pegawai;

class MasukLokalTest extends PakaiData
{
    public function test_aplikasi_memeriksa_password_akun_pusat(): void
    {
        [$app, $sec] = $this->buatAplikasi('absensi', ['akun']);
        [$lain, $sl] = $this->buatAplikasi('web', ['jurusan']);
        $p = Pegawai::create(['nip' => 'G008', 'nama' => 'Abdul', 'jenis' => 'guru', 'aktif' => true]);
        $u = $this->buatUser(['username' => 'g008', 'password' => 'Rahasia123!', 'pegawai_id' => $p->id], $app, ['guru']);
        $tanpa = $this->buatUser(['username' => 'tanpa', 'password' => 'Rahasia123!']);
        $h = $this->basic($app->client_id, $sec);
        $kirim = fn ($user, $pw) => $this->postJson('/api/v1/masuk-lokal', ['username' => $user, 'password' => $pw, 'ip' => '10.0.0.5'], $h);

        $kirim('G008', 'Rahasia123!')->assertOk()->assertJsonPath('sub', $u->uuid)->assertJsonPath('peran.0', 'guru')->assertJsonPath('pegawai.uuid', $p->uuid);
        $kirim('g008', 'salah')->assertStatus(401)->assertJsonPath('error.kode', 'kredensial_salah');
        $kirim('tanpa', 'Rahasia123!')->assertStatus(403)->assertJsonPath('error.kode', 'tanpa_akses');
        $u->forceFill(['wajib_ganti_password' => true])->save();
        $kirim('g008', 'Rahasia123!')->assertStatus(403)->assertJsonPath('error.kode', 'wajib_ganti_password');
        $this->postJson('/api/v1/masuk-lokal', ['username' => 'g008', 'password' => 'x'], $this->basic($lain->client_id, $sl))->assertForbidden();

        foreach (range(1, 5) as $i) {
            $kirim('g008', 'salah');
        }
        $kirim('g008', 'Rahasia123!')->assertStatus(429);
    }
}
