# Penyiapan VM — Pusat Sekolah (dari nol sampai jalan)

Pusat adalah aplikasi **pertama yang dipasang**: aplikasi lain (Absensi, Website, dst.) mendaftar ke sini.
Target: VM **Ubuntu Server 26.04 LTS** (PHP 8.5; 24.04 dengan PHP 8.3 juga bisa), **Nginx + PHP-FPM + MariaDB**, dipublikasikan lewat NPMplus/Cloudflare.
Semua perintah dijalankan sebagai user biasa yang punya `sudo`. Domain yang dipakai: `smkibupakusari.sch.id` (subdomain per aplikasi).

## Urutan pemasangan seluruh sistem
1. **Pusat** (panduan ini) → 2. **Absensi** (`docs/UBUNTU.md` di repo absensi-pusat) → 3. **Website** (`deploy/PANDUAN-VM.md` di repo web-pusat).
Tiap aplikasi boleh di VM sendiri-sendiri (disarankan: 1 VM per aplikasi, 1 GB RAM cukup untuk Pusat) atau satu VM dengan domain berbeda.

## 1. Buat VM di Proxmox
1. Unggah ISO Ubuntu Server 26.04 (atau 24.04) ke storage ISO. **Create VM**: Machine `q35`, centang **Qemu Agent**, disk 20 GB, 1–2 vCPU, 1–2 GB RAM, network `vmbr0`.
2. Pasang Ubuntu, centang **Install OpenSSH server**. Nama host saran: `pusat`.
3. Setelah masuk:
   ```bash
   sudo apt update && sudo apt -y upgrade
   sudo apt -y install qemu-guest-agent && sudo systemctl start qemu-guest-agent
   sudo timedatectl set-timezone Asia/Jakarta
   ```
   > Bila muncul pesan "unit files have no installation config" atau `start` gagal: itu normal (unit ini dijalankan otomatis oleh udev). Pastikan di Proxmox **Options → QEMU Guest Agent = Enabled**, lalu **Shutdown** dan **Start** ulang VM (bukan sekadar reboot); cek dengan `systemctl status qemu-guest-agent`.
4. IP tetap: edit file di `/etc/netplan/` (contoh `192.168.10.30/24`, gateway `192.168.10.254`), lalu `sudo netplan apply`.
5. Firewall (opsional): `sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw --force enable`.

## 2. Pasang paket
```bash
sudo apt -y install nginx mariadb-server git unzip curl composer \
  php-fpm php-cli php-mysql php-sqlite3 php-mbstring php-xml php-zip php-curl php-intl php-bcmath
php -v    # harus >= 8.3 (Ubuntu 26.04 = 8.5)
php --ri pdo_mysql | head -2
```
Paket `php-fpm` dan `php-*` otomatis memakai PHP bawaan Ubuntu (26.04 → 8.5, 24.04 → 8.3). Ubuntu 22.04 hanya punya PHP 8.1: tambahkan PPA `ppa:ondrej/php` dan pakai paket `php8.3-*`.

## 3. Database
```bash
sudo mariadb
```
```sql
CREATE DATABASE pusat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pusat'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON pusat.* TO 'pusat'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Ambil kode

> **`vendor/` sudah ikut di repo** (dibuat dengan `composer install --no-dev`, PHP 8.3). Setelah `git clone`, langkah `composer install` boleh dilewati. Jalankan `composer install` hanya untuk menjalankan tes (butuh paket dev) atau setelah `composer.json` berubah; untuk memperbarui `vendor/` di repo, jalankan workflow **Bundel vendor** (tab Actions di GitHub).

```bash
sudo mkdir -p /var/www/pusat-sekolah && sudo chown $USER:$USER /var/www/pusat-sekolah
git clone https://github.com/andieawan/pusat-sekolah.git /var/www/pusat-sekolah
cd /var/www/pusat-sekolah
composer install --no-dev --optimize-autoloader
```

> **Repo privat? (muncul `Password authentication is not supported`)** GitHub tidak menerima password akun untuk git. Buat **token**:
> GitHub → Settings → Developer settings → Personal access tokens → *Fine-grained tokens* → Repository access: hanya repo ini → Permissions: **Contents = Read-only**.
> Saat `git clone` diminta *Username* isi nama akun GitHub, *Password* isi **token** (bukan password akun). Agar `git pull` untuk pembaruan tidak meminta lagi:
> `git config --global credential.helper store` (jalankan sebelum clone; token tersimpan di `~/.git-credentials` milik user ini). Jangan tempel token ke chat/dokumen. Alternatif: jadikan repo *Public*, atau pakai *Deploy key* (SSH).

## 5. Isi `.env`
```bash
cp .env.example .env && php artisan key:generate && nano .env
```
Yang wajib: `APP_URL=https://pusat.smkibupakusari.sch.id`, `DB_PASSWORD=...`, `TRUSTED_PROXIES=<IP NPMplus>` (satu IP persis),
`SESSION_SECURE_COOKIE=true` (ubah ke `false` bila sementara dibuka lewat `http://IP`).

