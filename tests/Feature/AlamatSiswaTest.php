<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Services\ImporService;
use App\Support\CsvParser;

class AlamatSiswaTest extends PakaiData
{
    public function test_alamat_lewat_impor_dan_csv_tanpa_kolom_alamat_tidak_mengosongkan(): void
    {
        $impor = app(ImporService::class);
        $impor->terapkan('siswa', CsvParser::parse("nis;nama;alamat;nama_ortu\n5001;Ani;\"Dusun Krajan RT 01, Pakusari\";Pak Ali\n")['baris']);
        $this->assertSame('Dusun Krajan RT 01, Pakusari', Siswa::where('nis', '5001')->value('alamat'));

        $r = $impor->terapkan('siswa', CsvParser::parse("nis;nama\n5001;Ani Lestari\n")['baris']);
        $this->assertSame(1, $r['ubah']);
        $s = Siswa::where('nis', '5001')->first();
        $this->assertSame('Ani Lestari', $s->nama);
        $this->assertSame('Dusun Krajan RT 01, Pakusari', $s->alamat);
        $this->assertSame('Pak Ali', $s->nama_ortu);
    }

    public function test_alamat_hanya_untuk_aplikasi_berizin_lengkap(): void
    {
        $s = $this->buatKelasDanSiswa('6001');
        $s->update(['alamat' => 'Jl. Mawar 5']);
        [$lengkap, $sl] = $this->buatAplikasi('bk', ['siswa']);
        [$dasar, $sd] = $this->buatAplikasi('pkl', ['siswa_dasar']);
        $this->getJson('/api/v1/master/siswa', $this->basic($lengkap->client_id, $sl))->assertOk()->assertJsonPath('data.0.alamat', 'Jl. Mawar 5');
        $this->assertArrayNotHasKey('alamat', $this->getJson('/api/v1/master/siswa', $this->basic($dasar->client_id, $sd))->json('data.0'));
    }

    public function test_form_admin_memuat_alamat(): void
    {
        $s = $this->buatKelasDanSiswa('7001');
        $this->actingAs($this->buatUser(['superadmin' => true]))->get(route('admin.master.edit', ['siswa', $s->id]))->assertOk()->assertSee('Alamat rumah');
    }
}
