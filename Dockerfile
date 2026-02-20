# PHP 8.3 + Nginx 一体化镜像 - Zeabur生产环境
FROM php:8.3-fpm

WORKDIR /var/www/html

# ============= 第1层：系统依赖+PHP扩展（很少变化）=============
RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || \
    sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list 2>/dev/null || true

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

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache intl

RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo "max_execution_time = 300" > /usr/local/etc/php/conf.d/execution-time.ini

# ============= 第2层：Composer依赖（仅composer.json变化时重建）=============
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# ============= 第3层：应用代码（频繁变化）=============
COPY . /var/www/html

# 重新运行composer以执行post-install脚本和自动加载
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ============= 第4层：配置文件（很少变化）=============
# Zeabur单容器模式：用根目录nginx.conf（127.0.0.1:9000 + 端口8080）
# 注意：docker/nginx/default.conf 是本地多容器模式用的（php_v2:9000 + 端口80）
COPY nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak

RUN mkdir -p /var/log/supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
