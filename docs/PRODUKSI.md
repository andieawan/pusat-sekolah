# Runbook Produksi — Pusat Sekolah + Absensi + Website

Urutan ini sudah memperhitungkan semua kesalahan yang muncul saat uji coba. Ikuti **berurutan**; tiap tahap punya
pemeriksaan (✔) — jangan lanjut sebelum ✔ lulus. Langkah rinci pemasangan ada di `PENYIAPAN-VM.md` (Pusat),
`docs/UBUNTU.md` (Absensi) dan `deploy/PANDUAN-VM.md` (Website).

## 0. Sebelum mulai (sekali saja)

| Siapkan | Catatan |
|---|---|
| 3 subdomain | `pusat.smkibupakusari.sch.id`, `absensi.smkibupakusari.sch.id`, dan website di `smkibupakusari.sch.id` (+`www` diarahkan ke situ) — **masing-masing HTTPS sendiri** (jangan satu domain berbagi path) |
| Sertifikat HTTPS **sah** | Let's Encrypt lewat NPMplus. Jangan sertifikat buatan sendiri di produksi |
| PHP 8.3+ | Ubuntu 24.04 = 8.3, 26.04 = 8.5. Paket: `php-fpm php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd` |
| Token GitHub (repo private) | Fine-grained, **Contents: read-only**. Jangan ditempel di chat/dokumen |
| Antar-VM saling terjangkau | Absensi/Website → Pusat (`/api/…`) **dan** Pusat → Absensi/Website (webhook). Uji dengan `curl -i https://pusat…/api/v1/ping` dari VM klien (balasan 401 = jaringan OK) |

> **Hairpin NAT:** bila semua VM satu LAN tetapi domain publik lewat router/NPMplus, VM kadang tak bisa membuka
> domain publiknya sendiri (timeout, "cURL error 28"). Solusi: tambahkan di `/etc/hosts` tiap VM
> `IP-NPMplus  pusat.smkibupakusari.sch.id absensi.smkibupakusari.sch.id smkibupakusari.sch.id`, atau pakai DNS lokal.

### Susunan: 1 aplikasi = 1 VM

| VM | Isi | Domain | Saran awal |
|---|---|---|---|
| vm-pusat | Pusat + MariaDB + cron | `pusat.smkibupakusari.sch.id` | 1–2 vCPU, 2 GB RAM, 20 GB |
| vm-absensi | Absensi + MariaDB + cron | `absensi.smkibupakusari.sch.id` | 2 vCPU, 2–4 GB RAM, 30 GB |
| vm-web | Website + MariaDB | `smkibupakusari.sch.id` | 1–2 vCPU, 2 GB RAM, 20–30 GB |

Aturan jaringan (beri IP tetap/DHCP reservasi untuk tiap VM):
- NPMplus → tiap VM: port 80 (HTTP biasa; HTTPS berhenti di NPMplus).
- Antar-VM: **tidak perlu** akses langsung. Absensi/Website memanggil Pusat, dan Pusat mengirim webhook ke Absensi/Website,
  semuanya **lewat domain HTTPS** (jadi lewat NPMplus). Karena itu tiap VM wajib bisa me-resolve ketiga subdomain
  (DNS lokal atau `/etc/hosts` → IP NPMplus; lihat catatan hairpin di bawah).
- Firewall tiap VM (`ufw`): `sudo ufw allow from <IP-NPMplus> to any port 80 proto tcp`, `sudo ufw allow from <IP-admin> to any port 22 proto tcp`, `sudo ufw enable`.
  Database dibiarkan hanya di `127.0.0.1` (default MariaDB) — tiap VM punya database sendiri.
- Tiap VM punya cron `schedule:run` dan backup sendiri; Pusat dan Absensi sebaiknya di-backup ke tempat berbeda dari VM-nya.

### DNS (Cloudflare) dan NPMplus

Buat record `A`/`CNAME` untuk `pusat`, `absensi`, `@` (website) dan `www` ke IP publik/tunnel yang sama. Di NPMplus buat satu
Proxy Host per subdomain → teruskan ke IP VM masing-masing (port 80), centang *Force SSL* + *HTTP/2*, minta sertifikat
Let's Encrypt. Bila memakai proxy Cloudflare (awan oranye), mode SSL harus **Full (strict)**. Lalu isi `TRUSTED_PROXIES`
di tiap VM dengan IP NPMplus. Cookie sesi tiap subdomain terpisah (jangan isi `SESSION_DOMAIN`).

