<?php

/**
 * Tes logika mandiri (tanpa Laravel/Composer):  php tests-logic/run.php
 * Hanya menguji kelas murni di app/Support. Tes lengkap ada di tests/ (php artisan test).
 */

spl_autoload_register(function (string $kelas) {
    if (str_starts_with($kelas, 'App\\Support\\')) {
        require __DIR__.'/../app/Support/'.substr($kelas, strlen('App\\Support\\')).'.php';
    }
});

use App\Support\Backoff;
use App\Support\BarisImpor;
use App\Support\CsvParser;
use App\Support\Pkce;
use App\Support\RedirectUri;
use App\Support\Tanda;

$lulus = 0;
$gagal = 0;

function sama(mixed $diharapkan, mixed $aktual, string $nama): void
{
    global $lulus, $gagal;
    if ($diharapkan === $aktual) {
        $lulus++;

        return;
    }
    $gagal++;
    echo "GAGAL: $nama\n  diharapkan: ".var_export($diharapkan, true)."\n  aktual:     ".var_export($aktual, true)."\n";
}

// ---- Tanda (HMAC webhook) ----
$badan = '{"event":"siswa.dibuat"}';
$t = Tanda::buat('rahasia', 1700000000, $badan);
sama(true, str_starts_with($t, 'sha256=') && strlen($t) === 71, 'format tanda');
sama(true, Tanda::verifikasi('rahasia', 1700000000, $badan, $t, 300, 1700000100), 'verifikasi sah');
sama(false, Tanda::verifikasi('salah', 1700000000, $badan, $t, 300, 1700000100), 'rahasia salah ditolak');
sama(false, Tanda::verifikasi('rahasia', 1700000000, $badan.' ', $t, 300, 1700000100), 'badan diubah ditolak');
sama(false, Tanda::verifikasi('rahasia', 1700000000, $badan, $t, 300, 1700000400), 'kedaluwarsa ditolak (replay)');
sama(false, Tanda::verifikasi('rahasia', 1700000000, $badan, $t, 300, 1699999600), 'timestamp dari masa depan ditolak');

// ---- PKCE (contoh dari RFC 7636 lampiran B) ----
$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
$tantangan = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
sama($tantangan, Pkce::tantangan($verifier), 'pkce rfc7636');
sama(true, Pkce::cocok($verifier, $tantangan), 'pkce cocok');
sama(false, Pkce::cocok($verifier.'x', $tantangan), 'pkce verifier salah');
sama(false, Pkce::cocok('pendek', Pkce::tantangan('pendek')), 'pkce verifier terlalu pendek');

// ---- Redirect URI ----
$ok = ['https://absensi.smkibupakusari.sch.id/sso/callback', 'http://192.168.10.12/sso/callback'];
sama(true, RedirectUri::cocok('https://absensi.smkibupakusari.sch.id/sso/callback', $ok), 'uri persis cocok');
sama(false, RedirectUri::cocok('https://absensi.smkibupakusari.sch.id/sso/callback/', $ok), 'uri beda trailing slash ditolak');
sama(false, RedirectUri::cocok('https://evil.example/sso/callback', $ok), 'domain lain ditolak');
sama(false, RedirectUri::cocok('javascript:alert(1)', ['javascript:alert(1)']), 'skema javascript ditolak');
sama(false, RedirectUri::cocok('https://user:pw@absensi.smkibupakusari.sch.id/sso/callback', ['https://user:pw@absensi.smkibupakusari.sch.id/sso/callback']), 'userinfo ditolak');
sama(false, RedirectUri::cocok('https://absensi.smkibupakusari.sch.id/sso/callback#x', ['https://absensi.smkibupakusari.sch.id/sso/callback#x']), 'fragment ditolak');
sama('http://192.168.10.12', RedirectUri::asal('http://192.168.10.12/sso/callback'), 'asal tanpa port');
sama('https://a.test:8443', RedirectUri::asal('HTTPS://A.test:8443/x?y=1'), 'asal dengan port');
sama(true, RedirectUri::seAsal('https://absensi.smkibupakusari.sch.id/keluar', $ok), 'logout se-asal');
sama(false, RedirectUri::seAsal('https://evil.example/keluar', $ok), 'logout beda asal ditolak');
sama('https://a.test/cb?code=abc&state=xyz', RedirectUri::tambahQuery('https://a.test/cb', ['code' => 'abc', 'state' => 'xyz']), 'tambah query');
sama('https://a.test/cb?x=1&code=a%26b', RedirectUri::tambahQuery('https://a.test/cb?x=1', ['code' => 'a&b']), 'tambah query ke query yang ada');

// ---- Backoff ----
sama(60, Backoff::detik(1), 'backoff 1');
sama(300, Backoff::detik(2), 'backoff 2');
sama(21600, Backoff::detik(5), 'backoff 5');
sama(null, Backoff::detik(6), 'backoff menyerah di 6');
sama(60, Backoff::detik(0), 'backoff 0 diperlakukan 1');

