<?php

namespace Tests\Feature;

use App\Models\Siswa;

class ApiMasterTest extends PakaiData
{
    public function test_tanpa_kredensial_ditolak(): void
    {
        $this->getJson('/api/v1/master/siswa')->assertStatus(401);
    }

    public function test_scope_tidak_cukup_ditolak(): void
    {
        [$app, $secret] = $this->buatAplikasi('web', ['jurusan']);

        $this->getJson('/api/v1/master/siswa', $this->basic($app->client_id, $secret))
            ->assertStatus(403)->assertJsonPath('error.kode', 'scope_tidak_cukup');
    }

    public function test_scope_lengkap_menyertakan_kontak_dan_dasar_tidak(): void
    {
        $s = $this->buatKelasDanSiswa();
        $s->update(['no_hp_ortu' => '08123456789']);

        [$lengkap, $sl] = $this->buatAplikasi('absensi', ['siswa']);
        [$dasar, $sd] = $this->buatAplikasi('web', ['siswa_dasar']);

        $this->getJson('/api/v1/master/siswa', $this->basic($lengkap->client_id, $sl))
            ->assertOk()->assertJsonPath('data.0.no_hp_ortu', '08123456789');

        $res = $this->getJson('/api/v1/master/siswa', $this->basic($dasar->client_id, $sd))->assertOk();
        $this->assertArrayNotHasKey('no_hp_ortu', $res->json('data.0'));
        $this->assertSame('1001', $res->json('data.0.nis'));
    }

    public function test_updated_since_menyertakan_data_yang_dihapus(): void
    {
        [$app, $secret] = $this->buatAplikasi('absensi', ['siswa']);
        $s = $this->buatKelasDanSiswa('2001');

        $this->travel(10)->minutes();
        $batas = now()->subMinutes(5)->toIso8601String();
        $s->delete();

        $res = $this->getJson('/api/v1/master/siswa?'.http_build_query(['updated_since' => $batas]), $this->basic($app->client_id, $secret))->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertNotNull($res->json('data.0.dihapus'));

        // Tanpa updated_since, data terhapus tidak muncul.
        $this->getJson('/api/v1/master/siswa', $this->basic($app->client_id, $secret))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_per_page_dibatasi(): void
    {
        [$app, $secret] = $this->buatAplikasi('absensi', ['siswa']);

        $this->getJson('/api/v1/master/siswa?per_page=9999', $this->basic($app->client_id, $secret))->assertStatus(422);
    }

    public function test_aplikasi_nonaktif_ditolak(): void
    {
        [$app, $secret] = $this->buatAplikasi('absensi', ['siswa']);
        $app->update(['aktif' => false]);

        $this->getJson('/api/v1/ping', $this->basic($app->client_id, $secret))->assertStatus(401);
    }

    public function test_siswa_keluar_menonaktifkan_akunnya(): void
    {
        [$app] = $this->buatAplikasi('absensi', ['siswa']);
        $s = $this->buatKelasDanSiswa('3001');
        $u = $this->buatUser(['siswa_id' => $s->id], $app, ['siswa']);

        Siswa::find($s->id)->update(['status' => 'keluar']);

        $this->assertFalse($u->fresh()->aktif);
    }
}