## 1. Pasang Pusat  (`PENYIAPAN-VM.md`)

Hal yang paling sering salah:

1. **Root nginx = `…/public`**, bukan folder proyek (salah → 403/500). Pakai `deploy/nginx-pusat.conf`;
   `fastcgi_param HTTP_AUTHORIZATION` wajib ada (API memakai Basic auth; tanpa ini klien dapat 401).
2. **Nama socket PHP-FPM** berbeda per versi (`ls /run/php/`). Salah → 502.
3. `.env`: `APP_KEY` (`php artisan key:generate --force`), `DB_PASSWORD` **tidak boleh kosong**,
   `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=<IP NPMplus>`, `APP_DEBUG=false`.
   Sesudah mengubah `.env`: `php artisan config:clear` (atau `config:cache` ulang).
4. `php artisan migrate --seed --force`, lalu `php artisan pusat:buat-admin <username>`.
5. **Cron wajib** (kalau tidak, webhook tidak pernah terkirim):
   `sudo crontab -u www-data -e` →  `* * * * * cd /var/www/pusat-sekolah && php artisan schedule:run >> /dev/null 2>&1`
6. Izin: `sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache`

✔ **`php artisan pusat:periksa`** — harus berakhir "Semua siap" (atau hanya peringatan yang Anda pahami).
✔ Buka `https://pusat…/` → halaman login; masuk dengan superadmin.

## 2. Daftarkan aplikasi di Pusat (Menu Aplikasi → Daftarkan)

Isi **alamat produksi** (https) — bukan alamat uji:

| Kolom | Absensi | Website |
|---|---|---|
| Kode | `absensi` | `web` |
| URL dasar | `https://absensi.smkibupakusari.sch.id` | `https://smkibupakusari.sch.id` |
| Redirect URI | `https://absensi.smkibupakusari.sch.id/sso/callback` | `https://smkibupakusari.sch.id/pusat/callback` |
| URL webhook | `https://absensi.smkibupakusari.sch.id/api/pusat/webhook` | `https://smkibupakusari.sch.id/pusat/webhook` |

Redirect URI harus **persis sama** (https/http, www, tanpa garis miring di akhir). Salin **Client ID**, **Client Secret**
dan **Webhook Secret** — secret hanya tampil sekali. Beri tiap pengguna akses/peran di menu Akses
(superadmin otomatis `admin` di semua aplikasi).

## 3. Pasang Absensi (`docs/UBUNTU.md`) lalu sambungkan

Di `.env` Absensi:

```
PUSAT_ENABLED=true
PUSAT_URL=https://pusat.smkibupakusari.sch.id
PUSAT_CLIENT_ID=…
PUSAT_CLIENT_SECRET=…
PUSAT_WEBHOOK_SECRET=…
```
(Jangan isi `PUSAT_VERIFY_SSL` / `PUSAT_CA_BUNDLE` di produksi.) Lalu `php artisan config:clear`.

✔ `php artisan absensi:periksa` → bagian "Sambungan ke Pusat" harus **[OK] Pusat menjawab /api/v1/ping**.
✔ Admin → Integrasi Pusat → **Uji Koneksi** hijau → **Periksa Perubahan** (simulasi) → **Terapkan**.
✔ Login lewat tombol "Masuk dengan Pusat" berhasil.

## 4. Pasang Website (`deploy/PANDUAN-VM.md`) lalu sambungkan

Sama: `PUSAT_ENABLED=true`, `PUSAT_URL`, `PUSAT_CLIENT_ID`, `PUSAT_CLIENT_SECRET`, `PUSAT_WEBHOOK_SECRET`.
✔ `php artisan web:periksa`. ✔ Login admin lewat Pusat (akun Pusat dengan peran `admin` di aplikasi web;
penautan pertama berdasarkan **email**, jadi email di Pusat harus sama dengan email admin website).

## 5. Uji menyeluruh sebelum dipakai guru

1. Ubah nama seorang guru di Pusat → tunggu ≤ 1–2 menit → muncul perubahan di Absensi (webhook, butuh cron).
   Jika tidak: Pusat → menu Aplikasi → Webhook → lihat galat; `pusat:periksa` menunjukkan webhook tertahan.
2. Nonaktifkan akun uji di Pusat → akun itu tidak bisa masuk lagi di Absensi.
3. Cadangan: jalankan `sudo bash deploy/backup.sh` (Pusat) dan `php artisan absensi:backup`; pastikan file dibuat.
   Tes **restore** ke database kosong sekali sebelum produksi.

## 6. Pemeliharaan

