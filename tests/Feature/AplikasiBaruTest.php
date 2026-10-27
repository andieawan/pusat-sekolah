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
}
