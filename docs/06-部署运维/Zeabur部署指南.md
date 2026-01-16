# Yuzhen Backend Zeabur 部署指南

**版本**: v2.0.0  
**更新日期**: 2026-01-16  
**状态**: ✅ 生产环境运行中

---

## 📋 概述

本文档提供 Yuzhen Backend（Laravel + PHP-FPM + Nginx）在 Zeabur 平台的完整部署指南。

### 当前部署状态

- **生产环境**: ✅ 已部署到 Zeabur（阿里云北京 182.92.78.183）
- **部署方式**: GitHub同步自动构建
- **域名**: api.yuzhen-fitness.cn
- **服务名**: fitness_php_v2

### 架构说明

**当前架构**（单容器方案）：
- 使用 `vivy1024/fitness-php-v2:latest` 镜像
- 集成 Nginx + PHP-FPM 到单一容器
- 端口：8000（对外服务）
- 自动数据库迁移和健康检查

**旧架构**（双容器方案，已废弃）：
- PHP-FPM 服务（端口9000，内部）
- Nginx 服务（端口80，对外）
- 需要手动配置服务间通信

---

## 🚀 部署步骤

### 方案A：GitHub同步部署（推荐，当前使用）

#### 1. 准备GitHub仓库

1. 确保代码已推送到 `vivy1024/yuzhen-backend` 仓库
2. 确保 `.env.production` 文件已提交（不含敏感信息）
3. 确保 `Dockerfile` 和 `entrypoint.sh` 已提交

#### 2. 在Zeabur创建服务

1. 登录 Zeabur 控制台
2. 选择项目（或创建新项目）
3. 点击 "Add Service" → "Git"
4. 选择 `vivy1024/yuzhen-backend` 仓库
5. 服务名：`fitness_php_v2`

#### 3. 配置环境变量

**核心环境变量**（必须配置）：

```bash
# 应用配置
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-app-key-here
APP_URL=https://api.yuzhen-fitness.cn

# 数据库连接（使用Zeabur自动生成的变量）
DB_CONNECTION=mysql
DB_HOST=${FITNESS_MYSQL_HOST}
DB_PORT=${FITNESS_MYSQL_PORT}
DB_DATABASE=fitness_app
DB_USERNAME=${FITNESS_MYSQL_USERNAME}
DB_PASSWORD=${FITNESS_MYSQL_PASSWORD}

# Redis配置
REDIS_HOST=${FITNESS_REDIS_HOST}
REDIS_PORT=${FITNESS_REDIS_PORT}
CACHE_DRIVER=file  # 生产环境使用file，避免Redis连接问题
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# CORS配置
FRONTEND_URL=https://app.yuzhen-fitness.cn
CORS_ALLOWED_ORIGINS=https://app.yuzhen-fitness.cn,https://yuzhen-fitness.cn

# 邮件配置
MAIL_MAILER=smtp
MAIL_HOST=smtp.qq.com
MAIL_PORT=587
MAIL_USERNAME=1336495069@qq.com
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=1336495069@qq.com
MAIL_FROM_NAME="玉珍健身"
```

**⚠️ 重要说明**：
- 使用 `${FITNESS_MYSQL_HOST}` 等Zeabur自动生成的变量
- 不要硬编码数据库地址（如 `xxx.zeabur.internal`）
- `CACHE_DRIVER=file` 避免Redis连接问题
- `CORS_ALLOWED_ORIGINS` 必须包含前端域名

#### 4. 配置端口

- **容器端口**: 8000
- **协议**: HTTP
- **对外暴露**: ✅ 是

#### 5. 配置域名

1. 在 Zeabur 服务设置中点击 "Networking"
2. 添加自定义域名：`api.yuzhen-fitness.cn`
3. 配置DNS记录（CNAME指向Zeabur提供的地址）

#### 6. 部署验证

```bash
# 1. 检查服务状态
# 访问 https://api.yuzhen-fitness.cn/api/health

# 2. 检查数据库连接
# 访问 https://api.yuzhen-fitness.cn/api/admin/metrics/daml-rag/health

# 3. 查看日志
# Zeabur控制台 → 服务 → Logs
```

---

### 方案B：Docker镜像部署（备用）

#### 步骤1：部署 PHP-FPM 服务

#### 1.1 创建服务

1. 在 Zeabur 中创建新服务
2. 选择 "Docker Image"
3. 输入镜像：`vivy1024/fitness-php-v2:latest`
4. 服务名：`php-service`（重要：Nginx 会使用这个名称连接）

#### 1.2 配置环境变量

```bash
# 数据库连接
DB_CONNECTION=mysql
DB_HOST=mysql-service
DB_PORT=3306
DB_DATABASE=fitness_app
DB_USERNAME=fitness_user
DB_PASSWORD=your-password

# Redis配置
REDIS_HOST=redis-service
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Laravel配置
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-app-key-here
APP_URL=https://your-domain.com

# 邮件配置（如果使用）
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="玉珍健身"

# 阿里云服务（如果使用）
ALIYUN_ACCESS_KEY_ID=your-key
ALIYUN_ACCESS_KEY_SECRET=your-secret
ALIYUN_SMS_SIGN_NAME=your-sign-name
ALIYUN_SMS_TEMPLATE_CODE=your-template-code
```

