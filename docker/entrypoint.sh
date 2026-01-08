#!/bin/bash
set -e

# 等待数据库就绪（可选）
# sleep 5

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

# 清除并重建缓存
cd /var/www/html
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 运行数据库迁移（可选，生产环境谨慎使用）
# php artisan migrate --force

# 启动 Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
