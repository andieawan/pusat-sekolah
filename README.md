# Pusat Sekolah — SMKS Islam Bustanul Ulum

Aplikasi pusat yang mengatur **login tunggal (SSO)**, **data induk** (tahun ajaran, jurusan, kelas, siswa, guru/staf),
**hak akses per aplikasi**, **API**, dan **webhook** untuk aplikasi-aplikasi sekolah (Absensi, BK, TU, PKL, Website).
Tiap aplikasi tetap punya database sendiri; Pusat hanya menyimpan data yang dipakai bersama.

Laravel 13 · PHP 8.3+ · MySQL 8 / MariaDB 10.6+ · tanpa proses build front-end.

## Pasang di VM Ubuntu

> Tutorial lengkap dari nol (Proxmox → Nginx → HTTPS → cron → backup): [`docs/PENYIAPAN-VM.md`](docs/PENYIAPAN-VM.md).

```bash
# 1. Kebutuhan: php8.3 (+ extension mbstring xml curl mysql sqlite3 zip bcmath intl), composer, mysql
cd /var/www && composer install --no-dev --optimize-autoloader   # di dalam folder proyek ini

# 2. Konfigurasi
cp .env.example .env
php artisan key:generate
nano .env        # APP_URL, DB_*, TRUSTED_PROXIES (IP NPMplus, mis. 192.168.10.10)

# 3. Database
mysql -e "CREATE DATABASE pusat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed          # tahun ajaran aktif + jurusan awal; TIDAK membuat akun

# 4. Akun superadmin pertama (password ditanya interaktif, tidak ada password bawaan)
php artisan pusat:buat-admin admin --nama="Administrator"

# 5. Izin folder
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Jalankan tes dulu** (membutuhkan paket dev): `composer install && php artisan test`

### Penjadwal (wajib untuk webhook)

```
* * * * * cd /var/www/pusat-sekolah && php artisan schedule:run >> /dev/null 2>&1
```
Menjalankan `webhook:kirim` tiap menit dan `pusat:bersihkan` (hapus kode/token kedaluwarsa) tiap hari.

### Di belakang NPMplus / nginx

- Isi `TRUSTED_PROXIES` dengan IP NPMplus agar HTTPS dan IP asli terbaca.
- Pastikan header `Authorization` diteruskan ke PHP-FPM (API memakai HTTP Basic). Di nginx:
  `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`
- `SESSION_SECURE_COOKIE=true` bila diakses lewat HTTPS.

## Mendaftarkan aplikasi

```bash
php artisan pusat:aplikasi-baru absensi \
  --base-url=https://absensi.smkibu.example \
  --redirect=https://absensi.smkibu.example/sso/callback \
  --webhook=https://absensi.smkibu.example/hooks/pusat
```
Perintah mencetak `client_id`, `client_secret`, dan `webhook_secret` **sekali saja** — simpan di `.env` aplikasi tersebut.
Bisa juga lewat panel: **Admin → Aplikasi** (ada tombol rotasi rahasia dan uji ping webhook).

### Mengisi Pusat dari aplikasi yang sudah berjalan

Urutan impor di panel **Admin → Impor CSV**: **Guru & staf → Kelas → Siswa** (selalu ada pratinjau dulu).
Akun login guru dibuat dari CSV dengan `php artisan pusat:impor-akun akun.csv --aplikasi=absensi` (simulasi),
lalu ulangi dengan `--terapkan`; password sementara ditulis ke satu berkas yang harus dibagikan lalu dihapus.
Untuk Absensi, CSV-nya dibuat oleh `php artisan pusat:ekspor` di sisi Absensi (lihat `docs/PUSAT.md` di repo Absensi).

Preset (peran & scope bawaan): `absensi`, `bk`, `tu`, `pkl`, `web` — lihat `config/pusat.php`.

## Panel admin (hanya superadmin)

Data induk (CRUD + impor CSV dengan pratinjau), akun & peran per aplikasi, aplikasi & kredensial, antrean webhook
(kirim ulang), dan log audit.

## Dokumen

- `docs/PENYIAPAN-VM.md` — tutorial penyiapan VM
- `docs/api.md` — kontrak API & webhook
- `docs/integrasi.md` — cara menyambungkan sebuah aplikasi (SSO, penerima webhook, sinkron data) + contoh PHP/Laravel
- `docs/openapi.yaml` — spesifikasi OpenAPI

## Keamanan singkat

Kode SSO sekali pakai (60 dtk), token 1 jam; hanya hash disimpan. `redirect_uri` dicocokkan persis. PKCE S256 didukung.
Webhook ditandatangani HMAC-SHA256 + timestamp. Login dibatasi 5 gagal/15 menit. Siswa/pegawai yang dihapus atau
tidak aktif otomatis menonaktifkan akun dan mencabut tokennya. Tidak ada password/rahasia bawaan.

## Pengujian tanpa Composer

`php tests-logic/run.php` menjalankan tes logika murni (HMAC, PKCE, redirect URI, backoff, CSV, validasi impor).
Tes fitur lengkap ada di `tests/Feature` (`php artisan test`).
