<?php

namespace App\Support;

/**
 * Normalisasi dan validasi satu baris impor (tanpa akses database).
 * Setiap fungsi mengembalikan ['data' => [...], 'kelas' => ?string, 'galat' => [...]].
 */
final class BarisImpor
{
    public const STATUS_SISWA = ['aktif', 'lulus', 'pindah', 'keluar'];

    public const JENIS_PEGAWAI = ['guru', 'staf'];

    private const POLA_ID = '/^[A-Za-z0-9\/.\-]{1,30}$/'; // mis. 10408/771.111

    /** @return string|null|false  Y-m-d, null jika kosong, false jika tidak sah */
    public static function tanggal(?string $v): string|null|false
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
            [$y, $b, $t] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $v, $m)) {
            [$t, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return false;
        }

        return checkdate($b, $t, $y) ? sprintf('%04d-%02d-%02d', $y, $b, $t) : false;
    }

    public static function telepon(?string $v): ?string
    {
        $v = preg_replace('/[^0-9+]/', '', (string) $v);

        return $v === '' || $v === null ? null : mb_substr($v, 0, 30);
    }

    public static function jenisKelamin(?string $v): string|null|false
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }

        return match ($v) {
            'l', 'lk', 'laki-laki', 'laki laki', 'pria' => 'L',
            'p', 'pr', 'perempuan', 'wanita' => 'P',
            default => false,
        };
    }

    public static function siswa(array $b): array
    {
        $galat = [];
        $nis = trim($b['nis'] ?? '');
        if (! preg_match(self::POLA_ID, $nis)) {
            $galat[] = 'NIS wajib diisi (huruf/angka/. / -, maks 30 karakter)';
        }
        $nama = trim($b['nama'] ?? '');
        if ($nama === '' || mb_strlen($nama) > 150) {
            $galat[] = 'Nama wajib diisi (maks 150 karakter)';
        }
        $nisn = preg_replace('/\D/', '', (string) ($b['nisn'] ?? ''));
        if (mb_strlen($nisn) > 20) {
            $galat[] = 'NISN terlalu panjang';
        }
        $jk = self::jenisKelamin($b['jenis_kelamin'] ?? '');
        if ($jk === false) {
            $galat[] = 'Jenis kelamin harus L/P';
            $jk = null;
        }
        $status = strtolower(trim($b['status'] ?? '')) ?: 'aktif';
        if (! in_array($status, self::STATUS_SISWA, true)) {
            $galat[] = 'Status harus salah satu dari: '.implode(', ', self::STATUS_SISWA);
            $status = 'aktif';
        }
        $tgl = self::tanggal($b['tanggal_lahir'] ?? '');
        if ($tgl === false) {
            $galat[] = 'Tanggal lahir tidak sah (gunakan YYYY-MM-DD atau DD/MM/YYYY)';
            $tgl = null;
        }

        return [
            'data' => [
                'nis' => $nis,
                'nisn' => $nisn !== '' ? $nisn : null,
                'nama' => $nama,
                'jenis_kelamin' => $jk,
                'status' => $status,
                'tempat_lahir' => ($t = trim($b['tempat_lahir'] ?? '')) !== '' ? mb_substr($t, 0, 100) : null,
                'tanggal_lahir' => $tgl,
                'no_hp' => self::telepon($b['no_hp'] ?? ''),
                'no_hp_ortu' => self::telepon($b['no_hp_ortu'] ?? ''),
                'nama_ortu' => ($o = trim($b['nama_ortu'] ?? '')) !== '' ? mb_substr($o, 0, 150) : null,
            ],
            'kelas' => ($k = trim($b['kelas'] ?? '')) !== '' ? $k : null,
            'galat' => $galat,
        ];
    }

    public static function pegawai(array $b): array
    {
        $galat = [];
        $nip = trim($b['nip'] ?? '');
        if (! preg_match(self::POLA_ID, $nip)) {
            $galat[] = 'NIP/NUPTK/ID pegawai wajib diisi (maks 30 karakter)';
        }
        $nama = trim($b['nama'] ?? '');
        if ($nama === '' || mb_strlen($nama) > 150) {
            $galat[] = 'Nama wajib diisi (maks 150 karakter)';
        }
        $jenis = strtolower(trim($b['jenis'] ?? '')) ?: 'guru';
        if (! in_array($jenis, self::JENIS_PEGAWAI, true)) {
            $galat[] = 'Jenis harus guru atau staf';
            $jenis = 'guru';
        }
        $email = trim($b['email'] ?? '');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $galat[] = 'Email tidak sah';
            $email = '';
        }

        return [
            'data' => [
                'nip' => $nip,
                'nama' => $nama,
                'jenis' => $jenis,
                'jabatan' => ($j = trim($b['jabatan'] ?? '')) !== '' ? mb_substr($j, 0, 100) : null,
                'no_hp' => self::telepon($b['no_hp'] ?? ''),
                'email' => $email !== '' ? $email : null,
            ],
            'kelas' => null,
            'galat' => $galat,
        ];
    }

    /** Kelas: jurusan = kode jurusan, tahun_ajaran = nama (kosong = tahun aktif), wali = NIP guru. */
    public static function kelas(array $b): array
    {
        $galat = [];
        $nama = trim($b['nama'] ?? '');
        if ($nama === '' || mb_strlen($nama) > 50) {
            $galat[] = 'Nama kelas wajib diisi (maks 50 karakter)';
        }
        $tingkat = strtoupper(trim($b['tingkat'] ?? ''));
        if (! in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $galat[] = 'Tingkat harus X, XI, atau XII';
        }

        return [
            'data' => ['nama' => $nama, 'tingkat' => $tingkat],
            'jurusan' => ($j = trim($b['jurusan'] ?? '')) !== '' ? $j : null,
            'tahun' => ($t = trim($b['tahun_ajaran'] ?? '')) !== '' ? $t : null,
            'wali' => ($w = trim($b['wali'] ?? '')) !== '' ? $w : null,
            'kelas' => null,
            'galat' => $galat,
        ];
    }
}
