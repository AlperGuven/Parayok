# Parayok (Sprint Poker) — VPS Deploy Rehberi
## Sıfırdan Ubuntu 24.04 | 1 vCPU / 4GB RAM

---

## Ön Bilgi

- **Proje yapısı:** Laravel 11 + Vite + Vue 3 monolith (tek repo)
- **Proje dizini:** `/var/www/html/parayok/backend/`
- **Kod kaynağı:** GitHub
- **Domain:** Henüz yok — önce IP üzerinden çalıştıracağız, sonra domain ekleyeceğiz
- **Sunucu:** Hostinger VPS, 1 vCPU / 4GB RAM, Ubuntu 24.04
- **Docker:** Kaldırıldı, her şey doğrudan OS üzerinde çalışacak

---

## ADIM 1 — Sistemi Güncelle ve Temel Araçları Kur

```bash
ssh root@VPS_IP

apt update && apt upgrade -y

# Temel araçlar
apt install -y curl wget git unzip software-properties-common \
    acl htop net-tools
```

---

## ADIM 2 — PHP 8.3 Kurulumu

```bash
# PHP PPA ekle
add-apt-repository ppa:ondrej/php -y
apt update

# PHP 8.3 + tüm gerekli extension'lar
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-intl php8.3-readline \
    php8.3-tokenizer php8.3-pcntl php8.3-redis php8.3-gd

# Doğrula
php -v
# PHP 8.3.x çıkmalı
```

### PHP-FPM Ayarları (1 vCPU / 4GB RAM için optimize)

```bash
nano /etc/php/8.3/fpm/pool.d/www.conf
```

Şu satırları bul ve değiştir:

```ini
user = www-data
group = www-data

pm = dynamic
pm.max_children = 15
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500

; Request timeout
request_terminate_timeout = 300

; File limit
rlimit_files = 65535
```

> **Neden 15?** 1 vCPU'da her PHP-FPM child ~30-40MB RAM kullanır.
> 15 × 40MB = 600MB. Geriye MySQL, Redis, Reverb, Nginx için bol yer kalır.
> Eski script'inde 50 vardı — o 1000 eşzamanlı bağlantı hedefi içindi ve
> çok CPU'lu sunucu gerektiriyordu. 1 vCPU'da 50 child context switch'ten ölür.

```bash
systemctl restart php8.3-fpm
systemctl enable php8.3-fpm
```

---

## ADIM 3 — MySQL 8 Kurulumu

```bash
apt install -y mysql-server
systemctl enable mysql

# Güvenlik ayarları
mysql_secure_installation
# Root şifresi belirle, anonymous user sil, remote root kapat
```

### Veritabanı Oluştur

```bash
mysql -u root -p
```

```sql
CREATE DATABASE parayok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'parayok'@'localhost' IDENTIFIED BY 'BURAYA_GUCLU_SIFRE';
GRANT ALL PRIVILEGES ON parayok.* TO 'parayok'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### MySQL Performans Ayarları (1 vCPU / 4GB RAM)

```bash
nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

`[mysqld]` bölümüne ekle:

```ini
# Buffer — RAM'in ~%15'i
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M

# Bağlantı limiti
max_connections = 60

# Slow query log — debug için önemli
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

```bash
systemctl restart mysql
```

---

## ADIM 4 — Redis Kurulumu

```bash
apt install -y redis-server
systemctl enable redis-server

# Bellek limiti ayarla
nano /etc/redis/redis.conf
```

Şu satırları bul/ekle:

```ini
maxmemory 256mb
maxmemory-policy allkeys-lru
```

```bash
systemctl restart redis-server

