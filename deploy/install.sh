#!/usr/bin/env bash
# Instalasi Pusat Sekolah (SSO + data induk) di VM Ubuntu Server 24.04 / 26.04 (atau Debian 13).
# Dipakai untuk VM baru, atau memasang ulang Pusat setelah VM rusak (lalu pulihkan database dari backup).
#
#   git clone https://github.com/andieawan/pusat-sekolah.git /var/www/pusat-sekolah   # TANPA sudo (repo privat: isi token)
#   cd /var/www/pusat-sekolah
#   sudo DOMAIN=pusat.smkibupakusari.sch.id APP_URL=https://pusat.smkibupakusari.sch.id PROXY_IP=192.168.10.10 bash deploy/install.sh
#
# Variabel opsional (awali perintah dengan NAMA=nilai):
#   DOMAIN         nama domain (default: _ = melayani akses lewat IP juga)
#   APP_URL        alamat publik lengkap (default: http://$DOMAIN, atau http://IP-VM)
#   PROXY_IP       IP reverse proxy (NPMplus) yang dipercaya (default 192.168.10.10)
#   DB_NAME/DB_USER  (default pusat / pusat)
#   ADMIN_USER     username superadmin pertama (default admin; password ditanyakan)
#   SKIP_DB_INSTALL=1  jangan pasang MariaDB (pakai server MySQL/MariaDB yang sudah ada)
#
# Aman diulang: .env, database, dan akun yang sudah ada tidak diubah.
set -euo pipefail

NAMA="pusat"
DOMAIN="${DOMAIN:-_}"
IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [ "$DOMAIN" = "_" ]; then APP_URL="${APP_URL:-http://${IP:-localhost}}"; else APP_URL="${APP_URL:-http://$DOMAIN}"; fi
PROXY_IP="${PROXY_IP:-192.168.10.10}"
DB_NAME="${DB_NAME:-pusat}"
DB_USER="${DB_USER:-pusat}"
ADMIN_USER="${ADMIN_USER:-admin}"
SKIP_DB_INSTALL="${SKIP_DB_INSTALL:-0}"

info() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
gagal() { printf '\n\033[1;31mGAGAL: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || gagal "Jalankan dengan sudo: sudo bash deploy/install.sh"
command -v apt-get >/dev/null || gagal "Skrip ini untuk Ubuntu/Debian (butuh apt)."
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[ -f "$APP_DIR/artisan" ] && [ -d "$APP_DIR/vendor" ] || gagal "Jalankan dari folder hasil git clone (yang berisi artisan dan vendor/)."
# Kode milik user yang meng-clone (git pull & update.sh berjalan sebagai dia); web server lewat grup www-data.
OWNER="$(stat -c %U "$APP_DIR")"
[ "$OWNER" != "root" ] || gagal "Folder $APP_DIR milik root. Clone ulang TANPA sudo, atau: sudo chown -R \$USER:\$USER $APP_DIR"
export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------- 1. Paket
info "Memasang paket (nginx, PHP, MariaDB, cron)"
apt-get update -y
PAKET=(nginx git unzip curl openssl ca-certificates cron
       php-fpm php-cli php-mysql php-sqlite3 php-mbstring php-xml php-curl php-zip php-bcmath php-intl)
[ "$SKIP_DB_INSTALL" = "1" ] || PAKET+=(mariadb-server)
apt-get install -y "${PAKET[@]}"

PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    || gagal "PHP $PHPV terlalu lama; butuh PHP 8.3+. Pakai Ubuntu 24.04/26.04 atau Debian 13."
info "PHP $PHPV terdeteksi"
cat > "/etc/php/${PHPV}/fpm/conf.d/99-${NAMA}.ini" <<INI
upload_max_filesize = 10M
post_max_size = 12M
expose_php = Off
INI
systemctl enable --now "php${PHPV}-fpm"
systemctl restart "php${PHPV}-fpm"

# ---------------------------------------------------------------- 2. Izin folder
cd "$APP_DIR"
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/private bootstrap/cache
chown -R "$OWNER":www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} +

artisan() { runuser -u www-data -- php "$APP_DIR/artisan" "$@"; }

# ---------------------------------------------------------------- 3. Database + .env
if [ -f .env ]; then
    info ".env sudah ada: database dan .env tidak diubah"
    NEW_INSTALL=0