#### 1.3 配置端口

- **容器端口**: 9000
- **协议**: TCP（内部服务）
- **对外暴露**: ❌ 不需要（仅 Nginx 访问）

#### 1.4 配置存储卷（可选）

如果需要持久化存储：
- **路径**: `/var/www/html/storage`
- **用途**: Laravel 存储文件（上传的图片等）

---

### 步骤2：部署 Nginx 服务

#### 2.1 创建服务

1. 在 Zeabur 中创建新服务
2. 选择 "Docker Image"
3. 输入镜像：`vivy1024/fitness-nginx-v2:latest`
4. 服务名：`nginx-service`

#### 2.2 配置环境变量

```bash
# PHP-FPM 连接配置（重要！）
PHP_FPM_HOST=php-service  # 必须与 PHP 服务名一致
PHP_FPM_PORT=9000

# 前端域名（用于 CORS）
FRONTEND_URL=https://your-frontend-domain.com
```

#### 2.3 配置端口

- **容器端口**: 80
- **协议**: HTTP
- **对外暴露**: ✅ 是（主服务）

#### 2.4 配置存储卷

需要挂载 Laravel 代码：
- **路径**: `/var/www/html`
- **来源**: 可以挂载 Git 仓库或使用代码卷

---

## ⚠️ 重要配置说明

### 1. 数据库连接配置

**核心原则**：使用Zeabur环境变量占位符，避免硬编码

**✅ 正确配置**：
```bash
DB_HOST=${FITNESS_MYSQL_HOST}
DB_PORT=${FITNESS_MYSQL_PORT}
DB_USERNAME=${FITNESS_MYSQL_USERNAME}
DB_PASSWORD=${FITNESS_MYSQL_PASSWORD}
```

**❌ 错误配置**：
```bash
DB_HOST=fitness_mysql.zeabur.internal  # 硬编码，会导致连接失败
```

**连接测试机制**：
- `entrypoint.sh` 会在启动时测试数据库连接
- 最多重试30次，每次等待2秒
- 连接成功后自动执行数据库迁移

**数据库迁移**：
- 启动时自动执行 `php artisan migrate --force`
- 确保数据库结构与代码同步
- 不会删除现有数据

---

### 2. CORS跨域问题解决方案

**问题描述**：
- 前端（app.yuzhen-fitness.cn）访问后端API时出现CORS错误
- OPTIONS预检请求失败

**解决方案**：

#### 方案1：ForceCors中间件（当前使用）

创建 `app/Http/Middleware/ForceCors.php`：
```php
public function handle(Request $request, Closure $next): Response
{
    if ($request->isMethod('OPTIONS')) {
        return response('', 204)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    $response = $next($request);
    
    return $response
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
}
```

注册到 `bootstrap/app.php`：
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(ForceCors::class);
})
```

#### 方案2：Nginx配置（备用）

在 `docker/nginx/default.conf` 中添加：
```nginx
add_header 'Access-Control-Allow-Origin' '*' always;
add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With' always;

if ($request_method = 'OPTIONS') {
    return 204;
}
```

**验证CORS配置**：
```bash
curl -X OPTIONS https://api.yuzhen-fitness.cn/api/health \
  -H "Origin: https://app.yuzhen-fitness.cn" \
  -H "Access-Control-Request-Method: POST" \
  -v
```

---

### 3. Redis连接配置

**问题描述**：
- EmailService使用Redis::connection()直接连接失败
- Zeabur环境下Redis连接不稳定

**解决方案**：

#### 方案1：使用Cache Facade（推荐）

```php
// ❌ 错误：直接使用Redis
Redis::connection()->set($key, $code);

// ✅ 正确：使用Cache Facade
Cache::put($key, $code, 300);
```

#### 方案2：使用file缓存驱动

在 `.env.production` 中：
```bash
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

**优点**：
- 避免Redis连接问题
- 简化部署配置
- 适合中小规模应用

---

### 4. 自动数据库迁移

**启动流程**：
1. 容器启动
2. 等待数据库连接（最多60秒）
3. 执行 `php artisan migrate --force`
4. 启动Nginx和PHP-FPM

**entrypoint.sh关键代码**：
```bash
# 等待数据库连接
until php artisan db:show 2>/dev/null; do
    echo "等待数据库连接... ($count/30)"
    sleep 2
    count=$((count + 1))
    if [ $count -gt 30 ]; then
        echo "数据库连接超时"
        exit 1
    fi
done

# 执行数据库迁移
php artisan migrate --force
```

**注意事项**：
- 不会删除现有数据
- 只执行未运行的迁移
- 失败时容器会退出

---

### 5. PHP-FPM 服务名

**关键点**：Nginx 需要通过服务名连接到 PHP-FPM。

- ✅ **正确**：PHP 服务名设置为 `php-service`，Nginx 环境变量 `PHP_FPM_HOST=php-service`
- ❌ **错误**：PHP 服务名设置为其他名称，但 Nginx 仍使用 `php-service`

