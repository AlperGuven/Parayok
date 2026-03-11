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
