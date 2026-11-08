<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\Notifikasi;

/**
 * Laporan hasil sinkron dari aplikasi. Disimpan di baris aplikasi (kolom "Sinkron terakhir") dan, bila perlu,
 * menjadi notifikasi admin: jawaban atas permintaan sinkron dari Pusat, sinkron manual di aplikasi, gagal
 * (sekali per galat yang sama), dan pulih setelah gagal. Sinkron otomatis rutin yang berhasil tidak menimbulkan notifikasi.
 */
class LaporanSinkron
{
    private const LABEL = ['baru' => 'baru', 'ubah' => 'diubah', 'keluar' => 'keluar', 'nonaktif' => 'dinonaktifkan',
        'hapus' => 'dihapus', 'dihapus' => 'dihapus', 'dicabut' => 'dicabut'];

    public static function terima(Aplikasi $app, array $d): ?Notifikasi
    {
        $ok = (bool) ($d['ok'] ?? false);
        $pemicu = in_array($d['pemicu'] ?? '', ['manual', 'otomatis'], true) ? $d['pemicu'] : 'otomatis';
        $galat = $ok ? '' : (mb_substr(trim((string) ($d['galat'] ?? '')), 0, 300) ?: 'tanpa keterangan');
        $hasil = is_array($d['hasil'] ?? null) && strlen((string) json_encode($d['hasil'])) <= 20000 ? $d['hasil'] : [];
        $ringkas = $ok ? self::ringkas($hasil) : '';

        $lama = $app->sinkron_laporan ?: [];
        $menjawab = $app->sinkron_diminta_pada !== null;
        $app->forceFill([
            'sinkron_lapor_pada' => now(),
            'sinkron_laporan' => ['ok' => $ok, 'pemicu' => $menjawab ? 'diminta Pusat' : $pemicu, 'ringkas' => $ringkas, 'galat' => $galat],
            'sinkron_diminta_pada' => $ok ? null : $app->sinkron_diminta_pada,
        ])->save();

        $pulih = $ok && $lama && empty($lama['ok']);
        $galatBaru = ! $ok && (empty($lama) || ! empty($lama['ok']) || ($lama['galat'] ?? '') !== $galat || $menjawab);
        if (! ($ok && ($menjawab || $pemicu === 'manual' || $pulih)) && ! $galatBaru) {
            return null;
        }
        $isi = $ok ? ($ringkas ?: 'Tidak ada perubahan data.') : $galat;
        if ($ok && $pemicu === 'manual' && ! $menjawab) {
            $isi = 'Disinkronkan manual dari aplikasi. '.$isi;
        }

        return Notifikasi::create([
            'aplikasi_id' => $app->id,
            'jenis' => $ok ? 'ok' : 'galat',
            'judul' => $ok ? "{$app->nama} selesai sinkron".($pulih && ! $menjawab ? ' (pulih)' : '') : "{$app->nama} gagal sinkron",
            'isi' => mb_substr($isi, 0, 500),
        ]);
    }

    /** ['siswa'=>['baru'=>3,'ubah'=>1,'sama'=>200], 'akun'=>['baru'=>2]] → "siswa 3 baru, 1 diubah · akun 2 baru". */
    public static function ringkas(array $hasil): string
    {
        $bagian = [];
        foreach ($hasil as $nama => $v) {
            if (! is_array($v) || ! is_string($nama)) {
                continue;
            }
            $isi = [];
            foreach (self::LABEL as $k => $label) {
                if (isset($v[$k]) && is_numeric($v[$k]) && (int) $v[$k] > 0) {
                    $isi[] = (int) $v[$k].' '.$label;
                }
            }
            if ($isi) {
                $bagian[] = str_replace('_', ' ', $nama).' '.implode(', ', array_unique($isi));
            }
        }

        return mb_substr($bagian ? implode(' · ', $bagian) : 'Tidak ada perubahan data.', 0, 300);
    }
}
