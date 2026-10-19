<?php

namespace Tests\Feature;

use App\Models\WebhookOutbox;
use App\Services\WebhookPengirim;
use App\Support\Tanda;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class WebhookTest extends PakaiData
{
    private function appDenganWebhook(): array
    {
        return $this->buatAplikasi('absensi', ['siswa', 'akun'], [
            'webhook_url' => 'https://absensi.example.test/hooks/pusat',
            'webhook_secret' => 'rahasia-webhook',
        ]);
    }

    public function test_perubahan_siswa_masuk_antrean_hanya_untuk_aplikasi_berhak(): void
    {
        [$app] = $this->appDenganWebhook();
        $this->buatAplikasi('pkl', ['jurusan'], ['webhook_url' => 'https://pkl.example.test/h', 'webhook_secret' => 'x']);

        $this->buatKelasDanSiswa('4001');

        $o = WebhookOutbox::where('event', 'siswa.dibuat')->get();
        $this->assertCount(1, $o);
        $this->assertSame($app->id, $o[0]->aplikasi_id);
        $this->assertSame('pending', $o[0]->status);
    }

    public function test_superadmin_tanpa_akses_eksplisit_tetap_dikabari_dan_hapus_akun_memberi_tahu_aplikasi(): void
    {
        [$app] = $this->appDenganWebhook();
        $u = $this->buatUser(['superadmin' => true]);

        $u->update(['superadmin' => false]); // peran admin implisit hilang -> peran kosong
        $o = WebhookOutbox::where('event', 'akun.diperbarui')->latest('id')->first();
        $this->assertNotNull($o);
        $this->assertSame([], $o->payload['data']['peran']);

        $u2 = $this->buatUser([], $app, ['guru']);
        WebhookOutbox::query()->delete();
        $this->actingAs($this->buatUser(['superadmin' => true]))->delete(route('admin.akun.destroy', $u2));
        $o = WebhookOutbox::where('event', 'akun.diperbarui')->first();
        $this->assertNotNull($o);
        $this->assertFalse($o->payload['data']['aktif']);
    }

    public function test_pengiriman_menyertakan_tanda_tangan_yang_valid(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [$app] = $this->appDenganWebhook();
        $this->buatKelasDanSiswa('4002');

        $hasil = app(WebhookPengirim::class)->kirimJatuhTempo();
        $this->assertSame(1, $hasil['dikirim']);

        Http::assertSent(function (Request $r) {
            $waktu = (int) $r->header('X-Pusat-Timestamp')[0];
            return Tanda::verifikasi('rahasia-webhook', $waktu, $r->body(), $r->header('X-Pusat-Signature')[0])
                && $r->header('X-Pusat-Event')[0] === 'siswa.dibuat';
        });
        $this->assertSame('terkirim', WebhookOutbox::first()->status);
    }

    public function test_kegagalan_dijadwalkan_ulang_lalu_menyerah(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);
        $this->appDenganWebhook();
        $this->buatKelasDanSiswa('4003');
        $o = WebhookOutbox::firstOrFail();

        $pengirim = app(WebhookPengirim::class);
        $pengirim->kirim($o->fresh());
        $o->refresh();
        $this->assertSame('pending', $o->status);
        $this->assertSame(1, $o->percobaan);
        $this->assertNotNull($o->jadwal_berikutnya);

        for ($i = 0; $i < 5; $i++) {
            $pengirim->kirim($o->fresh());
        }
        $o->refresh();
        $this->assertSame('gagal', $o->status);
        $this->assertSame(6, $o->percobaan);
    }

    public function test_entri_belum_jatuh_tempo_tidak_dikirim(): void
    {
        Http::fake();
        $this->appDenganWebhook();
        $this->buatKelasDanSiswa('4004');
        WebhookOutbox::query()->update(['jadwal_berikutnya' => now()->addHour()]);

        $this->assertSame(0, app(WebhookPengirim::class)->kirimJatuhTempo()['dikirim']);
        Http::assertNothingSent();
    }

    public function test_tanda_tangan_lama_atau_salah_ditolak(): void
    {
        $badan = '{"a":1}';
        $t = Tanda::buat('r', 1000, $badan);
        $this->assertTrue(Tanda::verifikasi('r', 1000, $badan, $t, 300, 1100));
        $this->assertFalse(Tanda::verifikasi('r', 1000, $badan, $t, 300, 2000));
        $this->assertFalse(Tanda::verifikasi('lain', 1000, $badan, $t, 300, 1100));
        $this->assertFalse(Tanda::verifikasi('r', 1000, $badan.' ', $t, 300, 1100));
    }
}
