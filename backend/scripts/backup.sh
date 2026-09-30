#!/bin/bash
# Parayok - Database Backup (Debian 13 / MariaDB)
# Cron: 0 2 * * * /var/www/html/parayok/backend/scripts/backup.sh

set -uo pipefail

PROJECT_DIR="/var/www/html/parayok/backend"
BACKUP_DIR="/var/backups/parayok"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7
LOG="/var/log/backup.log"
FILE="$BACKUP_DIR/db_$DATE.sql.gz"

env_get() { grep -oP "^$1=\K.*" "$PROJECT_DIR/.env" | sed -e 's/^"//' -e 's/"$//'; }
DB_NAME=$(env_get DB_DATABASE)
DB_USER=$(env_get DB_USERNAME)
MYSQL_PWD=$(env_get DB_PASSWORD)
export MYSQL_PWD

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting backup..." >> "$LOG"

if mariadb-dump --single-transaction --quick --routines -u "$DB_USER" "$DB_NAME" | gzip > "$FILE"; then
    SIZE=$(du -h "$FILE" | cut -f1)
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Backup OK: db_$DATE.sql.gz ($SIZE)" >> "$LOG"
else
    rm -f "$FILE"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Backup failed" >> "$LOG"
    exit 1
fi

# Eski backup'ları temizle
find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +"$RETENTION_DAYS" -delete
echo "$(date '+%Y-%m-%d %H:%M:%S') - Cleanup done" >> "$LOG"
