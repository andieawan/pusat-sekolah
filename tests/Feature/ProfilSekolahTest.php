<?php

namespace Tests\Feature;

use App\Models\Akses;
use App\Models\WebhookOutbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilSekolahTest extends PakaiData
{
    public function test_simpan_profil_logo_aturan_password_dan_api(): void
    {
        Storage::fake('local');
        [$app, $sec] = $this->buatAplikasi('bk', ['siswa'], ['webhook_url' => 'https://bk.example.test/api/pusat/webhook', 'webhook_secret' => 'x', 'peran_tersedia' => ['bk', 'guru']]);
        $guruBk = $this->buatUser(['nama' => 'Bu Rina'], $app, ['bk']);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->get('/admin/profil-sekolah')->assertOk()->assertSee('Bu Rina');
        $this->actingAs($admin)->put('/admin/profil-sekolah', [
            'nama' => 'SMK Islam Bustanul Ulum Pakusari', 'kepsek' => 'MUHAMMAD MUSLIM, S.Pd., Gr', 'kepsek_nip' => '7951767668130072',
            'npsn' => '20570966', 'kota' => 'Pakusari', 'pw_min' => 12, 'pw_huruf' => 1, 'pw_angka' => 1,
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        ])->assertSessionHas('sukses');
        $this->assertSame(1, WebhookOutbox::where('event', 'profil.diperbarui')->count());

        $d = $this->getJson('/api/v1/profil', $this->basic($app->client_id, $sec))->assertOk()->json('data');
        $this->assertSame('MUHAMMAD MUSLIM, S.Pd., Gr', $d['kepsek']);
        $this->assertSame('SMK ISLAM BUSTANUL ULUM PAKUSARI', $d['nama_kop']);
        $this->assertSame(['Bu Rina'], $d['guru_bk']);
        $this->assertSame(12, $d['aturan_password']['min']);
        $this->assertNotNull($d['logo_url']);
        $this->get('/logo-sekolah')->assertOk();

        // aturan password baru berlaku untuk ganti password
        $this->actingAs($guruBk)->put('/profil/password', ['password_lama' => 'Rahasia12345', 'password' => 'Pendek12345', 'password_confirmation' => 'Pendek12345'])
            ->assertSessionHasErrors('password');
    }

    public function test_kontak_ortu_lewat_api_butuh_izin(): void
    {
        $s = $this->buatKelasDanSiswa('8001');
        [$tanpa, $st] = $this->buatAplikasi('bk', ['siswa']);
        [$abs, $sa] = $this->buatAplikasi('absensi', ['siswa', 'siswa_kontak']);
        $this->patchJson('/api/v1/siswa/'.$s->uuid.'/kontak', ['no_hp_ortu' => '081234'], $this->basic($tanpa->client_id, $st))->assertForbidden();
        $this->patchJson('/api/v1/siswa/'.$s->uuid.'/kontak', ['no_hp_ortu' => '0812-3456-7890', 'nama_ortu' => 'Pak Budi'], $this->basic($abs->client_id, $sa))
            ->assertOk()->assertJsonPath('data.nama_ortu', 'Pak Budi');
        $this->assertSame('Pak Budi', $s->fresh()->nama_ortu);
        $this->assertNotNull($s->fresh()->no_hp_ortu);
    }
}
