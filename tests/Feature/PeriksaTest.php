<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriksaTest extends TestCase
{
    use RefreshDatabase;

    public function test_gagal_tanpa_superadmin(): void
    {
        $this->artisan('pusat:periksa')->expectsOutputToContain('Belum ada superadmin')->assertExitCode(1);
    }
}
