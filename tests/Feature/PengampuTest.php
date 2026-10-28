<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Pegawai;
use App\Models\Pengampu;
use App\Models\TahunAjaran;
use App\Models\WebhookOutbox;
use App\Services\PengampuService;
use App\Support\CsvParser;

class PengampuTest extends PakaiData
{
    private function siapkan(): array
    {
        $ta = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'Ganjil', 'aktif' => true]);
        $k1 = Kelas::create(['nama' => 'X DKV 1', 'tingkat' => 'X', 'tahun_ajaran_id' => $ta->id]);
        $k2 = Kelas::create(['nama' => 'X DKV 2', 'tingkat' => 'X', 'tahun_ajaran_id' => $ta->id]);
        $g = Pegawai::create(['nip' => '1987', 'nama' => 'Bu Ani', 'jenis' => 'guru', 'aktif' => true]);
        $m = Mapel::create(['kode' => 'MTK', 'nama' => 'Matematika', 'kelompok' => 'umum', 'aktif' => true]);

        return [$k1, $k2, $g, $m];
    }

    public function test_tambah_hapus_pengampu_dan_dikabarkan_ke_aplikasi_berizin(): void
    {
        [$k1, $k2, $g, $m] = $this->siapkan();
        [$abs, $sec] = $this->buatAplikasi('absensi', ['pengampu'], ['webhook_url' => 'https://absensi.example.test/api/pusat/webhook', 'webhook_secret' => 'x']);
        [$lain, $sl] = $this->buatAplikasi('web', ['jurusan']);
        $admin = $this->buatUser(['superadmin' => true]);

        $this->actingAs($admin)->get('/admin/pengampu')->assertOk()->assertSee('Pengampu mapel')->assertSee('X DKV 2');
        $this->actingAs($admin)->post('/admin/pengampu', ['pegawai_id' => $g->id, 'mapel_id' => $m->id, 'kelas' => [$k1->id, $k2->id]])
            ->assertSessionHas('sukses', fn ($s) => str_contains($s, '2 baru'));
        $this->assertSame(2, Pengampu::count());
        $this->assertSame(2, WebhookOutbox::where('event', 'pengampu.dibuat')->where('aplikasi_id', $abs->id)->count());
        $this->assertSame(0, WebhookOutbox::where('aplikasi_id', $lain->id)->count());

        $this->getJson('/api/v1/master/pengampu', $this->basic($abs->client_id, $sec))->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.pegawai_uuid', $g->uuid)->assertJsonPath('data.0.mapel_uuid', $m->uuid);
        $this->getJson('/api/v1/master/pengampu', $this->basic($lain->client_id, $sl))->assertForbidden();

        $p = Pengampu::where('kelas_id', $k2->id)->first();
        $this->actingAs($admin)->delete('/admin/pengampu/'.$p->id)->assertSessionHas('sukses');
        $this->assertSame(1, Pengampu::count());
        $this->assertSame(1, WebhookOutbox::where('event', 'pengampu.dihapus')->count());
        // tambah lagi → dipulihkan, bukan baris kembar
        $this->actingAs($admin)->post('/admin/pengampu', ['pegawai_id' => $g->id, 'mapel_id' => $m->id, 'kelas' => [$k1->id, $k2->id]])
            ->assertSessionHas('sukses', fn ($s) => str_contains($s, '1 baru') && str_contains($s, '1 sudah ada'));
        $this->assertSame(2, Pengampu::withTrashed()->count());

        // mapel/guru/kelas yang masih diampu tidak bisa dihapus
        $this->actingAs($admin)->delete('/admin/data/mapel/'.$m->id)->assertSessionHas('galat');
        $this->assertNotNull($m->fresh());
    }

    public function test_impor_csv_pengampu(): void
    {
        [$k1, $k2, $g, $m] = $this->siapkan();
        $r = PengampuService::impor(CsvParser::parse("guru;mapel;kelas\n1987;MTK;X DKV 1, X DKV 2\nbu ani;matematika;X DKV 1\nPak Tak Ada;MTK;X DKV 1\n1987;MTK;XII Hilang\n")['baris']);
        $this->assertSame(2, $r['baru']);
        $this->assertSame(1, $r['sama']);
        $this->assertCount(2, $r['galat']);
    }
}
