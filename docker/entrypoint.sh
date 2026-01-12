#!/bin/bash

# 不使用 set -e，允许某些命令失败后继续执行

echo "Starting Laravel application..."
echo "APP_ENV: ${APP_ENV:-not set}"

cd /var/www/html

# 根据环境选择配置文件
if [ "$APP_ENV" = "production" ] || [ -n "$ZEABUR_SERVICE_ID" ]; then
    echo "Detected production/Zeabur environment"
    
    # 如果存在.env.production，使用它作为基础
    if [ -f /var/www/html/.env.production ]; then
        echo "Using .env.production as base configuration..."
        cp /var/www/html/.env.production /var/www/html/.env
        
        # 替换环境变量占位符（Zeabur注入的环境变量）
        echo "Replacing environment variable placeholders..."
        
        # 数据库配置
        if [ -n "$DB_HOST" ]; then
            sed -i "s|\${DB_HOST}|${DB_HOST}|g" /var/www/html/.env
            echo "DB_HOST replaced: ${DB_HOST}"
        fi
        if [ -n "$DB_USERNAME" ]; then
            sed -i "s|\${DB_USERNAME}|${DB_USERNAME}|g" /var/www/html/.env
            echo "DB_USERNAME replaced: ${DB_USERNAME}"
        fi
        if [ -n "$DB_PASSWORD" ]; then
            sed -i "s|\${DB_PASSWORD}|${DB_PASSWORD}|g" /var/www/html/.env
            echo "DB_PASSWORD replaced"
        fi
        
        # Redis配置
        if [ -n "$REDIS_HOST" ]; then
            sed -i "s|\${REDIS_HOST}|${REDIS_HOST}|g" /var/www/html/.env
            echo "REDIS_HOST replaced: ${REDIS_HOST}"
        fi
        if [ -n "$REDIS_PASSWORD" ]; then
            sed -i "s|\${REDIS_PASSWORD}|${REDIS_PASSWORD}|g" /var/www/html/.env
            echo "REDIS_PASSWORD replaced"
        fi
        
        # 兼容旧的占位符格式
        if [ -n "$MYSQL_PASSWORD" ]; then
            sed -i "s|\${MYSQL_PASSWORD}|${MYSQL_PASSWORD}|g" /var/www/html/.env
        fi
    fi
    
    # Zeabur环境变量会自动覆盖.env中的值
    echo "Zeabur environment variables will override .env values"
    
    # 打印Redis配置（调试用）
    echo "REDIS_HOST: ${REDIS_HOST:-not set}"
    echo "REDIS_PORT: ${REDIS_PORT:-not set}"
    echo "REDIS_PASSWORD: ${REDIS_PASSWORD:+[SET]}"
else
    echo "Detected local/development environment"
    # 本地开发环境，如果没有.env文件则创建空文件
    if [ ! -f /var/www/html/.env ]; then
        echo "Creating empty .env file..."
        touch /var/www/html/.env
    fi
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
