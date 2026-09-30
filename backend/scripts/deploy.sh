#!/bin/bash
# Run as root on the Debian 13 VPS after provisioning the environment files.
set -e

PROJECT_DIR="/var/www/html/parayok"
BACKEND_DIR="$PROJECT_DIR/backend"
FRONTEND_DIR="$PROJECT_DIR/frontend"

if [ "$EUID" -ne 0 ]; then
    echo "Run with sudo" >&2
    exit 1
fi

on_error() {
    echo "Deploy failed at line $1; leaving maintenance mode" >&2
    (cd "$BACKEND_DIR" && sudo -u www-data php artisan up) || true
    exit 1
}
trap 'on_error $LINENO' ERR

cd "$BACKEND_DIR"
sudo -u www-data php artisan down

cd "$PROJECT_DIR"
sudo -u www-data git pull --ff-only origin main

cd "$BACKEND_DIR"
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

cd "$FRONTEND_DIR"
sudo -u www-data npm ci
sudo -u www-data npm run build
cp -r dist/. "$BACKEND_DIR/public/"
chown -R www-data:www-data "$BACKEND_DIR/public/"

cd "$BACKEND_DIR"
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
sudo -u www-data php artisan queue:restart

supervisorctl restart parayok-reverb
supervisorctl restart 'parayok-worker:*'
systemctl reload php8.4-fpm
systemctl reload nginx

sudo -u www-data php artisan up
trap - ERR
supervisorctl status
