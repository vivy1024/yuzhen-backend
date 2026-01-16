# Yuzhen Backend Zeabur 部署指南

**版本**: v3.0.0  
**更新日期**: 2026-01-17  
**状态**: ✅ 生产环境运行中

---

## 📋 概述

本文档提供 Yuzhen Backend（Laravel + PHP-FPM + Nginx）在 Zeabur 平台的完整部署指南，包括GitHub同步部署、环境变量配置、数据库连接、CORS问题解决、Redis配置、自动迁移机制等核心内容。

### 当前部署状态

- **生产环境**: ✅ 已部署到 Zeabur（阿里云北京 182.92.78.183）
- **部署方式**: GitHub同步自动构建
- **域名**: api.yuzhen-fitness.cn
- **服务名**: fitness_php_v2
- **GitHub仓库**: vivy1024/yuzhen-backend

### 架构说明

**当前架构**（单容器方案）：
- 使用 `vivy1024/fitness-php-v2:latest` 镜像
- 集成 Nginx + PHP-FPM 到单一容器
- 端口：8000（对外服务）
- 自动数据库迁移和健康检查
- 支持流式响应（AI聊天）

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

# DAML-RAG服务连接
DAML_RAG_URL=http://fitness_daml_rag.zeabur.internal:8001

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
- 敏感信息（如密码、密钥）通过Zeabur环境变量覆盖，不要提交到Git

**环境变量配置位置**：
1. **代码仓库**：`.env.production` 文件（不含敏感信息）
2. **Zeabur控制台**：服务 → Variable（覆盖敏感信息）

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

#### 1.1 环境变量占位符

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
DB_HOST=182.92.78.183  # 硬编码IP，不推荐
```

**为什么使用占位符**：
- Zeabur会自动注入正确的连接信息
- 支持内网域名和公网端口自动切换
- 避免手动维护连接信息
- 提高配置的可移植性

#### 1.2 连接测试机制

**entrypoint.sh启动流程**：
```bash
#!/bin/bash
set -e

echo "等待数据库连接..."
count=0
until php artisan db:show 2>/dev/null; do
    echo "等待数据库连接... ($count/30)"
    sleep 2
    count=$((count + 1))
    if [ $count -gt 30 ]; then
        echo "❌ 数据库连接超时"
        exit 1
    fi
done

echo "✅ 数据库连接成功"
```

**连接参数**：
- 最多重试：30次
- 重试间隔：2秒
- 总超时时间：60秒
- 测试命令：`php artisan db:show`

**连接失败排查**：
1. 检查数据库服务是否运行（Zeabur控制台）
2. 验证环境变量是否正确注入
3. 查看启动日志（服务 → Logs）
4. 确认数据库用户权限

#### 1.3 内网域名 vs 公网端口

**Zeabur服务间通信**：

| 协议 | 内网支持 | 推荐方式 | 示例 |
|------|---------|---------|------|
| MySQL | ✅ 支持 | 内网域名 | `fitness_mysql.zeabur.internal:3306` |
| Redis | ✅ 支持 | 内网域名 | `fitness-redis.zeabur.internal:6379` |
| HTTP/HTTPS | ✅ 支持 | 内网域名 | `fitness_daml_rag.zeabur.internal:8001` |
| Neo4j Bolt | ❌ 不支持 | 公网端口 | `182.92.78.183:32633` |
| Qdrant gRPC | ❌ 不支持 | 公网端口 | `182.92.78.183:32091` |

**配置建议**：
- 标准协议（MySQL、Redis、HTTP）：优先使用内网域名
- 自定义协议（Bolt、gRPC）：必须使用公网端口
- 使用Zeabur占位符自动处理

#### 1.4 数据库迁移

**自动迁移机制**：
```bash
# entrypoint.sh中的迁移逻辑
echo "执行数据库迁移..."
php artisan migrate --force