### 2. 服务依赖

在 Zeabur 中，需要确保：
1. PHP-FPM 服务先启动
2. Nginx 服务后启动（依赖 PHP-FPM）

### 3. 代码同步

两个服务需要访问相同的代码：
- **方案A**：使用 Git 仓库（推荐）
  - 两个服务都连接到同一个 Git 仓库
  - Zeabur 会自动同步代码

- **方案B**：使用存储卷
  - 创建共享存储卷
  - 两个服务都挂载同一个卷

---

## 🔧 当前镜像的问题

### 问题：Nginx 配置硬编码

当前 `vivy1024/fitness-nginx-v2` 镜像中的 Nginx 配置硬编码了：
```nginx
fastcgi_pass php_v2:9000;  # Docker Compose 服务名
```

### 解决方案

**方案1：使用环境变量（推荐）**

需要重新构建 Nginx 镜像，使用支持环境变量的配置：
- 已创建 `default.conf.zeabur` 配置文件
- 已创建 `Dockerfile.zeabur` 支持环境变量

**方案2：修改服务名**

在 Zeabur 中，将 PHP 服务名设置为 `php_v2`（与配置一致）

---

## 📝 部署检查清单

### PHP-FPM 服务

- [ ] 镜像：`vivy1024/fitness-php-v2:latest`
- [ ] 服务名：`php-service`（或与 Nginx 配置一致）
- [ ] 端口：9000（内部）
- [ ] 所有环境变量已配置
- [ ] 存储卷已挂载（如需要）
- [ ] 服务状态：Running

### Nginx 服务

- [ ] 镜像：`vivy1024/fitness-nginx-v2:latest`
- [ ] 服务名：`nginx-service`
- [ ] 端口：80（对外）
- [ ] PHP_FPM_HOST 环境变量已设置
- [ ] PHP_FPM_PORT 环境变量已设置
- [ ] 存储卷已挂载（代码目录）
- [ ] 服务状态：Running

### 验证

- [ ] 访问 Nginx 服务 URL
- [ ] 测试 API 端点：`/api/health`
- [ ] 检查日志无错误
- [ ] PHP-FPM 连接正常

---

## 🚨 常见问题

### 问题1：数据库连接失败

**错误信息**：
```
SQLSTATE[HY000] [2002] Connection refused
```

**原因**：
- 数据库服务未启动
- 数据库连接配置错误
- 使用了硬编码的内网地址

**解决方案**：
1. 检查数据库服务状态（Zeabur控制台）
2. 验证环境变量配置：
   ```bash
   DB_HOST=${FITNESS_MYSQL_HOST}  # 使用占位符
   ```
3. 查看启动日志，确认连接测试通过

---

### 问题2：CORS跨域错误

**错误信息**：
```
Access to XMLHttpRequest has been blocked by CORS policy
```

**原因**：
- CORS中间件未生效
- OPTIONS预检请求返回错误状态码
- Nginx配置覆盖了Laravel的CORS头

**解决方案**：
1. 确认ForceCors中间件已注册
2. 检查OPTIONS请求返回204状态码
3. 验证CORS头：
   ```bash
   curl -X OPTIONS https://api.yuzhen-fitness.cn/api/health \
     -H "Origin: https://app.yuzhen-fitness.cn" -v
   ```

---

### 问题3：Redis连接失败

**错误信息**：
```
Connection refused [tcp://redis:6379]
```

**原因**：
- Redis服务未启动
- 使用了不兼容的Redis方法

**解决方案**：
1. 改用Cache Facade：
   ```php
   Cache::put($key, $value, $ttl);
   ```
2. 或使用file缓存驱动：
   ```bash
   CACHE_DRIVER=file
   ```

---

### 问题4：502 Bad Gateway

**原因**：Nginx 无法连接到 PHP-FPM

**解决方案**：
1. 检查 PHP-FPM 服务是否运行
2. 验证 `PHP_FPM_HOST` 环境变量是否正确
3. 确认 PHP 服务名与 Nginx 配置一致

---

### 问题5：404 Not Found

**原因**：代码路径不正确

**解决方案**：
1. 检查存储卷挂载路径
2. 确认 Laravel `public` 目录存在
3. 验证 Nginx `root` 配置

---

### 问题6：数据库迁移失败

**错误信息**：
```
Migration table not found
```

**原因**：
- 数据库连接失败
- 权限不足

**解决方案**：
1. 检查数据库连接配置
2. 确认数据库用户有CREATE权限
3. 手动执行迁移：
   ```bash
   docker exec fitness_php_v2 php artisan migrate --force
   ```

---

## 🔗 相关文档

- [MySQL数据库结构](../02-核心架构/02-数据层/01-MySQL数据库结构-v1.md)
- [会员系统实施指南](../04-部署指南/会员系统完整实施指南.md)
- [Zeabur生产环境规则](/.kiro/steering/zeabur-production.md)

---

**维护者**: 薛小川  
**最后更新**: 2026-01-16

