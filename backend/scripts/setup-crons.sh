# Parayok - Cron Jobs Setup
# Run as: sudo bash scripts/setup-crons.sh

echo "=== Setting up Cron Jobs for Parayok ==="

# Create log files
touch /var/log/reverb-health.log
touch /var/log/backup.log
chmod 666 /var/log/reverb-health.log
chmod 666 /var/log/backup.log

# Add cron jobs
(crontab -l 2>/dev/null || true; echo "*/5 * * * * /var/www/html/parayok/backend/scripts/reverb-health.sh >> /var/log/reverb-health.log 2>&1") | crontab -
(crontab -l 2>/dev/null || true; echo "0 2 * * * /var/www/html/parayok/backend/scripts/backup.sh >> /var/log/backup.log 2>&1") | crontab -

# List cron jobs
echo ""
echo "Current cron jobs:"
crontab -l

echo ""
echo "=== Cron Setup Complete ==="