// ---- CSV ----
$csv = "\xEF\xBB\xBFNIS;Nama Lengkap;Kelas\r\n10408/771.111;\"Budi, S.\";XI DKV 1\r\n\r\n10409/771.112;Siti;XI DKV 1\r\n";
$h = CsvParser::parse($csv);
sama(['nis', 'nama_lengkap', 'kelas'], $h['header'], 'header dinormalkan + BOM dibuang');
sama(2, count($h['baris']), 'baris kosong dilewati');
sama('Budi, S.', $h['baris'][0]['nama_lengkap'], 'kutipan + koma di dalam sel');
sama('4', $h['baris'][1]['_baris'], 'nomor baris asli (untuk pesan galat)');
$h2 = CsvParser::parse("nis,nama\n1,A\n2,B,ekstra\n3");
sama('A', $h2['baris'][0]['nama'], 'pemisah koma terdeteksi');
sama('', $h2['baris'][2]['nama'], 'kolom kurang diisi kosong');
$w1252 = "nis;nama\n1;Jos\xE9\n"; // "José" Windows-1252
sama('José', CsvParser::parse($w1252)['baris'][0]['nama'], 'konversi Windows-1252');
$terlempar = false;
try {
    CsvParser::parse('');
} catch (InvalidArgumentException) {
    $terlempar = true;
}
sama(true, $terlempar, 'file kosong ditolak');
$besar = "nis\n".str_repeat("1\n", CsvParser::MAKS_BARIS + 1);
$terlempar = false;
try {
    CsvParser::parse($besar);
} catch (InvalidArgumentException) {
    $terlempar = true;
}
sama(true, $terlempar, 'melebihi batas baris ditolak');

// ---- Impor: tanggal ----
sama('2008-03-09', BarisImpor::tanggal('2008-03-09'), 'tanggal iso');
sama('2008-03-09', BarisImpor::tanggal('9/3/2008'), 'tanggal d/m/Y');
sama('2008-03-09', BarisImpor::tanggal('09-03-2008'), 'tanggal d-m-Y');
sama(false, BarisImpor::tanggal('31/02/2008'), 'tanggal tidak ada ditolak');
sama(false, BarisImpor::tanggal('kemarin'), 'tanggal sembarang ditolak');
sama(null, BarisImpor::tanggal('  '), 'tanggal kosong');

// ---- Impor: siswa ----
$s = BarisImpor::siswa(['nis' => '10408/771.111', 'nisn' => '00 12 345', 'nama' => 'Budi', 'jenis_kelamin' => 'Laki-laki', 'kelas' => 'XI DKV 1', 'tanggal_lahir' => '5/6/2009', 'no_hp' => '0812-3456 789', 'status' => '']);
sama([], $s['galat'], 'siswa sah tanpa galat');
sama('L', $s['data']['jenis_kelamin'], 'jk dipetakan');
sama('0012345', $s['data']['nisn'], 'nisn hanya angka');
sama('08123456789', $s['data']['no_hp'], 'telepon dibersihkan');
sama('aktif', $s['data']['status'], 'status default');
sama('XI DKV 1', $s['kelas'], 'nama kelas dibawa');
$s = BarisImpor::siswa(['nis' => '', 'nama' => '', 'jenis_kelamin' => 'x', 'status' => 'alumni', 'tanggal_lahir' => 'abc']);
sama(5, count($s['galat']), 'siswa tidak sah: lima galat');
$s = BarisImpor::siswa(['nis' => '12; DROP TABLE siswa', 'nama' => 'X']);
sama(true, count($s['galat']) === 1, 'NIS dengan karakter aneh ditolak');

// ---- Impor: kelas & nama ortu ----
$k = BarisImpor::kelas(['nama' => 'XI DKV 1', 'tingkat' => 'xi', 'jurusan' => 'DKV', 'wali' => '123']);
sama([], $k['galat'], 'kelas sah');
sama('XI', $k['data']['tingkat'], 'tingkat dinormalkan');
sama('DKV', $k['jurusan'], 'kode jurusan dibawa');
sama(null, $k['tahun'], 'tahun kosong = aktif');
sama(2, count(BarisImpor::kelas(['nama' => '', 'tingkat' => 'IX'])['galat']), 'kelas tidak sah: dua galat');
$s = BarisImpor::siswa(['nis' => '1', 'nama' => 'A', 'nama_ortu' => '  Pak Budi ']);
sama('Pak Budi', $s['data']['nama_ortu'], 'nama ortu dirapikan');

// ---- Impor: pegawai ----
$p = BarisImpor::pegawai(['nip' => '1985010120100110', 'nama' => 'Bu Wati', 'jenis' => 'GURU', 'email' => 'wati@contoh.id']);
sama([], $p['galat'], 'pegawai sah');
sama('guru', $p['data']['jenis'], 'jenis huruf kecil');
$p = BarisImpor::pegawai(['nip' => '', 'nama' => 'X', 'jenis' => 'kepsek', 'email' => 'bukan-email']);
sama(3, count($p['galat']), 'pegawai tidak sah: tiga galat');

echo "\nLulus: $lulus, Gagal: $gagal\n";
exit($gagal === 0 ? 0 : 1);
