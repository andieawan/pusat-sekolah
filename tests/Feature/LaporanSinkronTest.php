<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Services\Outbox;
use App\Services\LaporanSinkron;

class LaporanSinkronTest extends PakaiData
{
    public function test_laporan_atas_permintaan_pusat_menjadi_notifikasi_sekali(): void
    {
        [$app, $secret] = $this->buatAplikasi('jurnal', ['siswa'], ['webhook_url' => 'https://jurnal.example.test/h', 'webhook_secret' => 'x']);
        Outbox::mintaSinkron(null, 'uji');
        $this->assertNotNull($app->fresh()->sinkron_diminta_pada);

        $hasil = ['siswa' => ['baru' => 3, 'ubah' => 1, 'sama' => 200], 'kelas' => ['baru' => 0, 'sama' => 9], 'tahun_aktif' => '2026/2027'];
        $this->postJson('/api/v1/sinkron/laporan', ['ok' => true, 'pemicu' => 'otomatis', 'hasil' => $hasil], $this->basic($app->client_id, $secret))
            ->assertOk()->assertJson(['notifikasi' => true]);
        $n = Notifikasi::sole();
        $this->assertSame('Jurnal selesai sinkron', $n->judul);
        $this->assertSame('siswa 3 baru, 1 diubah', $n->isi);
        $app->refresh();
        $this->assertNull($app->sinkron_diminta_pada);
        $this->assertTrue($app->sinkron_laporan['ok']);

        // sinkron otomatis rutin berikutnya: hanya dicatat, tanpa notifikasi
        $this->postJson('/api/v1/sinkron/laporan', ['ok' => true, 'hasil' => []], $this->basic($app->client_id, $secret))->assertJson(['notifikasi' => false]);
        $this->assertSame(1, Notifikasi::count());
    }

    public function test_gagal_diberitahu_sekali_lalu_pulih(): void
    {
        [$app] = $this->buatAplikasi('bk');
        $this->assertNotNull(LaporanSinkron::terima($app, ['ok' => false, 'galat' => 'Pusat belum punya tahun ajaran aktif.']));
        $this->assertNull(LaporanSinkron::terima($app->fresh(), ['ok' => false, 'galat' => 'Pusat belum punya tahun ajaran aktif.']));
        $n = LaporanSinkron::terima($app->fresh(), ['ok' => true, 'hasil' => []]);
        $this->assertSame('Bk selesai sinkron (pulih)', $n->judul);
        $this->assertSame('galat', Notifikasi::oldest('id')->first()->jenis);
    }

    public function test_admin_melihat_dan_menandai_notifikasi(): void
    {
        [$app] = $this->buatAplikasi('tu');
        LaporanSinkron::terima($app, ['ok' => true, 'pemicu' => 'manual', 'hasil' => ['siswa' => ['baru' => 1]]]);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->getJson(route('admin.notifikasi.baru', ['sejak' => 0]))->assertOk()->assertJson(['jumlah' => 1])->assertJsonPath('baru.0.judul', 'Tu selesai sinkron');
        $this->actingAs($admin)->get(route('admin.notifikasi.index'))->assertOk()->assertSee('Disinkronkan manual dari aplikasi.');
        $this->actingAs($admin)->get(route('admin.aplikasi.index'))->assertOk()->assertSee('✓ selesai')->assertSee('Notifikasi (1)');
        $this->actingAs($admin)->post(route('admin.notifikasi.baca'))->assertRedirect();
        $this->assertSame(0, Notifikasi::belumDibaca());
    }

    public function test_operator_tu_tidak_bisa_membuka_notifikasi(): void
    {
        $tu = $this->buatUser(['operator_tu' => true]);
        $tu->forceFill(['operator_tu' => true])->save();
        $this->actingAs($tu)->get(route('admin.notifikasi.index'))->assertForbidden();
    }
}
