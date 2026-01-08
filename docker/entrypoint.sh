#!/bin/bash

# 不使用 set -e，允许某些命令失败后继续执行

echo "Starting Laravel application..."

# 如果没有.env文件，从环境变量创建
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env file from environment variables..."
    touch /var/www/html/.env
fi

# 设置目录权限
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

cd /var/www/html

# 只清除配置缓存，不生成新缓存（避免Redis连接问题）
echo "Clearing caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# 注意：不执行 cache:clear 和 config:cache，因为它们可能触发Redis连接

echo "Laravel initialization complete, starting services..."

# 启动 Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
