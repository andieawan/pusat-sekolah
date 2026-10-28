<?php

namespace Tests\Feature;

use App\Models\WebhookOutbox;

class AkunSandiTest extends PakaiData
{
    public function test_hash_password_hanya_untuk_aplikasi_berizin_dan_tidak_selama_wajib_ganti(): void
    {
        [$abs, $sec] = $this->buatAplikasi('absensi', ['akun', 'akun_sandi'], ['webhook_url' => 'https://a.test/api/pusat/webhook', 'webhook_secret' => 'x']);
        [$bk, $sbk] = $this->buatAplikasi('bk', ['akun'], ['webhook_url' => 'https://b.test/api/pusat/webhook', 'webhook_secret' => 'y']);
        $u = $this->buatUser(['username' => 'g008'], $abs, ['guru']);
        $u->forceFill(['wajib_ganti_password' => true])->saveQuietly();
        app(\App\Services\AksesService::class)->sinkron($u, [$abs->id => ['guru'], $bk->id => ['guru']]);

        $this->getJson('/api/v1/akun', $this->basic($abs->client_id, $sec))->assertOk()->assertJsonPath('data.0.password_hash', null);
        $this->getJson('/api/v1/akun', $this->basic($bk->client_id, $sbk))->assertOk()->assertJsonMissingPath('data.0.password_hash');

        WebhookOutbox::query()->delete();
        $u->forceFill(['password' => 'PasswordBaru123', 'wajib_ganti_password' => false])->save();
        $hash = $this->getJson('/api/v1/akun', $this->basic($abs->client_id, $sec))->json('data.0.password_hash');
        $this->assertTrue(password_verify('PasswordBaru123', $hash));

        $kirim = WebhookOutbox::where('event', 'akun.diperbarui')->get();
        $this->assertSame($hash, $kirim->firstWhere('aplikasi_id', $abs->id)->payload['data']['password_hash']);
        $this->assertArrayNotHasKey('password_hash', $kirim->firstWhere('aplikasi_id', $bk->id)->payload['data']);
    }
}
