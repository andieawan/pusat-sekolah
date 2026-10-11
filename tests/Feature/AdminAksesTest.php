<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;

class AdminAksesTest extends PakaiData
{
    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_akun_biasa_tidak_boleh_masuk_panel(): void
    {
        $this->actingAs($this->buatUser())->get('/admin')->assertForbidden();
    }

    public function test_superadmin_boleh_dan_halaman_inti_terbuka(): void
    {
        $admin = $this->buatUser(['superadmin' => true]);
        $this->buatAplikasi();
        $this->actingAs($admin);

        foreach (['/admin', '/admin/akun', '/admin/aplikasi', '/admin/webhook', '/admin/audit', '/admin/impor',
            '/admin/data/siswa', '/admin/data/kelas', '/admin/data/pegawai', '/admin/data/jurusan', '/admin/data/tahun-ajaran'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_login_berhasil_dan_gagal(): void
    {
        $u = $this->buatUser(['username' => 'Budi', 'password' => 'Rahasia12345']);

        $this->post('/login', ['username' => 'budi', 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'BUDI', 'password' => 'Rahasia12345'])->assertRedirect(route('portal'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_login_dibatasi_setelah_lima_kali_gagal(): void
    {
        $this->buatUser(['username' => 'siti', 'password' => 'Rahasia12345']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'siti', 'password' => 'salah']);
        }

        $this->post('/login', ['username' => 'siti', 'password' => 'Rahasia12345'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $this->buatUser(['username' => 'off', 'password' => 'Rahasia12345', 'aktif' => false]);

        $this->post('/login', ['username' => 'off', 'password' => 'Rahasia12345'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_header_keamanan_terpasang(): void
    {
        $res = $this->get('/login');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("default-src 'self'", $res->headers->get('Content-Security-Policy'));
    }

    public function test_impor_csv_idempoten(): void
    {
        $csv = "nis;nama\n5001;Ani\n5002;Budi\n";
        $impor = app(\App\Services\ImporService::class);
        $baris = \App\Support\CsvParser::parse($csv)['baris'];

        $a = $impor->terapkan('siswa', $baris);
        $b = $impor->terapkan('siswa', $baris);

        $this->assertSame(2, $a['baru']);
        $this->assertSame(0, $b['baru']);
        $this->assertSame(2, $b['sama']);
        $this->assertSame(2, Siswa::count());
    }

    public function test_impor_kelas_menautkan_jurusan_wali_dan_tahun_aktif(): void
    {
        $ta = \App\Models\TahunAjaran::create(['nama' => '2026/2027', 'aktif' => true]);
        \App\Models\Jurusan::create(['kode' => 'DKV', 'nama' => 'Desain Komunikasi Visual']);
        $wali = \App\Models\Pegawai::create(['nip' => 'g1', 'nama' => 'Guru Satu']);

        $csv = "nama;tingkat;jurusan;tahun_ajaran;wali\nXI DKV 1;XI;DKV;;g1\nX ??;IX;XXX;;nope\n";
        $impor = app(\App\Services\ImporService::class);
        $baris = \App\Support\CsvParser::parse($csv)['baris'];

        $r = $impor->terapkan('kelas', $baris);
        $this->assertSame(1, $r['baru']);
        $this->assertSame(1, $r['galat']);
        $k = \App\Models\Kelas::firstOrFail();
        $this->assertSame($ta->id, $k->tahun_ajaran_id);
        $this->assertSame($wali->id, $k->wali_id);
        $this->assertSame('DKV', $k->jurusan->kode);

        $this->assertSame(0, $impor->terapkan('kelas', $baris)['baru']); // idempoten
        $this->assertSame(1, \App\Models\Kelas::count());
    }

    public function test_impor_akun_dari_csv_membuat_akun_dan_peran(): void
    {
        [$app] = $this->buatAplikasi('absensi', ['siswa'], ['peran_tersedia' => ['guru', 'wali_kelas', 'admin']]);
        \App\Models\Pegawai::create(['nip' => 'budi', 'nama' => 'Budi']);
        $berkas = tempnam(sys_get_temp_dir(), 'akun');
        file_put_contents($berkas, "username;nama;email;nip;peran\nbudi;Budi;;budi;guru|wali_kelas\n");

        $this->artisan('pusat:impor-akun', ['berkas' => $berkas])->assertSuccessful(); // simulasi
        $this->assertNull(User::where('username', 'budi')->first());

        $this->artisan('pusat:impor-akun', ['berkas' => $berkas, '--terapkan' => true])->assertSuccessful();
        $u = User::where('username', 'budi')->firstOrFail();
        $this->assertNotNull($u->pegawai_id);
        $this->assertEqualsCanonicalizing(['guru', 'wali_kelas'], $u->peranUntuk($app));
        array_map('unlink', glob(storage_path('app/private/akun-baru-*.csv')) ?: []);
        unlink($berkas);
    }
}
