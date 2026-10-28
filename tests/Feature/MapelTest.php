<?php

namespace Tests\Feature;

use App\Models\Mapel;
use App\Models\WebhookOutbox;
use App\Services\ImporService;
use App\Support\CsvParser;

class MapelTest extends PakaiData
{
    public function test_mapel_dikelola_di_pusat_dan_dikirim_ke_aplikasi_berizin(): void
    {
        [$abs, $sec] = $this->buatAplikasi('absensi', ['mapel'], ['webhook_url' => 'https://absensi.example.test/api/pusat/webhook', 'webhook_secret' => 'x']);
        [$lain, $sl] = $this->buatAplikasi('web', ['jurusan']);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->get('/admin/data/mapel')->assertOk()->assertSee('Mata Pelajaran');
        $this->actingAs($admin)->post('/admin/data/mapel', ['kode' => 'mtk', 'nama' => 'Matematika', 'kelompok' => 'umum', 'aktif' => 1])->assertRedirect();
        $m = Mapel::firstOrFail();
        $this->assertSame('MTK', $m->kode);
        $this->assertSame(1, WebhookOutbox::where('event', 'mapel.dibuat')->where('aplikasi_id', $abs->id)->count());
        $this->assertSame(0, WebhookOutbox::where('aplikasi_id', $lain->id)->count());

        $this->getJson('/api/v1/master/mapel', $this->basic($abs->client_id, $sec))->assertOk()
            ->assertJsonPath('data.0.kode', 'MTK')->assertJsonPath('data.0.kelompok', 'umum');
        $this->getJson('/api/v1/master/mapel', $this->basic($lain->client_id, $sl))->assertForbidden();
    }

    public function test_impor_csv_mapel(): void
    {
        $impor = app(ImporService::class);
        $r = $impor->terapkan('mapel', CsvParser::parse("kode;nama;kelompok\nBIND;Bahasa Indonesia;umum\nDKV-DG;Desain Grafis;produktif\nX;;umum\n")['baris']);
        $this->assertSame(2, $r['baru']);
        $this->assertSame(1, $r['galat']);
        $this->assertSame('kejuruan', Mapel::where('kode', 'DKV-DG')->value('kelompok'));
        $r = $impor->terapkan('mapel', CsvParser::parse("kode;nama\nBIND;Bahasa Indonesia\n")['baris']);
        $this->assertSame(1, $r['sama']);
    }
}
