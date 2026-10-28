<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\TahunAjaran;

class OperatorTuTest extends PakaiData
{
    public function test_operator_tu_hanya_bagian_yang_diizinkan(): void
    {
        [$abs] = $this->buatAplikasi('absensi', ['akun']);
        $abs->update(['peran_tersedia' => ['guru', 'admin']]);
        $tu = $this->buatUser(['operator_tu' => true]);
        $biasa = $this->buatUser();
        $ta = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'Ganjil', 'aktif' => true]);
        $g = Pegawai::create(['nip' => 'G1', 'nama' => 'Guru Satu', 'jenis' => 'guru', 'aktif' => true]);

        $this->actingAs($biasa)->get('/admin')->assertForbidden();

        $this->actingAs($tu);
        $this->get('/admin')->assertOk()->assertSee('Buat akun siswa')->assertDontSee('Kenaikan Kelas')->assertDontSee('Kelola aplikasi');
        foreach (['/admin/data/siswa', '/admin/data/pegawai', '/admin/data/kelas', '/admin/data/siswa/baru', '/admin/impor', '/admin/profil-sekolah', '/admin/akun/massal', '/admin/akun/siswa'] as $u) {
            $this->get($u)->assertOk();
        }
        foreach (['/admin/data/tahun-ajaran', '/admin/data/mapel', '/admin/data/jurusan', '/admin/kenaikan', '/admin/pengampu', '/admin/akun', '/admin/aplikasi', '/admin/sambungan', '/admin/webhook', '/admin/audit', '/admin/impor/templat/mapel'] as $u) {
            $this->get($u)->assertForbidden();
        }
        // ubah boleh, hapus tidak
        $this->get("/admin/data/pegawai/{$g->id}/ubah")->assertOk()->assertDontSee('zona-bahaya');
        $this->put("/admin/data/pegawai/{$g->id}", ['nip' => 'G1', 'nama' => 'Guru Satu Baru', 'jenis' => 'guru', 'aktif' => 1])->assertRedirect();
        $this->assertSame('Guru Satu Baru', $g->fresh()->nama);
        $this->delete("/admin/data/pegawai/{$g->id}")->assertForbidden();
        $this->put("/admin/data/tahun-ajaran/{$ta->id}", ['nama' => '2026/2027', 'semester' => 'Genap', 'aktif' => 1])->assertForbidden();
        $this->assertSame('Ganjil', $ta->fresh()->semester);
        $this->post('/admin/impor/pratinjau', ['sumber' => 'mapel'])->assertForbidden();

        // buat akun: peran admin ditolak, peran guru boleh
        $this->post('/admin/akun/massal', ['pegawai' => [$g->id], 'peran' => [$abs->id => ['guru' => 'admin']]])->assertSessionHas('galat');
        $this->post('/admin/akun/massal', ['pegawai' => [$g->id], 'peran' => [$abs->id => ['guru' => 'guru']]])->assertSessionHas('sukses');
        $this->assertNotNull($g->fresh()->akun);

        // aturan password tidak bisa diubah operator
        $this->get('/admin/profil-sekolah')->assertDontSee('Aturan password akun');
        $this->put('/admin/profil-sekolah', ['nama' => 'SMK Uji', 'pw_min' => 20])->assertRedirect();
        $this->assertNotSame('20', \App\Models\Pengaturan::ambil('pw_min'));
    }

    public function test_superadmin_bisa_menjadikan_akun_operator_tu(): void
    {
        $admin = $this->buatUser(['superadmin' => true]);
        $u = $this->buatUser(['username' => 'staf.tu']);
        $this->actingAs($admin)->get("/admin/akun/{$u->id}/edit")->assertOk()->assertSee('Operator TU di Pusat');
    }

    public function test_status_admin_pusat_dikabarkan_ke_aplikasi(): void
    {
        [$tu] = $this->buatAplikasi('tu', ['akun'], ['webhook_url' => 'https://tu.test/api/pusat/webhook', 'webhook_secret' => 'x']);
        $u = $this->buatUser(['username' => 'staf.tu'], $tu, ['staf_tu']);
        \App\Models\WebhookOutbox::query()->delete();
        $u->update(['operator_tu' => true]);
        $w = \App\Models\WebhookOutbox::where('event', 'akun.diperbarui')->where('aplikasi_id', $tu->id)->firstOrFail();
        $this->assertTrue($w->payload['data']['admin_pusat']);
    }
}
