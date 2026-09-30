#!/bin/bash
# Parayok - Cron Jobs Setup (Debian 13)
# Run as: sudo bash scripts/setup-crons.sh

set -e
echo "=== Setting up Cron Jobs for Parayok ==="

touch /var/log/reverb-health.log /var/log/backup.log
chmod 600 /var/log/reverb-health.log /var/log/backup.log

(crontab -u www-data -l 2>/dev/null || true; echo "* * * * * cd /var/www/html/parayok/backend && php artisan schedule:run >> /dev/null 2>&1") | sort -u | crontab -u www-data -

(crontab -l 2>/dev/null || true; echo "*/5 * * * * bash /var/www/html/parayok/backend/scripts/reverb-health.sh >> /var/log/reverb-health.log 2>&1"; echo "0 2 * * * bash /var/www/html/parayok/backend/scripts/backup.sh >> /var/log/backup.log 2>&1") | grep -v 'artisan schedule:run' | sort -u | crontab -

echo ""
echo "Root cron jobs:"
crontab -l
echo "www-data cron jobs:"
crontab -u www-data -l

echo ""
echo "=== Cron Setup Complete ==="
