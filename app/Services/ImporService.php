<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\BarisImpor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Impor siswa/pegawai dari CSV. Selalu dua tahap: rencana() (simulasi, tidak menulis apa pun)
 * lalu terapkan(). Baris yang bermasalah dilewati, sisanya tetap diproses. Idempoten: mengimpor
 * file yang sama dua kali tidak membuat data ganda (kunci: NIS untuk siswa, NIP untuk pegawai).
 */
class ImporService
{
    public const SUMBER = ['siswa', 'pegawai', 'kelas'];

    public const KOLOM = [
        'siswa' => ['nis', 'nisn', 'nama', 'jenis_kelamin', 'kelas', 'status', 'tempat_lahir', 'tanggal_lahir', 'no_hp', 'no_hp_ortu', 'nama_ortu'],
        'kelas' => ['nama', 'tingkat', 'jurusan', 'tahun_ajaran', 'wali'],
        'pegawai' => ['nip', 'nama', 'jenis', 'jabatan', 'no_hp', 'email'],
    ];

    /**
     * @param  array<int,array<string,string>>  $baris
     * @return array{item: array<int,array>, galat: array<int,array{baris:string,pesan:string}>, baru:int, ubah:int, sama:int}
     */
    public function rencana(string $sumber, array $baris): array
    {
        if (! in_array($sumber, self::SUMBER, true)) {
            throw new InvalidArgumentException('Sumber impor tidak dikenal.');
        }

        return match ($sumber) {
            'siswa' => $this->rencanaSiswa($baris),
            'kelas' => $this->rencanaKelas($baris),
            default => $this->rencanaPegawai($baris),
        };
    }

    /** Terapkan rencana. @return array{baru:int, ubah:int, sama:int, galat:int} */
    public function terapkan(string $sumber, array $baris): array
    {
        $r = $this->rencana($sumber, $baris);
        $model = ['siswa' => Siswa::class, 'kelas' => Kelas::class][$sumber] ?? Pegawai::class;
        $kunci = $sumber === 'siswa' ? 'nis' : 'nip';

        DB::transaction(function () use ($r, $model, $kunci, $sumber) {
            foreach ($r['item'] as $it) {
                if ($it['aksi'] === 'sama') {
                    continue;
                }
                $rec = $sumber === 'kelas'
                    ? $model::withTrashed()->where('nama', $it['data']['nama'])->where('tahun_ajaran_id', $it['data']['tahun_ajaran_id'])->first()
                    : $model::withTrashed()->where($kunci, $it['data'][$kunci])->first();
                if ($rec) {
                    if ($rec->trashed()) {
                        $rec->restore();
                    }
                    $rec->update($it['data']);
                } else {
                    $model::create($it['data']);
                }
            }
        });

        // Satu baris bisa punya beberapa galat; yang dilaporkan adalah jumlah BARIS yang dilewati.
        $barisGalat = count(array_unique(array_column($r['galat'], 'baris')));
        Audit::catat('impor.'.$sumber, null, [
            'baru' => $r['baru'], 'ubah' => $r['ubah'], 'sama' => $r['sama'], 'galat' => $barisGalat,
        ]);

        return ['baru' => $r['baru'], 'ubah' => $r['ubah'], 'sama' => $r['sama'], 'galat' => $barisGalat];
    }

    private function rencanaSiswa(array $baris): array
    {
        $tahun = TahunAjaran::aktif();
        $petaKelas = $tahun
            ? Kelas::where('tahun_ajaran_id', $tahun->id)->get()->keyBy(fn ($k) => mb_strtolower($k->nama))
            : collect();
        $ada = Siswa::withTrashed()->get()->keyBy('nis');

        $hasil = ['item' => [], 'galat' => [], 'baru' => 0, 'ubah' => 0, 'sama' => 0];
        $terlihat = [];

        foreach ($baris as $b) {
            $no = $b['_baris'] ?? '?';
            $p = BarisImpor::siswa($b);
            $galat = $p['galat'];
            $data = $p['data'];

            if ($p['kelas'] !== null) {
                if (! $tahun) {
                    $galat[] = 'Belum ada tahun ajaran aktif untuk mencocokkan kelas';
                } elseif ($k = $petaKelas->get(mb_strtolower($p['kelas']))) {
                    $data['kelas_id'] = $k->id;
                } else {
                    $galat[] = "Kelas \"{$p['kelas']}\" tidak ditemukan di tahun ajaran {$tahun->nama}";
                }
            }
            if ($data['nis'] !== '' && isset($terlihat[$data['nis']])) {
                $galat[] = "NIS ganda dalam file (sudah ada di baris {$terlihat[$data['nis']]})";
            }
            if ($galat) {
                foreach ($galat as $g) {
                    $hasil['galat'][] = ['baris' => $no, 'pesan' => $g];
                }

                continue;
            }
            $terlihat[$data['nis']] = $no;

            $lama = $ada->get($data['nis']);
            $aksi = 'baru';
            if ($lama) {
                $aksi = (! $lama->trashed() && $this->sama($lama, $data)) ? 'sama' : 'ubah';
            }
            $hasil[$aksi]++;
            $hasil['item'][] = ['aksi' => $aksi, 'baris' => $no, 'data' => $data];
        }

        return $hasil;
    }

