#!/bin/bash
# Parayok - Database Backup
# Cron: 0 2 * * * /var/www/html/parayok/backend/scripts/backup.sh

set -e

PROJECT_DIR="/var/www/html/parayok/backend"
BACKUP_DIR="/var/backups/parayok"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7
LOG="/var/log/backup.log"

# .env'den DB bilgilerini çek
DB_NAME=$(grep -oP '^DB_DATABASE=\K.*' "$PROJECT_DIR/.env")
DB_USER=$(grep -oP '^DB_USERNAME=\K.*' "$PROJECT_DIR/.env")
DB_PASS=$(grep -oP '^DB_PASSWORD=\K.*' "$PROJECT_DIR/.env")

mkdir -p "$BACKUP_DIR"

echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting backup..." >> "$LOG"

# Backup
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

if [ $? -eq 0 ]; then
    SIZE=$(du -h "$BACKUP_DIR/db_$DATE.sql.gz" | cut -f1)
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Backup OK: db_$DATE.sql.gz ($SIZE)" >> "$LOG"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Backup failed" >> "$LOG"
    exit 1
fi

# Eski backup'ları temizle
find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +"$RETENTION_DAYS" -delete
echo "$(date '+%Y-%m-%d %H:%M:%S') - Cleanup done" >> "$LOG"
