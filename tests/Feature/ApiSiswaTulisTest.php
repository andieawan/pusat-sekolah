<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\WebhookOutbox;

/** API tulis siswa (scope siswa_tulis) untuk mutasi di aplikasi Tata Usaha. */
class ApiSiswaTulisTest extends PakaiData
{
    public function test_butuh_scope_siswa_tulis(): void
    {
        [$app, $secret] = $this->buatAplikasi('absensi', ['siswa']);
        $this->postJson('/api/v1/siswa', ['nis' => '9'], $this->basic($app->client_id, $secret))
            ->assertStatus(403)->assertJsonPath('error.kode', 'scope_tidak_cukup');
    }

    public function test_mutasi_masuk_membuat_siswa_dan_mengabari_aplikasi_lain(): void
    {
        $lama = $this->buatKelasDanSiswa('1001');
        $kelas = Kelas::first();
        [$tu, $secret] = $this->buatAplikasi('tu', ['siswa', 'siswa_tulis']);
        $this->buatAplikasi('bk', ['siswa'], ['webhook_url' => 'https://bk.example.test/api/pusat/webhook', 'webhook_secret' => 'x']);

        $res = $this->postJson('/api/v1/siswa', [
            'nis' => '2001', 'nama' => 'Siswa Pindahan', 'jenis_kelamin' => 'P', 'kelas_uuid' => $kelas->uuid,
            'no_hp_ortu' => '0812-3456-789', 'petugas' => 'Bu TU',
        ], $this->basic($tu->client_id, $secret))->assertStatus(201);

        $this->assertSame('2001', $res->json('data.nis'));
        $this->assertSame($kelas->uuid, $res->json('data.kelas_uuid'));
        $this->assertSame('08123456789', $res->json('data.no_hp_ortu'));
        $this->assertSame(1, WebhookOutbox::where('event', 'siswa.dibuat')->whereHas('aplikasi', fn ($q) => $q->where('kode', 'bk'))->count());
        $this->assertSame('Bu TU', AuditLog::where('aksi', 'siswa.dibuat_api')->first()->detail['petugas']);

        // NIS ganda ditolak
        $this->postJson('/api/v1/siswa', ['nis' => $lama->nis, 'nama' => 'X', 'jenis_kelamin' => 'L', 'kelas_uuid' => $kelas->uuid], $this->basic($tu->client_id, $secret))
            ->assertStatus(409)->assertJsonPath('error.kode', 'nis_sudah_ada');
        // Kelas tak dikenal ditolak
        $this->postJson('/api/v1/siswa', ['nis' => '3001', 'nama' => 'X', 'jenis_kelamin' => 'L', 'kelas_uuid' => 'tidak-ada'], $this->basic($tu->client_id, $secret))
            ->assertStatus(422)->assertJsonPath('error.kode', 'kelas_tidak_ada');
    }

    public function test_siswa_yang_pernah_dihapus_dipulihkan_bukan_digandakan(): void
    {
        $s = $this->buatKelasDanSiswa('1001');
        $uuid = $s->uuid;
        $s->delete();
        [$tu, $secret] = $this->buatAplikasi('tu', ['siswa_tulis']);

        $this->postJson('/api/v1/siswa', ['nis' => '1001', 'nama' => 'Kembali', 'jenis_kelamin' => 'L', 'kelas_uuid' => Kelas::first()->uuid], $this->basic($tu->client_id, $secret))
            ->assertStatus(201)->assertJsonPath('data.uuid', $uuid);
        $this->assertSame(1, Siswa::withTrashed()->count());
    }

    public function test_mutasi_keluar_dan_pindah_kelas(): void
    {
        $s = $this->buatKelasDanSiswa('1001');
        $kelasB = Kelas::create(['nama' => 'X AK 2', 'tingkat' => 'X', 'tahun_ajaran_id' => Kelas::first()->tahun_ajaran_id]);
        [$tu, $secret] = $this->buatAplikasi('tu', ['siswa_tulis']);
        $h = $this->basic($tu->client_id, $secret);

        $this->patchJson('/api/v1/siswa/'.$s->uuid, ['kelas_uuid' => $kelasB->uuid], $h)->assertOk()->assertJsonPath('data.kelas_uuid', $kelasB->uuid);
        $this->patchJson('/api/v1/siswa/'.$s->uuid, ['status' => 'pindah'], $h)->assertOk()->assertJsonPath('data.status', 'pindah');
        $this->assertSame('pindah', $s->fresh()->status);

        $this->patchJson('/api/v1/siswa/'.$s->uuid, ['status' => 'hilang'], $h)->assertStatus(422);
        $this->patchJson('/api/v1/siswa/'.$s->uuid, ['nama' => 'Ganti Nama'], $h)->assertStatus(422); // hanya status/kelas
        $this->assertSame('Siswa 1001', $s->fresh()->nama);
        $this->patchJson('/api/v1/siswa/00000000-0000-0000-0000-000000000000', ['status' => 'aktif'], $h)->assertStatus(404);
    }
}