| Tugas | Perintah |
|---|---|
| Perbarui | `cd /var/www/<app> && sudo bash deploy/update.sh` (mem-backup, menarik kode, migrasi, lalu menjalankan `…:periksa`) |
| Cek kesiapan kapan saja | `php artisan pusat:periksa` / `absensi:periksa` / `web:periksa` (`--cepat` = tanpa DB/jaringan) |
| Putar rahasia aplikasi | Pusat → Aplikasi → Rotasi secret; perbarui `.env` klien; `config:clear` |
| Log galat | `storage/logs/laravel.log`, `/var/log/nginx/error.log` |

## 7. Daftar kesalahan umum → penyebab

| Gejala | Penyebab | Perbaikan |
|---|---|---|
| 403 nginx | root bukan `public/` / izin | arahkan `root` ke `…/public` |
| 502 Bad Gateway | socket PHP-FPM salah | `ls /run/php/`, samakan di conf nginx |
| 500 tanpa pesan | `.env`/`APP_KEY`/DB belum benar, storage tak bisa ditulis | `…:periksa`; lihat `laravel.log` |
| `Access denied for user` | `DB_PASSWORD` kosong/beda | betulkan `.env`, `config:clear` |
| `vendor/autoload.php` hilang | clone tanpa vendor | `composer install --no-dev --optimize-autoloader` |
| Login berputar di halaman login | `SESSION_SECURE_COOKIE=true` tetapi akses http, atau `APP_URL` salah | samakan skema & `APP_URL` |
| `invalid_redirect_uri` | Redirect URI di Pusat ≠ alamat callback | samakan persis |
| Klien dapat 401 dari `/api/v1/*` | secret salah / `HTTP_AUTHORIZATION` tak diteruskan nginx | lihat conf nginx Pusat |
| cURL error 60 | sertifikat tak dipercaya | produksi: sertifikat sah. Lokal saja: `PUSAT_CA_BUNDLE` |
| cURL error 28 (timeout) | hairpin NAT / firewall / server PHP lokal 1 pekerja | `/etc/hosts`, buka firewall; lokal: `PHP_CLI_SERVER_WORKERS=4` |
| Webhook tak sampai | cron `schedule:run` belum jalan, atau URL/secret webhook salah | cek crontab; `pusat:periksa` |
| `git pull` gagal "local changes … vendor" | composer mengubah berkas di vendor/ | `git ls-files -m -- vendor \| xargs git checkout --` (sudah otomatis di `update.sh`) |
| `git clone` "Password authentication is not supported" | repo private | pakai token (lihat langkah clone di panduan) |
| Halaman Laravel "419" saat login lewat `http://IP` | `SESSION_SECURE_COOKIE=true` tetapi akses http | set `false` untuk uji http; kembalikan `true` setelah HTTPS |
| Tampilan polos (tanpa CSS) lewat HTTPS | aset dibuat `http://` (mixed content) karena proxy tak tepercaya | `git pull` (Absensi memaksa https bila `APP_URL` https); isi `TRUSTED_PROXIES` dengan IP NPMplus dari `access.log` |
| `dubious ownership` / `Permission denied` di `vendor/` | folder di-clone dengan `sudo` (milik root) | `sudo chown -R $USER:www-data /var/www/<folder>`; clone tanpa sudo |
| `Unable to set application key` | cache konfigurasi lama / `.env` rusak saat tempel banyak baris ke nano | `cp .env.example .env`, `php artisan config:clear`, lalu isi satu per satu |
| `Access denied ... using password: NO/YES` | `DB_PASSWORD` kosong/beda dari MariaDB | buat ulang user dengan password acak (lihat `PENYIAPAN-VM.md`) |
| Pusat: "redirect_uri tidak terdaftar" | Redirect URI di Pusat ≠ alamat callback yang dikirim klien | samakan persis, pakai https & domain, bukan IP |
| Webhook `ping` HTTP 404 | `PUSAT_ENABLED=false` atau secret kosong di klien | isi `.env` klien, `config:clear`, kirim ulang di Pusat → Webhook |

## 8. Alat khusus uji lokal — JANGAN dipakai di produksi

`php artisan pusat:siapkan-lokal`, `--abaikan-ssl`, `PUSAT_VERIFY_SSL=false`, `PUSAT_WEBHOOK_VERIFY_SSL=false`.
Perintah `siapkan-lokal` menolak berjalan saat `APP_ENV=production`; `…:periksa` menandai opsi SSL-mati sebagai **GAGAL** di produksi.
