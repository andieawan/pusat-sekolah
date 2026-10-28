<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Pegawai;
use App\Models\Pengampu;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Pengampu mapel: guru – mapel – kelas. Setiap baris otomatis dikabarkan ke aplikasi berizin "pengampu". */
final class PengampuService
{
    /** @return array{baru:int, sama:int} */
    public static function tambah(int $pegawaiId, int $mapelId, array $kelasIds): array
    {
        $hasil = ['baru' => 0, 'sama' => 0];
        DB::transaction(function () use ($pegawaiId, $mapelId, $kelasIds, &$hasil) {
            foreach (array_unique(array_map('intval', $kelasIds)) as $kelasId) {
                $ada = Pengampu::withTrashed()->where(['pegawai_id' => $pegawaiId, 'mapel_id' => $mapelId, 'kelas_id' => $kelasId])->first();
                if ($ada && ! $ada->trashed()) {
                    $hasil['sama']++;
                } elseif ($ada) {
                    $ada->restore();
                    $hasil['baru']++;
                } else {
                    Pengampu::create(['pegawai_id' => $pegawaiId, 'mapel_id' => $mapelId, 'kelas_id' => $kelasId]);
                    $hasil['baru']++;
                }
            }
        });

        return $hasil;
    }

    /**
     * Impor CSV: kolom guru (NIP / username / nama), mapel (kode / nama), kelas (nama; boleh banyak dipisah koma).
     * Kelas dicari di tahun ajaran aktif.
     *
     * @param  array<int,array<string,string>>  $baris  hasil CsvParser
     * @return array{baru:int, sama:int, galat:array<int,string>}
     */
    public static function impor(array $baris): array
    {
        $ta = TahunAjaran::aktif();
        if (! $ta) {
            return ['baru' => 0, 'sama' => 0, 'galat' => ['Belum ada tahun ajaran aktif.']];
        }
        $kelas = Kelas::where('tahun_ajaran_id', $ta->id)->get()->keyBy(fn ($k) => self::norm($k->nama));
        $mapelKode = Mapel::all()->keyBy(fn ($m) => mb_strtoupper($m->kode));
        $mapelNama = Mapel::all()->groupBy(fn ($m) => self::norm($m->nama));
        $hasil = ['baru' => 0, 'sama' => 0, 'galat' => []];

        foreach ($baris as $b) {
            $no = $b['_baris'] ?? '?';
            $g = self::ambil($b, ['guru', 'nip', 'username', 'nama_guru', 'guru_(nip/username/nama)']);
            $mp = self::ambil($b, ['mapel', 'kode_mapel', 'mata_pelajaran']);
            $kl = self::ambil($b, ['kelas', 'nama_kelas']);
            if ($g === '' || $mp === '' || $kl === '') {
                $hasil['galat'][] = "Baris $no: guru, mapel, dan kelas wajib diisi.";

                continue;
            }
            $pegawai = self::cariGuru($g);
            if (! $pegawai) {
                $hasil['galat'][] = "Baris $no: guru \"$g\" tidak ditemukan di Guru & Staf (cocokkan NIP, username, atau nama).";

                continue;
            }
            $mapel = $mapelKode->get(mb_strtoupper($mp)) ?? (($c = $mapelNama->get(self::norm($mp))) && $c->count() === 1 ? $c->first() : null);
            if (! $mapel) {
                $hasil['galat'][] = "Baris $no: mapel \"$mp\" tidak ditemukan (pakai kode atau nama di Mata Pelajaran).";

                continue;
            }
            $ids = [];
            foreach (preg_split('/\s*[,|]\s*/', $kl, -1, PREG_SPLIT_NO_EMPTY) as $nama) {
                $k = $kelas->get(self::norm($nama));
                $k ? $ids[] = $k->id : $hasil['galat'][] = "Baris $no: kelas \"$nama\" tidak ada di tahun ajaran {$ta->nama}.";
            }
            if ($ids) {
                $r = self::tambah($pegawai->id, $mapel->id, $ids);
                $hasil['baru'] += $r['baru'];
                $hasil['sama'] += $r['sama'];
            }
        }

        return $hasil;
    }

    public static function cariGuru(string $v): ?Pegawai
    {
        if ($p = Pegawai::whereRaw('LOWER(nip) = ?', [mb_strtolower($v)])->first()) {
            return $p;
        }
        if ($u = User::whereRaw('LOWER(username) = ?', [mb_strtolower($v)])->whereNotNull('pegawai_id')->first()) {
            return Pegawai::find($u->pegawai_id);
        }
        $nama = Pegawai::whereRaw('LOWER(nama) = ?', [mb_strtolower(trim($v))])->get();

        return $nama->count() === 1 ? $nama->first() : null;
    }

    private static function ambil(array $b, array $kunci): string
    {
        foreach ($kunci as $k) {
            if (isset($b[$k]) && trim($b[$k]) !== '') {
                return trim($b[$k]);
            }
        }

        return '';
    }

    private static function norm(string $s): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
    }
}
