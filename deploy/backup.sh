#!/usr/bin/env bash
# Backup database Pusat (membaca DB_* dari .env). Simpan 14 hari di /var/backups/pusat.
set -euo pipefail
APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_get() { grep -E "^$1=" "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"'"'"; }
DEST="${DEST:-/var/backups/pusat}"; mkdir -p "$DEST"; chmod 700 "$DEST"
F="$DEST/pusat_$(date +%F_%H%M).sql.gz"
MYSQL_PWD="$(env_get DB_PASSWORD)" mysqldump --single-transaction -h "$(env_get DB_HOST)" -u "$(env_get DB_USERNAME)" "$(env_get DB_DATABASE)" | gzip > "$F"
chmod 600 "$F"; find "$DEST" -name 'pusat_*.sql.gz' -mtime +14 -delete
echo "Backup: $F"