# Test
redis-cli ping
# PONG dönmeli
```

---

## ADIM 5 — Nginx Kurulumu

```bash
apt install -y nginx
systemctl enable nginx
```

### Site Config (başlangıçta IP ile — domain'siz)

```bash
nano /etc/nginx/sites-available/parayok
```

```nginx
server {
    listen 80;
    server_name _;    # tüm istekleri kabul et (domain gelince değişecek)

    root /var/www/html/parayok/backend/public;
    index index.php;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml image/svg+xml;
    gzip_min_length 1024;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Vite build assets
    location /build {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # WebSocket — Reverb proxy
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }

    location /apps {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Gizli dosyaları engelle
    location ~ /\.(?!well-known) {
        deny all;
    }

    # storage ve vendor'a erişimi engelle
    location ~ ^/(storage|vendor) {
        deny all;
    }
}
```

### Nginx Performans (1 vCPU)

```bash
nano /etc/nginx/nginx.conf
```

Üst bölümü şöyle ayarla:

```nginx
user www-data;
worker_processes 1;          # 1 vCPU = 1 worker
worker_rlimit_nofile 65535;
pid /run/nginx.pid;

events {
    worker_connections 4096;  # 1 vCPU için yeterli
    multi_accept on;
    use epoll;
}
```

```bash
# Default site'ı kaldır, parayok'u aktif et
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/parayok /etc/nginx/sites-enabled/

nginx -t       # syntax kontrolü
systemctl restart nginx
```

---

## ADIM 6 — Node.js 20 LTS

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Doğrula
node -v    # v20.x
npm -v     # 10.x
```

---

## ADIM 7 — Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Doğrula
composer -V
```

---

## ADIM 8 — Supervisor

```bash
apt install -y supervisor
systemctl enable supervisor
```

---

## ADIM 9 — Proje Dizini ve GitHub'dan Kod Çekme

### 9.1 Dizin hazırla

```bash
mkdir -p /var/www/html/parayok
chown -R www-data:www-data /var/www/html/parayok
```

### 9.2 SSH key oluştur (GitHub için)

```bash
ssh-keygen -t ed25519 -C "parayok-vps"
# Enter'a bas, şifresiz bırak

cat ~/.ssh/id_ed25519.pub
# Çıkan key'i kopyala
```

GitHub'da: **Repo → Settings → Deploy keys → Add deploy key** → yapıştır, "Allow write access" tikle.

### 9.3 Kodu çek

```bash
cd /var/www/html/parayok
git clone git@github.com:KULLANICI/REPO_ADI.git backend
```

> **Not:** Monolith yapıda Laravel projesinin tamamı `backend/` altında.
> Vue dosyaları `backend/resources/js/` içinde.

### 9.4 İzinler

```bash
chown -R www-data:www-data /var/www/html/parayok/backend
chmod -R 775 /var/www/html/parayok/backend/storage
chmod -R 775 /var/www/html/parayok/backend/bootstrap/cache
```

---

## ADIM 10 — Laravel Kurulum

```bash
cd /var/www/html/parayok/backend

# PHP bağımlılıkları
composer install --no-dev --optimize-autoloader

# .env dosyası
cp .env.example .env
nano .env
```

### .env İçeriği

```env
APP_NAME="Parayok"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://VPS_IP_ADRESI

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parayok
DB_USERNAME=parayok
DB_PASSWORD=BURAYA_GUCLU_SIFRE

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Session & Cache & Queue
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Jira OAuth
JIRA_CLIENT_ID=atlassian_client_id_buraya
JIRA_CLIENT_SECRET=atlassian_client_secret_buraya
JIRA_REDIRECT_URI=http://VPS_IP_ADRESI/auth/jira/callback

# WebSocket (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=parayok
REVERB_APP_KEY=RANDOM_KEY_OLUSTUR_32CHAR
REVERB_APP_SECRET=RANDOM_SECRET_OLUSTUR_32CHAR
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Frontend Reverb bağlantısı (IP ile başlangıç)
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=VPS_IP_ADRESI
VITE_REVERB_PORT=80
VITE_REVERB_SCHEME=http
```

**Reverb key'leri oluşturmak için:**
```bash
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
# İki kez çalıştır — biri KEY, biri SECRET
```

### Laravel Setup

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## ADIM 11 — Frontend Build

```bash
cd /var/www/html/parayok/backend

npm install
npm run build

# build output: public/build/ altında olacak
```

---

## ADIM 12 — Supervisor Config

```bash
nano /etc/supervisor/conf.d/parayok.conf
```

```ini
;------------------------------------------
; Laravel Reverb — WebSocket Server
;------------------------------------------
[program:parayok-reverb]
process_name=%(program_name)s
numprocs=1
directory=/var/www/html/parayok/backend
command=php artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/parayok/backend/storage/logs/reverb.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=3600

;------------------------------------------
; Laravel Queue Worker — Jira sync vs.
;------------------------------------------
[program:parayok-worker]
process_name=%(program_name)s_%(process_num)02d
numprocs=2
directory=/var/www/html/parayok/backend
command=php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/parayok/backend/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start all
supervisorctl status
# parayok-reverb      RUNNING
# parayok-worker:00   RUNNING
# parayok-worker:01   RUNNING
```

---

## ADIM 13 — Script'leri Yerleştir ve Güncelle

Script'lerin zaten var. Onları `scripts/` dizinine koy ve çalıştırılabilir yap:

```bash
mkdir -p /var/www/html/parayok/backend/scripts
# scripts/ zaten repo'da varsa bu adımı atla

chmod +x /var/www/html/parayok/backend/scripts/*.sh
```

### optimize-server.sh — Güncellenmiş (1 vCPU / 4GB uyumlu)

Mevcut script'inde bir sorun var: her çalıştığında `limits.conf` ve `sysctl.conf`'a duplicate satır ekliyor. Şu versiyonla değiştir:

```bash
nano /var/www/html/parayok/backend/scripts/optimize-server.sh
```

```bash
#!/bin/bash
# Parayok - Server Optimization (1 vCPU / 4GB RAM)
# Tek seferlik çalıştır: sudo bash scripts/optimize-server.sh

set -e
echo "=== Optimizing System Limits for Parayok ==="

# ---- Open File Limits (duplicate'siz) ----
LIMITS_FILE="/etc/security/limits.conf"
MARKER="# parayok-limits"

if ! grep -q "$MARKER" "$LIMITS_FILE"; then
    cat >> "$LIMITS_FILE" << EOF

$MARKER
* soft nofile 65535
* hard nofile 65535
root soft nofile 65535
root hard nofile 65535
www-data soft nofile 65535
www-data hard nofile 65535
EOF
    echo "File limits added"
else
    echo "File limits already configured, skipping"
fi

# ---- Sysctl (duplicate'siz) ----
SYSCTL_FILE="/etc/sysctl.conf"
SYSCTL_MARKER="# parayok-sysctl"

if ! grep -q "$SYSCTL_MARKER" "$SYSCTL_FILE"; then
    cat >> "$SYSCTL_FILE" << EOF

$SYSCTL_MARKER
fs.file-max = 65535
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_tw_reuse = 1
net.ipv4.ip_local_port_range = 1024 65535
EOF
    echo "Sysctl optimizations added"
else
    echo "Sysctl already configured, skipping"
fi

sysctl -p

# ---- PHP-FPM (1 vCPU / 4GB) ----
FPM_CONF="/etc/php/8.3/fpm/pool.d/www.conf"
if [ -f "$FPM_CONF" ]; then
    sed -i 's/^pm.max_children = .*/pm.max_children = 15/' "$FPM_CONF"
    sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/' "$FPM_CONF"
    sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$FPM_CONF"
    sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' "$FPM_CONF"
    sed -i 's/^;*rlimit_files = .*/rlimit_files = 65535/' "$FPM_CONF"
    systemctl restart php8.3-fpm
    echo "PHP-FPM optimized for 1 vCPU / 4GB"
fi

# ---- Nginx (1 vCPU) ----
NGINX_CONF="/etc/nginx/nginx.conf"
if [ -f "$NGINX_CONF" ]; then
    sed -i 's/worker_connections .*/worker_connections 4096;/' "$NGINX_CONF"
    # worker_rlimit_nofile varsa güncelle, yoksa ekle
    if grep -q "worker_rlimit_nofile" "$NGINX_CONF"; then
        sed -i 's/worker_rlimit_nofile .*/worker_rlimit_nofile 65535;/' "$NGINX_CONF"
    else
        sed -i '/worker_processes/a worker_rlimit_nofile 65535;' "$NGINX_CONF"
    fi
    nginx -t && systemctl restart nginx
    echo "Nginx optimized"
fi

echo "=== Optimization Complete ==="
echo "Reboot recommended: sudo reboot"
```

### backup.sh — Güncelle (şifre .env'den çeksin)

```bash
nano /var/www/html/parayok/backend/scripts/backup.sh
```

```bash
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
```

### deploy.sh — Güncellenmiş (git pull destekli)

```bash
nano /var/www/html/parayok/backend/scripts/deploy.sh
```

```bash
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
```

### setup-crons.sh — Aynı, değişiklik gerekmiyor

Mevcut haliyle çalışır. Sadece `chmod +x` olduğundan emin ol.

### reverb-health.sh — Aynı, değişiklik gerekmiyor

Mevcut haliyle çalışır.

---

## ADIM 14 — İlk Deploy'u Çalıştır

```bash
# Önce optimize et (tek seferlik)
sudo bash /var/www/html/parayok/backend/scripts/optimize-server.sh
sudo reboot

# Reboot sonrası SSH ile tekrar bağlan
sudo bash /var/www/html/parayok/backend/scripts/setup-crons.sh
```

---

## ADIM 15 — Doğrulama

```bash
# 1. Site açılıyor mu?
curl -I http://VPS_IP_ADRESI
# HTTP 200 dönmeli

# 2. Servisler çalışıyor mu?
supervisorctl status
# parayok-reverb     RUNNING
# parayok-worker:00  RUNNING
# parayok-worker:01  RUNNING

# 3. WebSocket çalışıyor mu?
curl -i --no-buffer \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGVzdA==" \
  http://127.0.0.1:8080/app/REVERB_APP_KEY
# 101 Switching Protocols dönmeli

# 4. Redis
redis-cli ping    # PONG

# 5. MySQL
mysql -u parayok -p -e "SHOW DATABASES;"

# 6. Log'lar
tail -f /var/www/html/parayok/backend/storage/logs/laravel.log
tail -f /var/www/html/parayok/backend/storage/logs/reverb.log

# 7. Kaynak kullanımı
htop
```

---

## ADIM 16 — Güvenlik (Firewall)

```bash
ufw allow 22/tcp     # SSH
ufw allow 80/tcp     # HTTP
# 443 domain + SSL ekleyince açacaksın
ufw enable
ufw status

# 8080 portu AÇMA — Nginx üzerinden proxy ediliyor
```

---

## SONRA YAPILACAKLAR — Domain Ekleme

Domain/subdomain hazır olduğunda:

```bash
# 1. DNS'de A kaydı ekle
# poker.senindomain.com → VPS_IP_ADRESI

# 2. Certbot kur ve SSL al
apt install -y certbot python3-certbot-nginx
certbot --nginx -d poker.senindomain.com

# 3. Nginx config'de server_name güncelle
nano /etc/nginx/sites-available/parayok
# server_name _; → server_name poker.senindomain.com;

# 4. .env güncelle
# APP_URL=https://poker.senindomain.com
# JIRA_REDIRECT_URI=https://poker.senindomain.com/auth/jira/callback
# VITE_REVERB_HOST=poker.senindomain.com
# VITE_REVERB_PORT=443
# VITE_REVERB_SCHEME=https

# 5. Frontend'i tekrar build et (VITE_ değişkenleri değişti)
cd /var/www/html/parayok/backend
npm run build
php artisan config:cache

# 6. ufw'de 443 aç
ufw allow 443/tcp

# 7. Atlassian Developer Console'da callback URL güncelle
# https://poker.senindomain.com/auth/jira/callback

# 8. Nginx reload
systemctl reload nginx
```

---

## Kaynak Tüketimi Özeti (1 vCPU / 4GB RAM)

| Servis | RAM | CPU |
|--------|-----|-----|
| Nginx (1 worker) | ~30 MB | Düşük |
| PHP-FPM (15 child) | ~450 MB | Orta |
| MySQL | ~600 MB | Orta |
| Redis (256MB limit) | ~100 MB | Düşük |
| Reverb (WebSocket) | ~100 MB | Düşük-Orta |
| Queue Worker (×2) | ~120 MB | Düşük |
| OS + Buffer | ~600 MB | — |
| **Toplam** | **~2 GB** | — |
| **Kalan** | **~2 GB boş** | — |

50-100 eşzamanlı kullanıcı için rahat yeterli.

---

## Hızlı Referans — Günlük Komutlar

```bash
# Deploy
sudo bash /var/www/html/parayok/backend/scripts/deploy.sh

# Servisleri kontrol et
supervisorctl status

# Reverb restart
supervisorctl restart parayok-reverb

# Queue restart
php artisan queue:restart

# Log'lar
tail -f /var/www/html/parayok/backend/storage/logs/laravel.log
tail -f /var/www/html/parayok/backend/storage/logs/reverb.log
tail -f /var/www/html/parayok/backend/storage/logs/worker.log

# Kaynak kullanımı
htop
free -h
df -h

# Manuel backup
sudo bash /var/www/html/parayok/backend/scripts/backup.sh
```
