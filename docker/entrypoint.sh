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
        
        # 通用占位符替换：将所有 ${VAR_NAME} 替换为对应环境变量的值
        echo "Replacing environment variable placeholders..."
        
        # 遍历所有环境变量，替换 .env 中的 ${KEY} 占位符
        while IFS='=' read -r key value; do
            # 跳过空行和无效变量名
            if [ -n "$key" ] && echo "$key" | grep -qE '^[A-Za-z_][A-Za-z0-9_]*$'; then
                # 转义特殊字符用于 sed
                escaped_value=$(echo "$value" | sed 's/[&/\]/\\&/g')
                sed -i "s|\${${key}}|${escaped_value}|g" /var/www/html/.env 2>/dev/null
            fi
        done < <(env)
        
        echo "Environment variable placeholders replaced"
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

# 测试数据库连接（生产环境）
if [ "$APP_ENV" = "production" ] || [ -n "$ZEABUR_SERVICE_ID" ]; then
    echo "========== Database Connection Debug =========="
    echo "DB_HOST from env: ${DB_HOST:-not set}"
    echo "MYSQL_HOST from env: ${MYSQL_HOST:-not set}"
    echo "DB_PORT from env: ${DB_PORT:-not set}"
    echo "DB_DATABASE from env: ${DB_DATABASE:-not set}"
    echo "DB_USERNAME from env: ${DB_USERNAME:-not set}"
    echo "DB_PASSWORD is set: ${DB_PASSWORD:+YES}"
    echo "MYSQL_PASSWORD is set: ${MYSQL_PASSWORD:+YES}"
    
    # 优先使用MYSQL_HOST（Zeabur服务引用），否则使用DB_HOST
    ACTUAL_DB_HOST="${MYSQL_HOST:-${DB_HOST:-localhost}}"
    ACTUAL_DB_USER="${MYSQL_USERNAME:-${DB_USERNAME:-root}}"
    ACTUAL_DB_PASS="${MYSQL_PASSWORD:-${DB_PASSWORD:-}}"
    ACTUAL_DB_NAME="${MYSQL_DATABASE:-${DB_DATABASE:-fitness_app}}"
    ACTUAL_DB_PORT="${MYSQL_PORT:-${DB_PORT:-3306}}"
    
    echo "Using DB_HOST: ${ACTUAL_DB_HOST}"
    echo "Using DB_USER: ${ACTUAL_DB_USER}"
    echo "Using DB_NAME: ${ACTUAL_DB_NAME}"
    echo "Using DB_PORT: ${ACTUAL_DB_PORT}"
    echo "=============================================="
    
    echo "Testing database connection..."
    
    # 等待数据库可用（最多30秒）
    MAX_RETRIES=6
    RETRY_COUNT=0
    
    while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
        # 使用PHP测试数据库连接，使用实际解析的变量
        if php -r "
            \$host = '${ACTUAL_DB_HOST}';
            \$port = '${ACTUAL_DB_PORT}';
            \$user = '${ACTUAL_DB_USER}';
            \$pass = '${ACTUAL_DB_PASS}';
            \$db = '${ACTUAL_DB_NAME}';
            
            echo \"Connecting to: \$host:\$port as \$user to database \$db\n\";
            
            try {
                \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                echo 'Database connection successful!';
                exit(0);
            } catch (PDOException \$e) {
                echo 'Connection failed: ' . \$e->getMessage();
                exit(1);
            }
        " 2>&1; then
            echo "Database is ready!"
            break
        else
            RETRY_COUNT=$((RETRY_COUNT + 1))
            echo "Database not ready, retry $RETRY_COUNT/$MAX_RETRIES..."
            sleep 5
        fi
    done
    
    if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
        echo "WARNING: Could not connect to database after $MAX_RETRIES attempts"
        echo "Continuing anyway..."
    else
        # 数据库连接成功，运行迁移
        echo "Running database migrations..."
        php artisan migrate --force 2>&1 || echo "Migration completed (or no new migrations)"
    fi
fi

echo "Laravel initialization complete, starting services..."

# 启动 Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
