# PHP 8.3 + Nginx 一体化镜像 - Zeabur生产环境
FROM php:8.3-fpm

# 设置工作目录
WORKDIR /var/www/html

# 配置国内镜像源（阿里云）解决Zeabur构建网络问题
RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || \
    sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list 2>/dev/null || true

# 安装系统依赖 + Nginx（添加重试机制）
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# 安装 PHP 扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache intl

# 安装 Redis 扩展
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# 安装 Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 配置 PHP
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo "max_execution_time = 300" > /usr/local/etc/php/conf.d/execution-time.ini

# 复制应用代码
COPY . /var/www/html

# 安装 Composer 依赖
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 配置 Nginx
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak

# 配置 Supervisor（管理nginx和php-fpm）
RUN mkdir -p /var/log/supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# 复制启动脚本
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 暴露端口
EXPOSE 80

# 使用启动脚本
CMD ["/entrypoint.sh"]
