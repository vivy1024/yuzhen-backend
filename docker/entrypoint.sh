#!/bin/bash

# 不使用 set -e，允许某些命令失败后继续执行

# 等待服务就绪
echo "Waiting for services to be ready..."
sleep 3

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

# 清除配置缓存（不依赖Redis）
echo "Clearing config cache..."
php artisan config:clear || echo "Warning: config:clear failed, continuing..."

# 清除路由缓存（不依赖Redis）
echo "Clearing route cache..."
php artisan route:clear || echo "Warning: route:clear failed, continuing..."

# 清除视图缓存（不依赖Redis）
echo "Clearing view cache..."
php artisan view:clear || echo "Warning: view:clear failed, continuing..."

# 尝试清除应用缓存（可能依赖Redis，允许失败）
echo "Attempting to clear application cache..."
php artisan cache:clear 2>/dev/null || echo "Warning: cache:clear failed (Redis may not be ready), continuing..."

# 生成配置缓存（生产环境优化）
echo "Caching config for production..."
php artisan config:cache || echo "Warning: config:cache failed, continuing..."

# 生成路由缓存（生产环境优化）
echo "Caching routes for production..."
php artisan route:cache || echo "Warning: route:cache failed, continuing..."

echo "Laravel initialization complete, starting services..."

# 启动 Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