if [ $? -eq 0 ]; then
    echo "✅ 数据库迁移成功"
else
    echo "❌ 数据库迁移失败"
    exit 1
fi
```

**迁移特点**：
- 启动时自动执行
- 只运行未执行的迁移
- 不会删除现有数据
- 失败时容器退出

**手动执行迁移**：
```bash
# 在Zeabur控制台 → 服务 → Terminal
php artisan migrate --force

# 查看迁移状态
php artisan migrate:status

# 回滚最后一次迁移（谨慎使用）
php artisan migrate:rollback --step=1
```

**迁移失败排查**：
1. 检查迁移文件语法
2. 验证数据库用户权限（需要CREATE、ALTER权限）
3. 查看详细错误日志
4. 确认migrations表存在

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

#### 3.1 问题描述

**常见错误**：
```
Connection refused [tcp://redis:6379]
RedisException: Connection to Redis failed
```

**原因分析**：
- 直接使用 `Redis::connection()` 在Zeabur环境下不稳定
- Redis服务未启动或连接信息错误
- 网络延迟导致连接超时

#### 3.2 解决方案

**方案1：使用Cache Facade（推荐）**

```php
// ❌ 错误：直接使用Redis Facade
use Illuminate\Support\Facades\Redis;

Redis::connection()->set($key, $code);
Redis::connection()->expire($key, 300);
$value = Redis::connection()->get($key);

// ✅ 正确：使用Cache Facade
use Illuminate\Support\Facades\Cache;

Cache::put($key, $code, 300);  // 自动处理过期时间
$value = Cache::get($key);
Cache::forget($key);  // 删除缓存
```

**优点**：
- 自动处理连接池
- 支持多种缓存驱动（redis、file、memcached）
- 统一的API接口
- 更好的错误处理

**方案2：使用file缓存驱动（生产环境推荐）**

在 `.env.production` 中：
```bash
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

**优点**：
- 避免Redis连接问题
- 简化部署配置
- 无需额外服务
- 适合中小规模应用

**缺点**：
- 性能略低于Redis
- 不支持分布式缓存
- 文件系统IO开销

**方案3：配置Redis连接（如需使用Redis）**

```bash
# .env.production
REDIS_CLIENT=phpredis  # 或 predis
REDIS_HOST=${FITNESS_REDIS_HOST}
REDIS_PORT=${FITNESS_REDIS_PORT}
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1
```

**config/database.php**：
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_timeout' => 60,
        'timeout' => 5,
    ],
],
```

#### 3.3 性能对比

| 缓存驱动 | 读取速度 | 写入速度 | 分布式 | 持久化 | 推荐场景 |
|---------|---------|---------|--------|--------|---------|
| Redis | 极快 | 极快 | ✅ | ✅ | 高并发、分布式 |
| File | 快 | 中 | ❌ | ✅ | 单机、中小规模 |
| Array | 极快 | 极快 | ❌ | ❌ | 测试环境 |

#### 3.4 验证Redis连接

```bash
# 在Zeabur控制台 → 服务 → Terminal
php artisan tinker

# 测试Redis连接
>>> Cache::put('test_key', 'test_value', 60);
>>> Cache::get('test_key');
=> "test_value"

# 测试Redis Facade（如果使用）
>>> Redis::ping();
=> "PONG"
```

---

### 4. 自动数据库迁移

#### 4.1 启动流程

**完整启动流程**：
```
1. 容器启动
   ↓
2. 加载环境变量（.env.production）
   ↓
3. 等待数据库连接（最多60秒）
   ↓
4. 执行数据库迁移（php artisan migrate --force）
   ↓
5. 启动PHP-FPM（后台）
   ↓
6. 启动Nginx（前台）
```

#### 4.2 entrypoint.sh完整代码

```bash
#!/bin/bash
set -e

echo "=========================================="
echo "Yuzhen Backend 启动中..."
echo "=========================================="

