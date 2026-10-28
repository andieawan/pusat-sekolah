<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Pegawai;
use App\Models\Pengampu;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WebhookOutbox;

class KosongkanDataTest extends PakaiData
{
    public function test_kosongkan_menghapus_data_induk_dan_akun_kecuali_superadmin(): void
    {
        [$app] = $this->buatAplikasi('absensi', ['siswa', 'kelas', 'pegawai', 'mapel', 'pengampu', 'akun'], ['webhook_url' => 'https://a.test/api/pusat/webhook', 'webhook_secret' => 'x']);
        $ta = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'Ganjil', 'aktif' => true]);
        $j = Jurusan::create(['kode' => 'DKV', 'nama' => 'DKV', 'aktif' => true]);
        $g = Pegawai::create(['nip' => 'G1', 'nama' => 'Guru', 'jenis' => 'guru', 'aktif' => true]);
        $k = Kelas::create(['nama' => 'X DKV 1', 'tingkat' => 'X', 'tahun_ajaran_id' => $ta->id, 'jurusan_id' => $j->id, 'wali_id' => $g->id]);
        Siswa::create(['nis' => '1', 'nama' => 'A', 'jenis_kelamin' => 'L', 'kelas_id' => $k->id, 'status' => 'aktif']);
        $s2 = Siswa::create(['nis' => '2', 'nama' => 'B', 'jenis_kelamin' => 'P', 'kelas_id' => $k->id, 'status' => 'aktif']);
        $s2->delete(); // terhapus lunak pun ikut dibersihkan
        $m = Mapel::create(['kode' => 'MTK', 'nama' => 'Matematika', 'kelompok' => 'umum', 'aktif' => true]);
        Pengampu::create(['pegawai_id' => $g->id, 'mapel_id' => $m->id, 'kelas_id' => $k->id]);
        $admin = $this->buatUser(['superadmin' => true, 'pegawai_id' => $g->id]);
        $guru = $this->buatUser([], $app, ['guru']);
        $this->assertGreaterThan(0, WebhookOutbox::count());

        $this->artisan('pusat:kosongkan-data')->expectsQuestion('Ketik HAPUS SEMUA untuk melanjutkan', 'tidak')->assertExitCode(1);
        $this->assertSame(1, Pegawai::count());

        $this->artisan('pusat:kosongkan-data', ['--yakin' => true])->assertExitCode(0);
        foreach ([Pengampu::class, Kelas::class, Pegawai::class, Mapel::class] as $c) {
            $this->assertSame(0, $c::withTrashed()->count(), $c);
        }
        $this->assertSame(0, Siswa::withTrashed()->count());
        $this->assertSame(0, WebhookOutbox::count());
        $this->assertNull(User::find($guru->id));
        $this->assertNull(User::find($admin->id)->pegawai_id);
        $this->assertSame(1, TahunAjaran::count());
        $this->assertSame(1, Jurusan::count());
    }
}
