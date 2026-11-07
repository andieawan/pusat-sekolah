<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AplikasiBaruTest extends TestCase
{
    use RefreshDatabase;

    public function test_alamat_halaman_login_ditolak(): void
    {
        $this->artisan('pusat:aplikasi-baru', ['kode' => 'pkl', '--base-url' => 'https://pkl.example.test/login'])->assertFailed();
        $this->assertFalse(Aplikasi::where('kode', 'pkl')->exists());
    }

    public function test_templat_pkl_memakai_webhook_api(): void
    {
        $this->artisan('pusat:aplikasi-baru', ['kode' => 'pkl', '--base-url' => 'https://pkl.example.test/'])->assertSuccessful();
        $a = Aplikasi::where('kode', 'pkl')->firstOrFail();
        $this->assertSame('https://pkl.example.test/api/pusat/webhook', $a->webhook_url);
        $this->assertSame(['https://pkl.example.test/sso/callback'], $a->redirect_uris);
        $this->assertContains('siswa', $a->scope);
    }

    public function test_templat_web_memakai_jalur_pusat(): void
    {
        $this->artisan('pusat:aplikasi-baru', ['kode' => 'web', '--base-url' => 'https://web.example.test'])->assertSuccessful();
        $a = Aplikasi::where('kode', 'web')->firstOrFail();
        $this->assertSame(['https://web.example.test/pusat/callback'], $a->redirect_uris);
        $this->assertSame('https://web.example.test/pusat/webhook', $a->webhook_url);
        $this->assertSame('https://web.example.test/pusat/masuk', $a->urlMasuk());
    }

    public function test_templat_tanggungan_hanya_data_siswa_dasar(): void
    {
        $this->artisan('pusat:aplikasi-baru', ['kode' => 'tanggungan', '--base-url' => 'https://tanggungan.example.test'])->assertSuccessful();
        $a = Aplikasi::where('kode', 'tanggungan')->firstOrFail();
        $this->assertSame('https://tanggungan.example.test/api/pusat/webhook', $a->webhook_url);
        $this->assertContains('siswa_dasar', $a->scope);
        $this->assertNotContains('siswa', $a->scope);
        $this->assertSame(['admin', 'bendahara', 'kepsek', 'wali_kelas'], $a->peran_tersedia);
    }
}
