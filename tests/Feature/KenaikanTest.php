<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\WebhookOutbox;

class KenaikanTest extends PakaiData
{
    public function test_naik_tinggal_lulus_dan_tahun_ajaran_baru_aktif(): void
    {
        $this->buatAplikasi('absensi', ['siswa', 'kelas', 'tahun_ajaran'], ['webhook_url' => 'https://absensi.example.test/api/pusat/webhook', 'webhook_secret' => 'x']);
        $lama = TahunAjaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $dkv = Jurusan::create(['kode' => 'DKV', 'nama' => 'Desain Komunikasi Visual']);
        $x = Kelas::create(['nama' => 'X DKV 1', 'tingkat' => 'X', 'jurusan_id' => $dkv->id, 'tahun_ajaran_id' => $lama->id]);
        $xii = Kelas::create(['nama' => 'XII DKV 1', 'tingkat' => 'XII', 'jurusan_id' => $dkv->id, 'tahun_ajaran_id' => $lama->id]);
        $a = Siswa::create(['nis' => '1', 'nama' => 'Ani', 'kelas_id' => $x->id, 'status' => 'aktif']);
        $b = Siswa::create(['nis' => '2', 'nama' => 'Budi', 'kelas_id' => $x->id, 'status' => 'aktif']);
        $c = Siswa::create(['nis' => '3', 'nama' => 'Cici', 'kelas_id' => $xii->id, 'status' => 'aktif']);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->get('/admin/kenaikan')->assertOk()->assertSee('Buat tahun ajaran')->assertSee('2027/2028');
        $this->actingAs($admin)->post('/admin/kenaikan/tahun-baru', ['nama' => '2027/2028'])->assertRedirect();
        $baru = TahunAjaran::where('nama', '2027/2028')->firstOrFail();
        $this->assertFalse($baru->aktif);

        $this->actingAs($admin)->get('/admin/kenaikan?dari='.$lama->id.'&ke='.$baru->id)->assertOk()
            ->assertSee('XI DKV 1 (kelas baru)', false)->assertSee('Lulus');

        WebhookOutbox::query()->delete();
        $this->actingAs($admin)->post('/admin/kenaikan', [
            'dari' => $lama->id, 'ke' => $baru->id, 'aktifkan' => 1, 'yakin' => 1,
            'tujuan' => [$x->id => 'baru:XI DKV 1', $xii->id => 'lulus'],
            'siswa' => [$a->id => 'naik', $b->id => 'tinggal', $c->id => 'naik'],
        ])->assertRedirect()->assertSessionHas('sukses');

        $xiBaru = Kelas::where('tahun_ajaran_id', $baru->id)->where('nama', 'XI DKV 1')->firstOrFail();
        $this->assertSame('XI', $xiBaru->tingkat);
        $this->assertSame($dkv->id, $xiBaru->jurusan_id);
        $this->assertSame($xiBaru->id, $a->fresh()->kelas_id);
        $xBaru = Kelas::where('tahun_ajaran_id', $baru->id)->where('nama', 'X DKV 1')->firstOrFail(); // tinggal kelas
        $this->assertSame($xBaru->id, $b->fresh()->kelas_id);
        $this->assertSame('lulus', $c->fresh()->status);
        $this->assertTrue($baru->fresh()->aktif);
        $this->assertFalse($lama->fresh()->aktif);
        $this->assertGreaterThanOrEqual(3, WebhookOutbox::where('event', 'siswa.diperbarui')->count());

        // Dijalankan lagi: tidak ada siswa aktif tersisa di kelas lama
        $this->actingAs($admin)->get('/admin/kenaikan?dari='.$lama->id.'&ke='.$baru->id)->assertOk()->assertSee('Tidak ada siswa aktif');
    }

    public function test_tanpa_konfirmasi_ditolak(): void
    {
        $lama = TahunAjaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $baru = TahunAjaran::create(['nama' => '2027/2028']);
        $this->actingAs($this->buatUser(['superadmin' => true]))->post('/admin/kenaikan', ['dari' => $lama->id, 'ke' => $baru->id])->assertSessionHasErrors('yakin');
        $this->actingAs($this->buatUser())->get('/admin/kenaikan')->assertForbidden();
    }
}
