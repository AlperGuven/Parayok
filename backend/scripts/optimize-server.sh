#!/bin/bash

# Open File Limits Configuration for 1000+ Users
# Run as: sudo bash scripts/optimize-server.sh

echo "=== Optimizing System Limits for Parayok ==="

# Increase open file limits
echo "* soft nofile 65535" | tee -a /etc/security/limits.conf
echo "* hard nofile 65535" | tee -a /etc/security/limits.conf
echo "root soft nofile 65535" | tee -a /etc/security/limits.conf
echo "root hard nofile 65535" | tee -a /etc/security/limits.conf
echo "www-data soft nofile 65535" | tee -a /etc/security/limits.conf
echo "www-data hard nofile 65535" | tee -a /etc/security/limits.conf

# System-wide file descriptor limit
echo "65535" > /proc/sys/fs/file-max
echo "fs.file-max=65535" | tee -a /etc/sysctl.conf

# Network optimization
echo "65535" > /proc/sys/net/core/somaxconn
echo "net.core.somaxconn=65535" | tee -a /etc/sysctl.conf
echo "net.ipv4.tcp_max_syn_backlog=65535" | tee -a /etc/sysctl.conf

# Apply sysctl changes
sysctl -p

# PHP-FPM optimization (if using PHP-FPM)
if [ -f /etc/php/8.3/fpm/pool.d/www.conf ]; then
    sed -i 's/rlimit_files = 4096/rlimit_files = 65535/' /etc/php/8.3/fpm/pool.d/www.conf
    sed -i 's/pm.max_children = 5/pm.max_children = 50/' /etc/php/8.3/fpm/pool.d/www.conf
fi

# Nginx optimization (if using Nginx)
if [ -f /etc/nginx/nginx.conf ]; then
    sed -i 's/worker_connections 1024/worker_connections 65535/' /etc/nginx/nginx.conf
    sed -i 's/worker_rlimit_nofile .*/worker_rlimit_nofile 65535;/' /etc/nginx/nginx.conf
fi

echo "=== Optimization Complete ==="
echo "Please REBOOT your server for changes to take effect"
