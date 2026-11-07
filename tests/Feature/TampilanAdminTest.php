<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;

class TampilanAdminTest extends PakaiData
{
    public function test_ringkasan_daftar_periksa_dan_menu_samping(): void
    {
        $this->buatKelasDanSiswa('3001');
        $this->actingAs($this->buatUser(['superadmin' => true]))->get('/admin')->assertOk()
            ->assertSee('Daftar periksa')->assertSee('Profil sekolah')->assertSee('Isi profil')
            ->assertSee('Wali kelas')->assertSee('1 kelas belum punya wali kelas')
            ->assertSee('Data induk')->assertSee('Integrasi');
    }

    public function test_daftar_periksa_data_kosong_tidak_dicentang(): void
    {
        $this->actingAs($this->buatUser(['superadmin' => true]))->get('/admin')->assertOk()
            ->assertSee('Belum ada kelas di tahun ajaran aktif')->assertSee('Belum ada data guru')
            ->assertDontSee('Semua kelas sudah punya wali kelas')->assertDontSee('Semua guru &amp; staf sudah punya akun', false);
    }

    public function test_saringan_siswa_per_kelas_dan_hapus_di_halaman_ubah(): void
    {
        $s = $this->buatKelasDanSiswa('3001');
        $lain = Kelas::create(['nama' => 'XI AK 1', 'tingkat' => 'XI', 'tahun_ajaran_id' => $s->kelas->tahun_ajaran_id]);
        Siswa::create(['nis' => '3002', 'nama' => 'Siswa Lain', 'kelas_id' => $lain->id, 'status' => 'aktif']);
        $admin = $this->buatUser(['superadmin' => true]);
        $this->actingAs($admin)->get('/admin/data/siswa?kelas_id='.$lain->id)->assertOk()->assertSee('Siswa Lain')->assertDontSee('Siswa 3001')->assertSee('1 data');
        $this->actingAs($admin)->get('/admin/data/siswa?q=tidakada')->assertOk()->assertSee('Tidak ada yang cocok');
        $this->actingAs($admin)->get('/admin/data/siswa/'.$s->id.'/ubah')->assertOk()->assertSee('Hapus siswa');
    }

    public function test_portal_kartu_aplikasi(): void
    {
        [$app] = $this->buatAplikasi('jurnal', ['siswa'], ['peran_tersedia' => ['guru']]);
        $u = $this->buatUser([], $app, ['guru']);
        $this->actingAs($u)->get('/portal')->assertOk()->assertSee('Jurnal mengajar')->assertSee('Buka →', false)->assertDontSee('Buka Administrasi');
    }
}
