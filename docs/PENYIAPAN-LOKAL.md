# Mencoba di komputer pribadi (Windows, tanpa VM)

Cara tercepat untuk mencoba sebelum dipasang di VM. Tanpa Nginx, tanpa NPMplus, tanpa HTTPS: tiap aplikasi dijalankan dengan `php artisan serve` pada port berbeda.
Pasang Pusat dulu, lalu aplikasi lain (panduan di repo masing-masing: `docs/PENYIAPAN-LOKAL.md`).

| Aplikasi | Alamat | Port |
|---|---|---|
| Pusat | http://127.0.0.1:8000 | 8000 |
| Absensi | http://127.0.0.1:8001 | 8001 |
| Website | http://127.0.0.1:8002 | 8002 |

> Selalu pakai **`127.0.0.1`** (jangan campur dengan `localhost`) di browser, `.env`, dan pendaftaran aplikasi, karena alamat dicocokkan persis.

## 1. Pasang alat (sekali saja)
1. **Laragon Full** (laragon.org) — sudah berisi PHP, MySQL/MariaDB, Composer. Pasang, jalankan, klik **Start All**.
2. **Git for Windows** (git-scm.com).
3. Buka **Menu → Terminal** di Laragon, lalu cek: `php -v` (harus **8.3 atau lebih baru**; bila lebih lama, tambahkan PHP baru lewat Menu → PHP → Versi → Tambah/Download lalu pilih), `composer -V`, `git --version`.
4. Pastikan ekstensi aktif (Menu → PHP → Ekstensi): `mbstring`, `curl`, `zip`, `intl`, `gd`, `pdo_mysql`, `fileinfo`, `bcmath`, `openssl`.

Semua perintah di bawah dijalankan di **Terminal Laragon**, satu per satu.

## 2. Ambil kode Pusat

> **`vendor/` sudah ikut di repo** (dibuat dengan `composer install --no-dev`, PHP 8.3). Setelah `git clone`, langkah `composer install` boleh dilewati. Jalankan `composer install` hanya untuk menjalankan tes (butuh paket dev) atau setelah `composer.json` berubah; untuk memperbarui `vendor/` di repo, jalankan workflow **Bundel vendor** (tab Actions di GitHub).

```bash
cd C:\laragon\www
git clone https://github.com/andieawan/pusat-sekolah.git
cd pusat-sekolah
composer install
```
(Repo privat: saat diminta *Username* isi nama akun GitHub, *Password* isi **token** — lihat `docs/PENYIAPAN-VM.md` bagian "Repo privat".)

## 3. Database
MySQL Laragon memakai user `root` tanpa password (aman karena hanya di komputer ini).
```bash
mysql -u root -e "CREATE DATABASE pusat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 4. `.env`
```bash
copy .env.example .env
php artisan key:generate
notepad .env
```
Ubah baris berikut:
```ini
APP_URL=http://127.0.0.1:8000
DB_USERNAME=root
DB_PASSWORD=
SESSION_SECURE_COOKIE=false
SESSION_COOKIE=pusat_session
TRUSTED_PROXIES=
```
`SESSION_COOKIE` wajib beda di tiap aplikasi: cookie tidak membedakan port, sehingga tanpa ini login satu aplikasi bisa menimpa aplikasi lain.

## 5. Tabel & akun superadmin
```bash
php artisan migrate --seed
php artisan pusat:buat-admin admin --nama="Administrator"
```
Password ditanya di terminal (min. 10 karakter).

## 6. Jalankan
Buka **dua terminal**:
```bash
php artisan serve --port=8000        # terminal 1
php artisan schedule:work            # terminal 2 — pengirim webhook (tiap menit)
```
Buka http://127.0.0.1:8000/login dan masuk. Menu **Admin → Webhook → Kirim sekarang** juga bisa dipakai tanpa terminal 2.

## 7. Daftarkan aplikasi lain (otomatis)
Satu perintah mendaftarkan Absensi (dan Website, bila diberi `--web-url`), mengisi data contoh, dan **menulis kredensialnya langsung ke `.env` aplikasi itu**. Aman diulang.
```bash
php artisan pusat:siapkan-lokal --absensi-url=http://127.0.0.1:8001 --env-absensi=C:\laragon\www\absensi-pusat\.env --demo
```
Ganti `--absensi-url` dengan alamat Absensi Anda yang sebenarnya (mis. `http://absensi-pusat.test`) dan `--env-absensi` dengan path `.env`-nya. Opsi lain:

| Opsi | Fungsi |
|---|---|
| `--web-url=http://127.0.0.1:8002 --env-web=...\.env` | ikut mendaftarkan Website |
| `--pusat-url=http://pusat-sekolah.test` | alamat Pusat yang dilihat aplikasi lain (bawaan: `APP_URL` Pusat) |
| `--demo` | 3 guru, 2 kelas, 10 siswa contoh + akun `guru.demo` (peran guru di Absensi; password acak ditampilkan sekali) |
| `--putar` | buat rahasia baru bila lupa/hilang (rahasia lama tidak berlaku) |

Perintah menulis `PUSAT_ENABLED`, `PUSAT_URL`, `PUSAT_CLIENT_ID`, `PUSAT_CLIENT_SECRET`, `PUSAT_WEBHOOK_SECRET` ke `.env` target (cadangan: `.env.bak-pusat`; isi lain tidak disentuh). Lalu di aplikasi itu jalankan `php artisan config:clear`. Akun **superadmin Pusat otomatis berperan "admin" di semua aplikasi**, jadi langsung bisa masuk lewat tombol "Masuk lewat Pusat".

Perintah ini ditolak di lingkungan `production`. Di server sungguhan pakai `php artisan pusat:aplikasi-baru` (lihat `README.md`).

## 8. Menjalankan tes (opsional)
`php artisan test` (setelah `composer install` dengan paket dev). Tes memakai SQLite memori, tidak menyentuh database `pusat`.

## 9. Masalah umum
| Gejala | Solusi |
|---|---|
| `Access denied for user 'root'` | `DB_USERNAME`/`DB_PASSWORD` di `.env` salah, atau MySQL belum Start. Lalu `php artisan config:clear` |
| `could not find driver` | Aktifkan ekstensi `pdo_mysql` (langkah 1.4), restart terminal |
| Setelah login langsung keluar / "Page Expired" | `SESSION_SECURE_COOKIE=false`, `SESSION_COOKIE` unik per aplikasi, hapus cookie 127.0.0.1 di browser |
| SSO: redirect_uri tidak cocok | Alamat di pendaftaran harus sama persis dengan `APP_URL` aplikasi (`127.0.0.1`, port benar, tanpa garis miring akhir) |
| Port sudah dipakai | Ganti `--port=` dan sesuaikan `.env` + pendaftaran |
| Webhook tidak terkirim | Terminal `schedule:work` belum jalan, atau aplikasi tujuan belum di-serve |
| Perubahan `.env` tidak terbaca | `php artisan config:clear` |

Catatan: `php artisan serve` hanya untuk uji coba. Untuk penggunaan sekolah, pasang di VM (`docs/PENYIAPAN-VM.md`).
