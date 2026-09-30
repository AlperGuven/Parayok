#!/bin/bash
# Parayok - Server Optimization (Debian 13 / 2 cores / 4GB RAM)
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

# Debian 13 loads persistent tuning from sysctl.d.
cat > /etc/sysctl.d/99-parayok.conf << EOF
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_tw_reuse = 1
net.ipv4.ip_local_port_range = 10240 65535
EOF
sysctl --system > /dev/null

# ---- PHP-FPM (2 cores / 4GB) ----
FPM_CONF="/etc/php/8.4/fpm/pool.d/www.conf"
if [ -f "$FPM_CONF" ]; then
    sed -i 's/^pm.max_children = .*/pm.max_children = 15/' "$FPM_CONF"
    sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/' "$FPM_CONF"
    sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' "$FPM_CONF"
    sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' "$FPM_CONF"
    sed -i 's/^;*rlimit_files = .*/rlimit_files = 65535/' "$FPM_CONF"
    systemctl restart php8.4-fpm
    echo "PHP-FPM optimized for 2 cores / 4GB"
fi

# ---- Nginx (2 cores) ----
NGINX_CONF="/etc/nginx/nginx.conf"
if [ -f "$NGINX_CONF" ]; then
    sed -i 's/worker_processes .*/worker_processes 2;/' "$NGINX_CONF"
    sed -i 's/worker_connections .*/worker_connections 4096;/' "$NGINX_CONF"
    if grep -q "worker_rlimit_nofile" "$NGINX_CONF"; then
        sed -i 's/worker_rlimit_nofile .*/worker_rlimit_nofile 65535;/' "$NGINX_CONF"
    else
        sed -i '/worker_processes/a worker_rlimit_nofile 65535;' "$NGINX_CONF"
    fi
    nginx -t && systemctl restart nginx
    echo "Nginx optimized for 2 cores"
fi

echo "=== Optimization Complete ==="
echo "Reboot recommended: sudo reboot"
