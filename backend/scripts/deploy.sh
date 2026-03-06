#!/bin/bash

# Parayok - Deployment Script for Single Server (1000 users)
# Run as: sudo bash scripts/deploy.sh

set -e

echo "=== Parayok Deployment Script ==="
echo "Target: 1000 concurrent users"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${YELLOW}Please run as root or with sudo${NC}"
    exit 1
fi

# Navigate to project directory
cd /var/www/html/parayok/backend

# Optimize server limits
echo -e "${GREEN}[1/8] Optimizing server limits...${NC}"
bash ../scripts/optimize-server.sh

# Install Redis if not installed
echo -e "${GREEN}[2/8] Checking Redis...${NC}"
systemctl status redis-server > /dev/null 2>&1 || apt-get install -y redis-server

# Install Supervisor if not installed
echo -e "${GREEN}[3/8] Checking Supervisor...${NC}"
systemctl status supervisor > /dev/null 2>&1 || apt-get install -y supervisor

# Copy Supervisor config
echo -e "${GREEN}[4/8] Configuring Supervisor...${NC}"
cp storage/supervisor.conf /etc/supervisor/conf.d/parayok.conf
supervisorctl reread
supervisorctl update

# Setup cron jobs
echo -e "${GREEN}[5/8] Setting up cron jobs...${NC}"
bash ../scripts/setup-crons.sh

# Run MySQL performance optimizations
echo -e "${GREEN}[6/8] Running MySQL optimizations...${NC}"
mysql -u root -p < database/performance.sql 2>/dev/null || echo "Skipping MySQL optimization (may require password)"

# Install Composer dependencies
echo -e "${GREEN}[7/8] Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader

# Run migrations
echo -e "${GREEN}[8/8] Running migrations...${NC}"
php artisan migrate --force

# Clear and rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start services
echo -e "${GREEN}Starting services...${NC}"
supervisorctl start parayok-worker
supervisorctl start parayok-reverb

# Restart PHP-FPM
systemctl restart php*-fpm

# Restart Nginx
systemctl restart nginx

echo ""
echo -e "${GREEN}=== Deployment Complete! ===${NC}"
echo ""
echo "Services:"
supervisorctl status
echo ""
echo "Memory usage:"
free -h
echo ""
echo "Open files limit:"
ulimit -n
