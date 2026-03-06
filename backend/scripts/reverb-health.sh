#!/bin/bash

# Reverb Health Check Script
# Run every 5 minutes via cron: */5 * * * * /var/www/html/parayok/backend/scripts/reverb-health.sh >> /var/log/reverb-health.log 2>&1

LOG_FILE="/var/log/reverb-health.log"
PROJECT_DIR="/var/www/html/parayok/backend"

echo "$(date '+%Y-%m-%d %H:%M:%S') - Checking Reverb status..." >> $LOG_FILE

# Check if supervisorctl is available
if ! command -v supervisorctl &> /dev/null; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: supervisorctl not found" >> $LOG_FILE
    exit 1
fi

# Check Reverb process status
STATUS=$(supervisorctl status parayok-reverb 2>/dev/null || echo "NOT_FOUND")

if echo "$STATUS" | grep -q "RUNNING"; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Reverb is RUNNING" >> $LOG_FILE
    exit 0
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - WARNING: Reverb status: $STATUS" >> $LOG_FILE
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Restarting Reverb..." >> $LOG_FILE

    supervisorctl restart parayok-reverb

    sleep 5

    # Verify restart
    NEW_STATUS=$(supervisorctl status parayok-reverb 2>/dev/null || echo "ERROR")
    if echo "$NEW_STATUS" | grep -q "RUNNING"; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - SUCCESS: Reverb restarted successfully" >> $LOG_FILE
    else
        echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Failed to restart Reverb. Status: $NEW_STATUS" >> $LOG_FILE
    fi
fi
