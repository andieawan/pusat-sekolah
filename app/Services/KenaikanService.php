<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kenaikan kelas akhir tahun: siswa aktif di kelas tahun ajaran asal dipindah ke kelas tahun ajaran tujuan
 * (naik satu tingkat), tinggal kelas (kelas setingkat), lulus (tingkat XII), atau keluar. Setiap perubahan siswa
 * dikabarkan ke semua aplikasi lewat webhook seperti perubahan biasa.
 */
final class KenaikanService
{
    public const TINGKAT = ['X' => 'XI', 'XI' => 'XII', 'XII' => null];

    public const LULUS = 'lulus';

    public static function tingkatBerikut(string $t): ?string
    {
        return self::TINGKAT[strtoupper(trim($t))] ?? null;
    }

    /** "X DKV 1" → "XI DKV 1" (awalan tingkat diganti); bila tidak berawalan tingkat: "<tingkat baru> <nama>". */
    public static function namaBaru(Kelas $k, string $tingkatBaru): string
    {
        $pola = '/^'.preg_quote(strtoupper(trim($k->tingkat)), '/').'(?=\s|$|-)/i';

        return preg_match($pola, $k->nama) ? preg_replace($pola, $tingkatBaru, $k->nama, 1) : $tingkatBaru.' '.$k->nama;
    }

    /**
     * Usulan per kelas asal: tujuan naik (id kelas yang ada, "baru:<nama>", atau "lulus") dan kelas untuk tinggal kelas.
     *
     * @return Collection<int,array{kelas:Kelas,siswa:Collection,naik:string,naikNama:string,tinggalNama:string}>
     */
    public static function rencana(TahunAjaran $dari, TahunAjaran $ke): Collection
    {
        $tujuan = Kelas::where('tahun_ajaran_id', $ke->id)->get();
        $cari = fn (string $nama) => $tujuan->first(fn ($t) => mb_strtolower(trim($t->nama)) === mb_strtolower(trim($nama)));

        return Kelas::with('jurusan')->where('tahun_ajaran_id', $dari->id)->orderByRaw("CASE tingkat WHEN 'XII' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")->orderBy('nama')->get()
            ->map(function (Kelas $k) use ($cari) {
                $siswa = Siswa::where('kelas_id', $k->id)->where('status', 'aktif')->orderBy('nama')->get();
                $berikut = self::tingkatBerikut($k->tingkat);
                if ($berikut === null) {
                    $naik = self::LULUS;
                    $naikNama = 'Lulus';
                } else {
                    $nama = self::namaBaru($k, $berikut);
                    $ada = $cari($nama);
                    $naik = $ada ? (string) $ada->id : 'baru:'.$nama;
                    $naikNama = $nama;
                }

                return ['kelas' => $k, 'siswa' => $siswa, 'naik' => $naik, 'naikNama' => $naikNama, 'tinggalNama' => $k->nama];
            })->filter(fn ($r) => $r['siswa']->isNotEmpty())->values();
    }

    /**
     * @param  array<int,string>  $tujuan  kelas_asal_id => id kelas tujuan | "baru:<nama>" | "lulus"
     * @param  array<int,string>  $pengecualian  siswa_id => "tinggal" | "keluar" | "lulus"
     * @return array{naik:int,tinggal:int,lulus:int,keluar:int,kelas_baru:string[]}
     */
    public static function terapkan(TahunAjaran $dari, TahunAjaran $ke, array $tujuan, array $pengecualian, bool $aktifkan): array
    {
        if ($dari->id === $ke->id) {
            throw new RuntimeException('Tahun ajaran asal dan tujuan tidak boleh sama.');
        }

        return DB::transaction(function () use ($dari, $ke, $tujuan, $pengecualian, $aktifkan) {
            $h = ['naik' => 0, 'tinggal' => 0, 'lulus' => 0, 'keluar' => 0, 'kelas_baru' => []];
            $kelasKe = Kelas::where('tahun_ajaran_id', $ke->id)->get();
            $pastikan = function (string $nama, string $tingkat, ?int $jurusanId) use ($ke, &$kelasKe, &$h): Kelas {
                $ada = $kelasKe->first(fn ($t) => mb_strtolower(trim($t->nama)) === mb_strtolower(trim($nama)));
                if ($ada) {
                    return $ada;
                }
                $baru = Kelas::create(['nama' => mb_substr(trim($nama), 0, 50), 'tingkat' => $tingkat, 'jurusan_id' => $jurusanId, 'tahun_ajaran_id' => $ke->id]);
                $kelasKe->push($baru);
                $h['kelas_baru'][] = $baru->nama;

                return $baru;
            };

            foreach (Kelas::where('tahun_ajaran_id', $dari->id)->get() as $k) {
                $siswa = Siswa::where('kelas_id', $k->id)->where('status', 'aktif')->get();
                if ($siswa->isEmpty()) {
                    continue;
                }
                $pilih = (string) ($tujuan[$k->id] ?? '');
                $naikKe = null;
                if ($pilih !== self::LULUS) {
                    if (str_starts_with($pilih, 'baru:')) {
                        $berikut = self::tingkatBerikut($k->tingkat) ?? $k->tingkat;
                        $naikKe = $pastikan(substr($pilih, 5), $berikut, $k->jurusan_id);
                    } elseif ($pilih !== '' && ($t = $kelasKe->firstWhere('id', (int) $pilih))) {
                        $naikKe = $t;
                    } else {
                        throw new RuntimeException("Pilih kelas tujuan untuk {$k->nama}.");
                    }
                }
                foreach ($siswa as $s) {
                    $aksi = $pengecualian[$s->id] ?? ($naikKe ? 'naik' : self::LULUS);
                    if ($aksi === 'tinggal') {
                        $s->update(['kelas_id' => $pastikan($k->nama, $k->tingkat, $k->jurusan_id)->id]);
                        $h['tinggal']++;
                    } elseif ($aksi === 'keluar') {
                        $s->update(['status' => 'keluar']);
                        $h['keluar']++;
                    } elseif ($aksi === self::LULUS) {
                        $s->update(['status' => 'lulus']);
                        $h['lulus']++;
                    } else {
                        $s->update(['kelas_id' => $naikKe->id]);
                        $h['naik']++;
                    }
                }
            }
            if ($aktifkan && ! $ke->aktif) {
                $ke->update(['aktif' => true]);
            }
            Audit::catat('kenaikan.terapkan', $dari->nama.' → '.$ke->nama, $h);

            return $h;
        });
    }
}
