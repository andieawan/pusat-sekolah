<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\User;

class AkunMassalTest extends PakaiData
{
    public function test_buat_akun_guru_staf_dengan_nip_peran_awal_dan_wajib_ganti_password(): void
    {
        [$absensi] = $this->buatAplikasi('absensi', ['akun'], ['peran_tersedia' => ['guru', 'admin']]);
        [$tu] = $this->buatAplikasi('tu', ['akun'], ['peran_tersedia' => ['pegawai', 'staf_tu']]);
        $g = Pegawai::create(['nip' => '19800101 200501 1 001', 'nama' => 'Drs. Ahmad Fauzi', 'jenis' => 'guru', 'aktif' => true]);
        $s = Pegawai::create(['nip' => '', 'nama' => 'Siti Aminah', 'jenis' => 'staf', 'aktif' => true]);
        $sudah = Pegawai::create(['nip' => '999', 'nama' => 'Sudah Punya', 'jenis' => 'guru', 'aktif' => true]);
        $this->buatUser(['username' => 'lama', 'pegawai_id' => $sudah->id]);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->get('/admin/akun/massal')->assertOk()->assertSee('Drs. Ahmad Fauzi')->assertDontSee('Sudah Punya')
            ->assertSee('198001012005011001')->assertSee('siti');

        $r = $this->actingAs($admin)->post('/admin/akun/massal', [
            'pegawai' => [$g->id, $s->id], 'username' => [$s->id => 'siti'],
            'peran' => [$absensi->id => ['guru' => 'guru', 'staf' => ''], $tu->id => ['guru' => 'pegawai', 'staf' => 'pegawai']],
        ])->assertRedirect('/admin/akun/massal');
        $hasil = session('akun_massal');
        $this->assertCount(2, $hasil);

        $ug = User::where('pegawai_id', $g->id)->firstOrFail();
        $this->assertSame('198001012005011001', $ug->username);
        $this->assertTrue($ug->wajib_ganti_password);
        $this->assertEqualsCanonicalizing(['guru', 'pegawai'], $ug->akses()->pluck('peran')->all());
        $us = User::where('pegawai_id', $s->id)->firstOrFail();
        $this->assertSame('siti', $us->username);
        $this->assertSame(['pegawai'], $us->akses()->pluck('peran')->all());

        // Login pertama dipaksa ganti password, lalu bebas
        auth()->logout();
        $pw = collect($hasil)->firstWhere('username', 'siti')['password'];
        $this->post('/login', ['username' => 'siti', 'password' => $pw])->assertRedirect();
        $this->get('/portal')->assertRedirect(route('profil.password'));
        $this->put('/profil/password', ['password_lama' => $pw, 'password' => 'BaruSekali123', 'password_confirmation' => 'BaruSekali123'])->assertRedirect();
        $this->assertFalse($us->fresh()->wajib_ganti_password);
        $this->get('/portal')->assertOk();

        // halaman tidak menampilkan password lagi
        $this->actingAs($admin)->get('/admin/akun/massal')->assertDontSee($pw);
    }
}