# 1. 等待数据库连接
echo "等待数据库连接..."
count=0
until php artisan db:show 2>/dev/null; do
    echo "等待数据库连接... ($count/30)"
    sleep 2
    count=$((count + 1))
    if [ $count -gt 30 ]; then
        echo "❌ 数据库连接超时"
        exit 1
    fi
done
echo "✅ 数据库连接成功"

# 2. 执行数据库迁移
echo "执行数据库迁移..."
php artisan migrate --force

if [ $? -eq 0 ]; then
    echo "✅ 数据库迁移成功"
else
    echo "❌ 数据库迁移失败"
    exit 1
fi

# 3. 清理缓存（可选）
echo "清理应用缓存..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. 优化性能（生产环境）
if [ "$APP_ENV" = "production" ]; then
    echo "优化生产环境..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# 5. 启动PHP-FPM（后台）
echo "启动PHP-FPM..."
php-fpm -D

# 6. 启动Nginx（前台）
echo "启动Nginx..."
echo "=========================================="
echo "✅ Yuzhen Backend 启动完成"
echo "=========================================="
nginx -g 'daemon off;'
```

#### 4.3 迁移机制说明

**迁移特点**：
- **增量执行**：只运行未执行的迁移
- **幂等性**：多次执行不会重复创建
- **事务保护**：失败时自动回滚
- **版本追踪**：记录在 `migrations` 表

**迁移文件示例**：
```php
// database/migrations/2024_01_01_000000_create_users_table.php
public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('users');
}
```

**迁移命令**：
```bash
# 执行所有未运行的迁移
php artisan migrate --force

# 查看迁移状态
php artisan migrate:status

# 回滚最后一批迁移
php artisan migrate:rollback

# 回滚所有迁移
php artisan migrate:reset

# 回滚并重新运行所有迁移（危险！）
php artisan migrate:refresh --force
```

#### 4.4 注意事项

**✅ 安全操作**：
- `migrate`：只添加新表和字段
- `migrate:status`：查看状态
- `migrate:rollback --step=1`：回滚一步

**❌ 危险操作**（生产环境禁止）：
- `migrate:fresh`：删除所有表重建
- `migrate:refresh`：回滚并重新运行
- `migrate:reset`：回滚所有迁移
- `db:wipe`：清空数据库

**最佳实践**：
1. 本地测试迁移文件
2. 生产环境部署前备份数据库
3. 使用 `--pretend` 预览SQL
4. 避免修改已部署的迁移文件
5. 使用新迁移文件修改表结构

#### 4.5 迁移失败排查

**常见错误1：表已存在**
```
SQLSTATE[42S01]: Base table or view already exists
```
**解决方案**：
- 检查 `migrations` 表记录
- 手动删除重复的迁移记录
- 或使用 `Schema::dropIfExists()` 先删除表

**常见错误2：字段已存在**
```
SQLSTATE[42S21]: Column already exists
```
**解决方案**：
- 使用 `Schema::hasColumn()` 检查
- 或使用 `dropColumn()` 先删除字段

**常见错误3：权限不足**
```
SQLSTATE[42000]: Access denied for user
```
**解决方案**：
- 确认数据库用户有 CREATE、ALTER 权限
- 检查 `DB_USERNAME` 和 `DB_PASSWORD`

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

## 🚨 常见问题排查

### 问题1：数据库连接失败

**错误信息**：
```
SQLSTATE[HY000] [2002] Connection refused
SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed
```

**原因分析**：
1. 数据库服务未启动
2. 数据库连接配置错误
3. 使用了硬编码的内网地址
4. 环境变量未正确注入

**解决方案**：

**步骤1：检查数据库服务状态**
- 登录Zeabur控制台
- 查看 `fitness_mysql` 服务状态
- 确认服务为 Running 状态

**步骤2：验证环境变量配置**
```bash
# 在Zeabur控制台 → 服务 → Terminal
echo $DB_HOST
echo $DB_PORT
echo $DB_USERNAME