else
    NEW_INSTALL=1
    DB_PASS="$(openssl rand -hex 12)"
    info "Membuat database '$DB_NAME' dan user '$DB_USER'"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    info "Menulis .env"
    cp .env.example .env
    sed -i \
        -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
        -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
        -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
        -e "s|^TRUSTED_PROXIES=.*|TRUSTED_PROXIES=${PROXY_IP}|" .env
    case "$APP_URL" in
        https://*) sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' .env ;;
        *)         sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|' .env; echo "APP_FORCE_HTTPS=false" >> .env ;;
    esac
    php artisan key:generate --force          # sebagai root: .env belum dikunci
    chown "$OWNER":www-data .env
    chmod 640 .env
fi

info "Migrasi database"
if [ "$NEW_INSTALL" = "1" ]; then
    artisan migrate --seed --force            # tahun ajaran aktif + jurusan awal, tanpa akun
else
    artisan migrate --force
fi
artisan config:cache >/dev/null
artisan route:cache >/dev/null
artisan view:cache >/dev/null

# ---------------------------------------------------------------- 4. Nginx
info "Menyiapkan Nginx"
SITE="/etc/nginx/sites-available/${NAMA}"
sed -e "s#pusat.smkibupakusari.sch.id#${DOMAIN}#" \
    -e "s#/var/www/pusat-sekolah#${APP_DIR}#" \
    -e "s#php8.5-fpm.sock#php${PHPV}-fpm.sock#" deploy/nginx-pusat.conf > "$SITE"
if [ "$DOMAIN" = "_" ]; then sed -i 's#listen 80;#listen 80 default_server;#; s#listen \[::\]:80;#listen [::]:80 default_server;#' "$SITE"; fi
ln -sf "$SITE" "/etc/nginx/sites-enabled/${NAMA}"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx
systemctl reload nginx

# ---------------------------------------------------------------- 5. Penjadwal & backup
# WAJIB: tanpa schedule:run, perubahan data tidak terkirim (webhook) ke aplikasi lain.
# Bila dulu dipasang lewat "crontab -u www-data -e", baris itu dihapus agar tidak berjalan ganda.
if crontab -u www-data -l 2>/dev/null | grep -q 'schedule:run'; then
    crontab -u www-data -l | grep -v 'schedule:run' | crontab -u www-data - || true
fi
echo "* * * * * www-data cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > "/etc/cron.d/${NAMA}"
chmod 644 "/etc/cron.d/${NAMA}"
# Backup harian 01:15, simpan 14 hari di /var/backups/pusat. Tidak menimpa jadwal yang sudah diatur.
if [ ! -f /etc/cron.d/pusat-backup ]; then
    echo "15 1 * * * root bash $APP_DIR/deploy/backup.sh >> /var/log/pusat-backup.log 2>&1" > /etc/cron.d/pusat-backup
    chmod 644 /etc/cron.d/pusat-backup
fi
systemctl enable --now cron >/dev/null 2>&1 || true

# ---------------------------------------------------------------- 6. Superadmin pertama
if [ "$NEW_INSTALL" = "1" ]; then
    if [ -t 0 ]; then
        info "Membuat superadmin '$ADMIN_USER'"
        artisan pusat:buat-admin "$ADMIN_USER" --nama="Administrator" \
            || echo "Superadmin belum dibuat; ulangi: sudo -u www-data php artisan pusat:buat-admin $ADMIN_USER"
    else
        echo "Superadmin belum dibuat (tanpa terminal). Jalankan: sudo -u www-data php artisan pusat:buat-admin $ADMIN_USER"
    fi
fi

chown -R "$OWNER":www-data storage bootstrap/cache
artisan pusat:periksa || true

# ---------------------------------------------------------------- 7. Selesai
info "Selesai"
cat <<RINGKAS

  Aplikasi : ${APP_URL}   (sementara http://${IP}/)
  Folder   : ${APP_DIR}  (milik ${OWNER})
RINGKAS
if [ "$NEW_INSTALL" = "1" ]; then
    printf '  Database : %s / user %s (password ada di %s/.env)\n' "$DB_NAME" "$DB_USER" "$APP_DIR"
fi
cat <<LANJUT

  Berikutnya (rincian: docs/PENYIAPAN-VM.md):
   1. NPMplus: Proxy Host ${APP_URL#*://} -> ${IP}:80, SSL + Force SSL; DNS bila belum ada
   2. Pasang ulang setelah VM rusak? Kembalikan APP_KEY dari .env lama (tanpa itu kunci sambungan
      tidak terbaca), lalu pulihkan database terakhir:  gunzip < pusat_TANGGAL.sql.gz | sudo mysql ${DB_NAME}
      lalu: sudo -u www-data php artisan config:cache
   3. Daftarkan aplikasi: sudo -u www-data php artisan pusat:aplikasi-baru <kode> --base-url=https://...
   Memperbarui nanti: cd ${APP_DIR} && sudo bash deploy/update.sh
LANJUT
