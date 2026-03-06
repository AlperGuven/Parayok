#!/bin/bash

# Database Backup Script
# Run daily via cron: 0 2 * * * /var/www/html/parayok/backend/scripts/backup.sh >> /var/log/backup.log 2>&1

set -e

# Configuration
DB_NAME="parayok"
DB_USER="root"
DB_PASS=""
BACKUP_DIR="/var/backups/parayok"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Create backup directory if not exists
mkdir -p $BACKUP_DIR

echo "$(date '+%Y-%m-%d %H:%M:%S') - Starting backup..." >> /var/log/backup.log

# Perform backup
if [ -z "$DB_PASS" ]; then
    mysqldump -u $DB_USER $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz
else
    mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz
fi

# Check if backup was successful
if [ $? -eq 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Backup completed: db_$DATE.sql.gz" >> /var/log/backup.log
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Backup failed" >> /var/log/backup.log
    exit 1
fi

# Remove old backups (older than RETENTION_DAYS)
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "$(date '+%Y-%m-%d %H:%M:%S') - Old backups cleaned up" >> /var/log/backup.log

# Show backup size
du -h $BACKUP_DIR/db_$DATE.sql.gz >> /var/log/backup.log

echo "$(date '+%Y-%m-%d %H:%M:%S') - Backup process completed" >> /var/log/backup.log