## 6. Izin folder, tabel, akun superadmin
```bash
sudo chown -R $USER:www-data . && sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
php artisan migrate --seed --force            # tahun ajaran aktif + jurusan awal, TANPA akun
php artisan pusat:buat-admin admin --nama="Administrator"    # password ditanya (tidak ada bawaan)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 7. Nginx
```bash
sudo cp deploy/nginx-pusat.conf /etc/nginx/sites-available/pusat
sudo nano /etc/nginx/sites-available/pusat      # server_name
# samakan versi PHP-FPM otomatis (Ubuntu 26.04 = 8.5, 24.04 = 8.3):
SOK=$(basename $(ls /run/php/php*-fpm.sock | head -1)); sudo sed -i "s|php[0-9.]*-fpm.sock|$SOK|" /etc/nginx/sites-available/pusat
sudo ln -s /etc/nginx/sites-available/pusat /etc/nginx/sites-enabled/pusat
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```
Tes dari VM: `curl -I http://localhost/login` → harus `200`.

## 8. HTTPS / publikasi
- **Lewat NPMplus (disarankan):** Proxy Host → domain `pusat.smkibupakusari.sch.id` → Forward `http://IP-VM:80` → tab SSL: Request certificate, *Force SSL*. DNS Cloudflare awalnya **DNS only**.
- **Langsung Let's Encrypt:** `sudo apt -y install certbot python3-certbot-nginx && sudo certbot --nginx -d pusat.smkibupakusari.sch.id`.
- **Hanya jaringan lokal:** pakai `http://IP-VM`, `SESSION_SECURE_COOKIE=false`. Aplikasi lain tetap bisa tersambung di LAN, tetapi SSO yang melewati internet sebaiknya HTTPS.

Setelah mengubah `.env`: `php artisan config:cache`.

## 9. Penjadwal (WAJIB — pengiriman webhook)
```bash
sudo crontab -u www-data -e
```
```cron
* * * * * cd /var/www/pusat-sekolah && php artisan schedule:run >> /dev/null 2>&1
```
Tanpa baris ini, perubahan data **tidak akan terkirim** ke aplikasi lain. Cek di Admin → Webhook (antrean harus kosong/terkirim).

## 10. Backup & pembaruan
```bash
echo '15 1 * * * root /var/www/pusat-sekolah/deploy/backup.sh' | sudo tee /etc/cron.d/pusat-backup
sudo bash /var/www/pusat-sekolah/deploy/update.sh        # memperbarui versi (backup otomatis dulu)
```
Salin `/var/backups/pusat` ke VPS/komputer lain (`rsync`), dan jadwalkan **Datacenter → Backup** di Proxmox untuk VM ini.
Pulihkan: `gunzip < pusat_TANGGAL.sql.gz | mysql -u pusat -p pusat`.

## 11. Tes & mendaftarkan aplikasi pertama
```bash
composer install && php artisan test       # opsional, butuh paket dev; lalu composer install --no-dev lagi
php artisan pusat:aplikasi-baru absensi \
  --base-url=https://absensi.smkibupakusari.sch.id \
  --redirect=https://absensi.smkibupakusari.sch.id/sso/callback \
  --webhook=https://absensi.smkibupakusari.sch.id/api/pusat/webhook
```
Perintah mencetak `client_id`, `client_secret`, `webhook_secret` **sekali saja** — salin ke `.env` aplikasi tersebut
(lihat panduan masing-masing). Untuk Website: `pusat:aplikasi-baru web ... --redirect=https://WEBSITE/pusat/callback --webhook=https://WEBSITE/pusat/webhook`.
Lalu isi data induk lewat Admin → Impor (urutan: **guru → kelas → siswa**).

## 12. Cek akhir
- [ ] `https://pusat.smkibupakusari.sch.id/login` terbuka (gembok hijau), login superadmin berhasil.
- [ ] Cron `schedule:run` terpasang; Admin → Webhook tidak menumpuk.
- [ ] Admin → Aplikasi: tombol **ping** ke aplikasi terdaftar berhasil.
- [ ] Backup pertama: `sudo bash deploy/backup.sh` menghasilkan berkas di `/var/backups/pusat`.

## 13. Masalah umum
| Gejala | Solusi |
|---|---|
| 500 | `tail -50 storage/logs/laravel.log`; biasanya `APP_KEY` kosong, `.env` salah, atau izin folder (langkah 6) |
| 502 | `ls /run/php/` lalu samakan nama soket di `fastcgi_pass`; `systemctl status php*-fpm` |
| API 401 padahal kredensial benar | Header `Authorization` tidak diteruskan: pastikan baris `fastcgi_param HTTP_AUTHORIZATION` ada |
| Setelah login kembali ke `http://` / redirect SSO ditolak | `TRUSTED_PROXIES` salah, atau NPMplus tidak mengirim `X-Forwarded-Proto` |
| Webhook tidak terkirim | Cron belum terpasang (langkah 9); lihat Admin → Webhook untuk pesan galat |
| Perubahan `.env` tak terbaca | `php artisan config:cache` |
