#!/usr/bin/env bash
# Perbarui Pusat Sekolah:  cd /var/www/pusat-sekolah && sudo bash deploy/update.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Jalankan dengan sudo." >&2; exit 1; }
APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$APP"
[ -f .env ] || { echo ".env tidak ada" >&2; exit 1; }
OWNER="$(stat -c %U "$APP")"; as_owner() { sudo -u "$OWNER" -H "$@"; }
trap 'php artisan up >/dev/null 2>&1 || true' EXIT
echo "==> Backup database"; bash deploy/backup.sh
php artisan down --retry=30 >/dev/null 2>&1 || true
echo "==> git pull + composer"; as_owner git ls-files -m -- vendor | as_owner xargs -r git checkout --; as_owner git pull --ff-only; as_owner composer install --no-dev --optimize-autoloader --no-interaction
echo "==> migrate"; php artisan migrate --force
php artisan optimize:clear; php artisan config:cache; php artisan route:cache; php artisan view:cache
chown -R "$OWNER":www-data storage bootstrap/cache; chmod -R ug+rwX storage bootstrap/cache
systemctl reload "$(systemctl list-units --type=service --no-legend 'php*-fpm.service' | awk 'NR==1{print $1}')" || true
sudo -u "$OWNER" -H php artisan pusat:periksa || echo "PERHATIAN: ada masalah di atas — perbaiki sebelum dipakai."
# Jadwal backup harian 01:15 (dipasang sekali; tidak menimpa jadwal yang sudah diatur)
if [ ! -f /etc/cron.d/pusat-backup ]; then
    echo "15 1 * * * root bash $APP/deploy/backup.sh >> /var/log/pusat-backup.log 2>&1" > /etc/cron.d/pusat-backup
    chmod 644 /etc/cron.d/pusat-backup; echo "==> Jadwal backup harian dipasang (/etc/cron.d/pusat-backup)"
fi
echo "Selesai."