# 应该显示实际的连接信息，而不是占位符
```

**步骤3：测试数据库连接**
```bash
php artisan db:show
# 应该显示数据库连接信息

php artisan tinker
>>> DB::connection()->getPdo();
# 应该返回PDO对象
```

**步骤4：查看启动日志**
- Zeabur控制台 → 服务 → Logs
- 查找 "等待数据库连接" 相关日志
- 确认连接测试通过

**步骤5：修正配置**
```bash
# ✅ 正确配置
DB_HOST=${FITNESS_MYSQL_HOST}
DB_PORT=${FITNESS_MYSQL_PORT}

# ❌ 错误配置
DB_HOST=fitness_mysql.zeabur.internal  # 硬编码
DB_HOST=localhost  # 错误的地址
```

---

### 问题2：CORS跨域错误

**错误信息**：
```
Access to XMLHttpRequest at 'https://api.yuzhen-fitness.cn/api/chat' 
from origin 'https://app.yuzhen-fitness.cn' has been blocked by CORS policy
```

**原因分析**：
1. CORS中间件未生效
2. OPTIONS预检请求返回错误状态码
3. Nginx配置覆盖了Laravel的CORS头
4. CORS_ALLOWED_ORIGINS配置错误

**解决方案**：

**步骤1：确认ForceCors中间件已注册**

`app/Http/Middleware/ForceCors.php`：
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceCors
{
    public function handle(Request $request, Closure $next): Response
    {
        // 处理OPTIONS预检请求
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $response = $next($request);
        
        // 添加CORS头到所有响应
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }
}
```

`bootstrap/app.php`：
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(ForceCors::class);
})
```

**步骤2：验证OPTIONS请求**
```bash
curl -X OPTIONS https://api.yuzhen-fitness.cn/api/health \
  -H "Origin: https://app.yuzhen-fitness.cn" \
  -H "Access-Control-Request-Method: POST" \
  -v

