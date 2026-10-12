<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiapkanLokalTest extends TestCase
{
    use RefreshDatabase;

    public function test_mendaftarkan_absensi_dan_menulis_env(): void
    {
        $env = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($env, "APP_NAME=Absensi\r\nPUSAT_ENABLED=false\r\n");

        $this->artisan('pusat:siapkan-lokal', ['--absensi-url' => 'http://absensi-pusat.test', '--pusat-url' => 'http://pusat.test', '--env-absensi' => $env])
            ->assertSuccessful();

        $app = Aplikasi::where('kode', 'absensi')->firstOrFail();
        $this->assertSame(['http://absensi-pusat.test/sso/callback'], $app->redirect_uris);
        $this->assertSame('http://absensi-pusat.test/api/pusat/webhook', $app->webhook_url);

        $isi = file_get_contents($env);
        $this->assertStringContainsString("PUSAT_ENABLED=true\r\n", $isi);
        $this->assertStringContainsString('PUSAT_URL=http://pusat.test', $isi);
        $this->assertMatchesRegularExpression('/PUSAT_CLIENT_ID=app_[a-z0-9]{24}/', $isi);
        $this->assertStringContainsString('APP_NAME=Absensi', $isi);
        $this->assertFileExists($env.'.bak-pusat');
        @unlink($env);
        @unlink($env.'.bak-pusat');
    }

    public function test_diulang_tidak_mengubah_rahasia_kecuali_diputar(): void
    {
        $this->artisan('pusat:siapkan-lokal')->assertSuccessful();
        $hash = Aplikasi::where('kode', 'absensi')->value('client_secret_hash');

        $this->artisan('pusat:siapkan-lokal', ['--absensi-url' => 'http://127.0.0.1:9001'])->assertSuccessful();
        $app = Aplikasi::where('kode', 'absensi')->first();
        $this->assertSame($hash, $app->client_secret_hash);
        $this->assertSame('http://127.0.0.1:9001', $app->base_url);

        $this->artisan('pusat:siapkan-lokal', ['--putar' => true])->assertSuccessful();
        $this->assertNotSame($hash, Aplikasi::where('kode', 'absensi')->value('client_secret_hash'));
    }

    public function test_data_demo_dan_akun_guru_berhak_masuk_absensi(): void
    {
        $this->artisan('pusat:siapkan-lokal', ['--demo' => true])->assertSuccessful();
        $this->assertSame(10, Siswa::count());

        $app = Aplikasi::where('kode', 'absensi')->first();
        $this->assertTrue(User::where('username', 'guru.demo')->first()->bolehAkses($app));
    }

    public function test_ditolak_di_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('pusat:siapkan-lokal')->assertFailed();
        $this->assertSame(0, Aplikasi::count());
    }
}