    private function rencanaPegawai(array $baris): array
    {
        $ada = Pegawai::withTrashed()->get()->keyBy('nip');
        $hasil = ['item' => [], 'galat' => [], 'baru' => 0, 'ubah' => 0, 'sama' => 0];
        $terlihat = [];

        foreach ($baris as $b) {
            $no = $b['_baris'] ?? '?';
            $p = BarisImpor::pegawai($b);
            $galat = $p['galat'];
            $data = $p['data'];
            if ($data['nip'] !== '' && isset($terlihat[$data['nip']])) {
                $galat[] = "NIP ganda dalam file (sudah ada di baris {$terlihat[$data['nip']]})";
            }
            if ($galat) {
                foreach ($galat as $g) {
                    $hasil['galat'][] = ['baris' => $no, 'pesan' => $g];
                }

                continue;
            }
            $terlihat[$data['nip']] = $no;

            $lama = $ada->get($data['nip']);
            $aksi = 'baru';
            if ($lama) {
                $aksi = (! $lama->trashed() && $this->sama($lama, $data)) ? 'sama' : 'ubah';
            }
            $hasil[$aksi]++;
            $hasil['item'][] = ['aksi' => $aksi, 'baris' => $no, 'data' => $data];
        }

        return $hasil;
    }

    private function rencanaKelas(array $baris): array
    {
        $aktif = TahunAjaran::aktif();
        $tahunPeta = TahunAjaran::all()->keyBy(fn ($t) => mb_strtolower($t->nama));
        $jurusanPeta = \App\Models\Jurusan::all()->keyBy(fn ($j) => mb_strtolower($j->kode));
        $waliPeta = Pegawai::all()->keyBy('nip');
        $ada = Kelas::withTrashed()->get()->keyBy(fn ($k) => mb_strtolower($k->nama).'|'.$k->tahun_ajaran_id);

        $hasil = ['item' => [], 'galat' => [], 'baru' => 0, 'ubah' => 0, 'sama' => 0];
        $terlihat = [];

        foreach ($baris as $b) {
            $no = $b['_baris'] ?? '?';
            $p = BarisImpor::kelas($b);
            $galat = $p['galat'];
            $data = $p['data'];

            $tahun = $p['tahun'] === null ? $aktif : $tahunPeta->get(mb_strtolower($p['tahun']));
            if (! $tahun) {
                $galat[] = $p['tahun'] === null ? 'Belum ada tahun ajaran aktif' : "Tahun ajaran \"{$p['tahun']}\" tidak ditemukan";
            } else {
                $data['tahun_ajaran_id'] = $tahun->id;
            }
            $data['jurusan_id'] = null;
            if ($p['jurusan'] !== null) {
                if ($j = $jurusanPeta->get(mb_strtolower($p['jurusan']))) {
                    $data['jurusan_id'] = $j->id;
                } else {
                    $galat[] = "Kode jurusan \"{$p['jurusan']}\" tidak ditemukan";
                }
            }
            $data['wali_id'] = null;
            if ($p['wali'] !== null) {
                if ($w = $waliPeta->get($p['wali'])) {
                    $data['wali_id'] = $w->id;
                } else {
                    $galat[] = "Wali dengan NIP/ID \"{$p['wali']}\" tidak ditemukan (impor guru dulu)";
                }
            }

            $kunci = mb_strtolower($data['nama']).'|'.($data['tahun_ajaran_id'] ?? '');
            if (isset($terlihat[$kunci])) {
                $galat[] = "Kelas ganda dalam file (sudah ada di baris {$terlihat[$kunci]})";
            }
            if ($galat) {
                foreach ($galat as $g) {
                    $hasil['galat'][] = ['baris' => $no, 'pesan' => $g];
                }

                continue;
            }
            $terlihat[$kunci] = $no;

            $lama = $ada->get($kunci);
            $aksi = 'baru';
            if ($lama) {
                $aksi = (! $lama->trashed() && $this->sama($lama, $data)) ? 'sama' : 'ubah';
            }
            $hasil[$aksi]++;
            $hasil['item'][] = ['aksi' => $aksi, 'baris' => $no, 'data' => $data];
        }

        return $hasil;
    }

    /** Apakah semua kolom yang akan ditulis sudah bernilai sama di database. */
    private function sama($model, array $data): bool
    {
        foreach ($data as $kolom => $nilai) {
            $sekarang = $model->{$kolom};
            if ($sekarang instanceof \DateTimeInterface) {
                $sekarang = $sekarang->format('Y-m-d');
            }
            if ((string) $sekarang !== (string) $nilai) {
                return false;
            }
        }

        return true;
    }
}
