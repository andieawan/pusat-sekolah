<?php

namespace Tests\Feature;

use App\Models\Akses;

/** Menu "Aplikasi lain": daftar dari userinfo SSO, dan /buka/{kode} langsung ke awal SSO aplikasi. */
class BukaAplikasiTest extends PakaiData
{
    public function test_buka_langsung_ke_sso_aplikasi_yang_berhak(): void
    {
        [$abs] = $this->buatAplikasi('absensi');
        [$web] = $this->buatAplikasi('web', ['akun'], ['redirect_uris' => ['https://web.example.test/pusat/callback']]);
        [$bk] = $this->buatAplikasi('bk', ['akun'], ['redirect_uris' => ['https://bk.example.test/sso/callback']]);
        $u = $this->buatUser([], $abs, ['guru']);
        Akses::create(['user_id' => $u->id, 'aplikasi_id' => $web->id, 'peran' => 'admin']);

        $this->actingAs($u)->get('/buka/absensi')->assertRedirect('https://absensi.example.test/sso/login');
        $this->get('/buka/web')->assertRedirect('https://web.example.test/pusat/masuk');
        $this->get('/buka/bk')->assertNotFound(); // tidak punya akses BK
        $this->get('/portal')->assertOk()->assertSee(route('buka', 'web'));

        $info = app(\App\Services\SsoService::class)->userinfo($u, $abs);
        $this->assertSame([['kode' => 'web', 'nama' => 'Web', 'url' => route('buka', 'web')]], $info['aplikasi_lain']);
    }
}
