<?php

namespace Tests\Feature;

use App\Models\Sambungan;
use App\Models\WebhookOutbox;
use Illuminate\Support\Facades\Http;

class SambunganTest extends PakaiData
{
    private function duaAplikasi(): array
    {
        [$pkl, $sp] = $this->buatAplikasi('pkl', ['siswa'], ['webhook_url' => 'https://pkl.example.test/api/pusat/webhook', 'webhook_secret' => 'a']);
        [$bk, $sb] = $this->buatAplikasi('bk', ['siswa'], ['webhook_url' => 'https://bk.example.test/api/pusat/webhook', 'webhook_secret' => 'b']);

        return [$pkl, $sp, $bk, $sb];
    }

    public function test_halaman_hanya_untuk_superadmin_dan_tampil(): void
    {
        $this->duaAplikasi();
        $this->actingAs($this->buatUser())->get('/admin/sambungan')->assertForbidden();
        $this->actingAs($this->buatUser(['superadmin' => true]))->get('/admin/sambungan')
            ->assertOk()->assertSee('PKL → BK')->assertSee('Sambungkan')->assertSee('belum terdaftar');
    }

    public function test_sambungkan_membuat_kunci_mengabari_dan_api_mengirim_kunci_ke_kedua_aplikasi(): void
    {
        Http::fake(['*' => Http::response('{"ok":true}', 200)]);
        [$pkl, $sp, $bk, $sb] = $this->duaAplikasi();
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->post('/admin/sambungan/pkl-bk/nyalakan')->assertRedirect()->assertSessionHas('sukses');
        $s = Sambungan::where('jenis', 'pkl-bk')->first();
        $this->assertTrue($s->aktif);
        $this->assertGreaterThanOrEqual(40, strlen($s->kunci));
        $o = WebhookOutbox::where('event', 'sambungan.diperbarui')->get();
        $this->assertCount(2, $o);
        $this->assertSame(['jenis' => 'pkl-bk'], $o[0]->payload['data']); // kunci tidak ikut di webhook
        $this->assertTrue($o->every(fn ($x) => $x->status === 'terkirim'));

        $a = collect($this->getJson('/api/v1/sambungan', $this->basic($pkl->client_id, $sp))->assertOk()->json('data'))->keyBy('jenis');
        $this->assertSame(['pkl-bk', 'pkl-tu', 'pkl-web'], $a->keys()->all());
        $this->assertSame(['jenis' => 'pkl-bk', 'aktif' => true, 'peran' => 'pemberi', 'mitra' => 'bk', 'url' => 'https://bk.example.test', 'kunci' => $s->kunci], $a['pkl-bk']);
        $this->assertFalse($a['pkl-tu']['aktif']);
        $b = collect($this->getJson('/api/v1/sambungan', $this->basic($bk->client_id, $sb))->json('data'))->keyBy('jenis');
        $this->assertSame('https://pkl.example.test', $b['pkl-bk']['url']);
        $this->assertSame($s->kunci, $b['pkl-bk']['kunci']);
        $this->assertFalse($b['absensi-bk']['aktif']); // absensi belum terdaftar

        $lama = $s->kunci;
        $this->actingAs($admin)->post('/admin/sambungan/pkl-bk/ganti-kunci')->assertRedirect();
        $this->assertNotSame($lama, $s->fresh()->kunci);

        $this->actingAs($admin)->post('/admin/sambungan/pkl-bk/matikan')->assertRedirect();
        $a = collect($this->getJson('/api/v1/sambungan', $this->basic($pkl->client_id, $sp))->json('data'))->keyBy('jenis');
        $this->assertFalse($a['pkl-bk']['aktif']);
        $this->assertSame('', $a['pkl-bk']['kunci']);
        $this->assertSame(6, WebhookOutbox::where('event', 'sambungan.diperbarui')->count());
    }

    public function test_aplikasi_mati_tetap_disimpan_dan_diberi_tahu(): void
    {
        Http::fake(['*' => Http::response('mati', 502)]);
        $this->duaAplikasi();
        $this->actingAs($this->buatUser(['superadmin' => true]))->post('/admin/sambungan/pkl-bk/nyalakan')->assertSessionHas('galat');
        $this->assertTrue(Sambungan::where('jenis', 'pkl-bk')->value('aktif'));
    }

    public function test_tanpa_aplikasi_terdaftar_ditolak_dan_jenis_asing_404(): void
    {
        $admin = $this->buatUser(['superadmin' => true]);
        $this->actingAs($admin)->post('/admin/sambungan/pkl-bk/nyalakan')->assertSessionHas('galat');
        $this->assertSame(0, Sambungan::count());
        $this->actingAs($admin)->post('/admin/sambungan/asal/nyalakan')->assertNotFound();
        $this->getJson('/api/v1/sambungan')->assertStatus(401);
    }
}
