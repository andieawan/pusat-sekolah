<?php

namespace Tests\Feature;

use App\Models\Akses;
use App\Models\Sambungan;
use Illuminate\Support\Facades\Http;

class AlihkanAbsensiTest extends PakaiData
{
    public function test_akses_disalin_dan_sambungan_bk_pindah_ke_jurnal(): void
    {
        Http::fake(['*' => Http::response('{"ok":true}', 200)]);
        [$abs] = $this->buatAplikasi('absensi', ['akun'], ['base_url' => 'https://absensi.test', 'webhook_url' => 'https://absensi.test/api/pusat/webhook', 'webhook_secret' => 'a']);
        [$jur, $sj] = $this->buatAplikasi('jurnal', ['akun'], ['base_url' => 'https://jurnal.test', 'webhook_url' => 'https://jurnal.test/api/pusat/webhook', 'webhook_secret' => 'j']);
        [$bk, $sb] = $this->buatAplikasi('bk', ['akun'], ['base_url' => 'https://bk.test', 'webhook_url' => 'https://bk.test/api/pusat/webhook', 'webhook_secret' => 'b']);
        foreach ([$abs, $jur] as $a) {
            $a->update(['peran_tersedia' => ['guru', 'admin', 'bk']]);
        }
        $guru = $this->buatUser([], $abs, ['guru']);
        Akses::create(['user_id' => $guru->id, 'aplikasi_id' => $bk->id, 'peran' => 'bk']);
        $s = new Sambungan(['jenis' => 'absensi-bk', 'aktif' => true]);
        $s->buatKunci();
        $s->save();

        $bkLama = collect($this->getJson('/api/v1/sambungan', $this->basic($bk->client_id, $sb))->json('data'))->keyBy('jenis');
        $this->assertSame('https://absensi.test', $bkLama['absensi-bk']['url']);

        $this->artisan('pusat:alihkan-absensi', ['--yakin' => true, '--nonaktifkan-lama' => true])->assertExitCode(0);

        $this->assertSame(['guru'], Akses::where('user_id', $guru->id)->where('aplikasi_id', $jur->id)->pluck('peran')->all());
        $this->assertSame(['bk'], Akses::where('user_id', $guru->id)->where('aplikasi_id', $bk->id)->pluck('peran')->all()); // akses lain tetap
        $this->assertFalse($abs->fresh()->aktif);
        $bkBaru = collect($this->getJson('/api/v1/sambungan', $this->basic($bk->client_id, $sb))->json('data'))->keyBy('jenis');
        $this->assertSame(['aktif' => true, 'mitra' => 'jurnal', 'url' => 'https://jurnal.test', 'kunci' => $s->kunci],
            array_intersect_key($bkBaru['absensi-bk'], array_flip(['aktif', 'mitra', 'url', 'kunci'])));
        $j = collect($this->getJson('/api/v1/sambungan', $this->basic($jur->client_id, $sj))->json('data'))->keyBy('jenis');
        $this->assertSame('pemberi', $j['absensi-bk']['peran']);
        $this->assertSame($s->kunci, $j['absensi-bk']['kunci']);

        $this->artisan('pusat:alihkan-absensi', ['--balik' => true])->assertExitCode(0);
        $this->assertTrue($abs->fresh()->aktif);
        $this->assertSame('absensi', Sambungan::penyediaAbsensi());
    }
}
