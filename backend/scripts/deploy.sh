#!/bin/bash
# Parayok - Deployment Script
# Kullanım: sudo bash /var/www/html/parayok/backend/scripts/deploy.sh

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

PROJECT_DIR="/var/www/html/parayok/backend"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}sudo ile çalıştır${NC}"
    exit 1
fi

cd "$PROJECT_DIR"

echo -e "${GREEN}[1/9] Pulling latest code...${NC}"
sudo -u www-data git pull origin main

echo -e "${GREEN}[2/9] Installing PHP dependencies...${NC}"
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

echo -e "${GREEN}[3/9] Installing & building frontend...${NC}"
sudo -u www-data npm ci
sudo -u www-data npm run build

echo -e "${GREEN}[4/9] Running migrations...${NC}"
sudo -u www-data php artisan migrate --force

echo -e "${GREEN}[5/9] Caching config...${NC}"
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

echo -e "${GREEN}[6/9] Restarting queue workers...${NC}"
sudo -u www-data php artisan queue:restart

echo -e "${GREEN}[7/9] Restarting Reverb...${NC}"
supervisorctl restart parayok-reverb

echo -e "${GREEN}[8/9] Restarting PHP-FPM...${NC}"
systemctl restart php8.3-fpm

echo -e "${GREEN}[9/9] Reloading Nginx...${NC}"
systemctl reload nginx

echo ""
echo -e "${GREEN}=== Deploy Complete! ===${NC}"
echo ""
echo "Services:"
supervisorctl status
echo ""
echo "Memory:"
free -h
echo ""
echo "Disk:"
df -h /