# 应该返回：
# HTTP/1.1 204 No Content
# Access-Control-Allow-Origin: *
# Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
```

**步骤3：检查环境变量**
```bash
CORS_ALLOWED_ORIGINS=https://app.yuzhen-fitness.cn,https://yuzhen-fitness.cn
FRONTEND_URL=https://app.yuzhen-fitness.cn
```

**步骤4：禁用Laravel默认CORS（如果冲突）**

`config/cors.php`：
```php
return [
    'paths' => [],  // 禁用默认CORS
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

---

### 问题3：Redis连接失败

**错误信息**：
```
Connection refused [tcp://redis:6379]
RedisException: Connection to Redis failed
```

**原因分析**：
1. Redis服务未启动
2. 使用了不兼容的Redis方法
3. 连接超时或网络问题

**解决方案**：

**方案1：改用Cache Facade（推荐）**
```php
// ❌ 错误
use Illuminate\Support\Facades\Redis;
Redis::connection()->set($key, $value);

// ✅ 正确
use Illuminate\Support\Facades\Cache;
Cache::put($key, $value, $ttl);
```

**方案2：使用file缓存驱动**
```bash
# .env.production
CACHE_DRIVER=file
SESSION_DRIVER=file
```

**方案3：检查Redis服务**
```bash
# 在Zeabur控制台查看Redis服务状态
# 确认 fitness-redis 服务为 Running

# 测试连接
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
```

---

### 问题4：502 Bad Gateway

**错误信息**：
```
502 Bad Gateway
nginx/1.24.0
```

**原因分析**：
1. PHP-FPM进程未启动
2. PHP-FPM进程崩溃
3. Nginx无法连接到PHP-FPM
4. 内存不足导致进程被杀

**解决方案**：

**步骤1：检查进程状态**
```bash
# 在Zeabur控制台 → 服务 → Terminal
ps aux | grep php-fpm
ps aux | grep nginx

# 应该看到多个php-fpm进程和nginx进程
```

**步骤2：查看错误日志**
```bash
# PHP-FPM日志
tail -f /var/log/php-fpm/error.log

# Nginx日志
tail -f /var/log/nginx/error.log
```

**步骤3：重启服务**
- Zeabur控制台 → 服务 → Overview → Restart

**步骤4：检查内存使用**
```bash
free -h
# 确认有足够的可用内存
```

---

### 问题5：数据库迁移失败

**错误信息**：
```
SQLSTATE[42S01]: Base table or view already exists
SQLSTATE[42S21]: Column already exists
SQLSTATE[42000]: Access denied for user
```

**原因分析**：
1. 表或字段已存在
2. 数据库用户权限不足
3. 迁移文件语法错误
4. migrations表损坏

**解决方案**：

**问题A：表已存在**
```php
// 使用 Schema::dropIfExists() 先删除
public function up()
{
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        // ...
    });
}
```

**问题B：字段已存在**
```php
// 检查字段是否存在
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'phone')) {
            $table->string('phone')->nullable();
        }
    });
}
```

**问题C：权限不足**
```sql
-- 授予完整权限
GRANT ALL PRIVILEGES ON fitness_app.* TO 'fitness_user'@'%';
FLUSH PRIVILEGES;
```

**问题D：查看迁移状态**
```bash
php artisan migrate:status

# 手动标记迁移为已执行
php artisan migrate:mark-migrated 2024_01_01_000000_create_users_table
```

---

### 问题6：环境变量未生效

**错误信息**：
```
APP_KEY is not set
Database configuration not found
```

**原因分析**：
1. .env.production文件未提交到Git
2. Zeabur环境变量未配置
3. 环境变量名称错误
4. 缓存未清理

**解决方案**：

**步骤1：确认.env.production存在**
```bash
# 在代码仓库中
ls -la .env.production
# 应该存在且已提交到Git
```

**步骤2：检查Zeabur环境变量**
- Zeabur控制台 → 服务 → Variable
- 确认关键变量已配置（APP_KEY、DB_*等）

**步骤3：清理配置缓存**
```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

**步骤4：验证环境变量**
```bash
php artisan tinker
>>> env('APP_KEY');
>>> env('DB_HOST');
>>> config('database.connections.mysql.host');
```

---

### 问题7：流式响应中断

**错误信息**：
```
Stream connection closed
ERR_INCOMPLETE_CHUNKED_ENCODING
```

**原因分析**：
1. Nginx缓冲配置问题
2. PHP执行超时
3. 网络连接中断
4. DAML-RAG服务异常

**解决方案**：

**步骤1：检查Nginx配置**
```nginx
# docker/nginx/default.conf
location /api/ai/ {
    proxy_pass http://127.0.0.1:8000;
    proxy_buffering off;  # 禁用缓冲
    proxy_cache off;
    proxy_read_timeout 300s;
    proxy_connect_timeout 75s;
}
```

**步骤2：增加PHP超时时间**
```ini
# php.ini
max_execution_time = 300
```

**步骤3：测试DAML-RAG连接**
```bash
curl http://fitness_daml_rag.zeabur.internal:8001/api/health
# 应该返回健康状态
```

---

## 🔗 相关文档

- [MySQL数据库完整结构](../02-核心架构/02-数据层/03-MySQL数据库完整结构文档.md)
- [会员系统完整实现](../03-代码参考/03-会员系统/02-会员系统完整实现.md)
- [AI聊天系统实现](../03-代码参考/05-AI聊天系统/02-AI聊天系统完整实现.md)
- [Zeabur生产环境规则](/.kiro/steering/zeabur-production.md)
- [健康检查API](../05-API文档/05-健康检查API.md)

---

**维护者**: 薛小川  
**最后更新**: 2026-01-17

