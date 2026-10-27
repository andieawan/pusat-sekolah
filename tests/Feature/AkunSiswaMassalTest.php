<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;

class AkunSiswaMassalTest extends PakaiData
{
    public function test_buat_akun_siswa_per_kelas_peran_pkl_dan_nonaktif_saat_lulus(): void
    {
        [$pkl] = $this->buatAplikasi('pkl', ['akun'], ['peran_tersedia' => ['siswa', 'admin']]);
        $ta = TahunAjaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $xi = Kelas::create(['nama' => 'XI DKV 1', 'tingkat' => 'XI', 'tahun_ajaran_id' => $ta->id]);
        $x = Kelas::create(['nama' => 'X DKV 1', 'tingkat' => 'X', 'tahun_ajaran_id' => $ta->id]);
        $a = Siswa::create(['nis' => '2001', 'nama' => 'Ani', 'kelas_id' => $xi->id, 'status' => 'aktif']);
        $b = Siswa::create(['nis' => '2002', 'nama' => 'Budi', 'kelas_id' => $xi->id, 'status' => 'aktif']);
        Siswa::create(['nis' => '1001', 'nama' => 'Cici', 'kelas_id' => $x->id, 'status' => 'aktif']);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->get('/admin/akun/siswa')->assertOk()->assertSee('XI DKV 1')->assertDontSee('Ani');
        $this->actingAs($admin)->get('/admin/akun/siswa?kelas[]='.$xi->id)->assertOk()->assertSee('Ani')->assertSee('2001')->assertDontSee('Cici');

        $this->actingAs($admin)->post('/admin/akun/siswa', ['siswa' => [$a->id, $b->id], 'peran' => [$pkl->id => 'siswa']])
            ->assertRedirect('/admin/akun/siswa');
        $hasil = session('akun_siswa');
        $this->assertCount(2, $hasil);
        $ua = User::where('siswa_id', $a->id)->firstOrFail();
        $this->assertSame('2001', $ua->username);
        $this->assertTrue($ua->wajib_ganti_password);
        $this->assertSame(['siswa'], $ua->akses()->pluck('peran')->all());
        $this->assertSame(0, User::whereHas('siswa', fn ($q) => $q->where('nis', '1001'))->count());

        $a->update(['status' => 'lulus']);
        $this->assertFalse($ua->fresh()->aktif);
    }
}
