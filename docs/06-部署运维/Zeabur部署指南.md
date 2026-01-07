# Yuzhen Backend Zeabur 部署指南

**版本**: v1.0.0  
**更新日期**: 2026-01-07  
**状态**: ✅ 部署就绪

---

## 📋 概述

本文档提供 Yuzhen Backend（Laravel + PHP-FPM + Nginx）在 Zeabur 平台的完整部署指南。

### 架构说明

在 Zeabur 中，需要部署两个服务：

1. **PHP-FPM 服务** (`vivy1024/fitness-php-v2`)
   - 端口：9000（内部服务，不对外暴露）
   - 功能：处理 PHP 请求

2. **Nginx 服务** (`vivy1024/fitness-nginx-v2`)
   - 端口：80（对外服务）
   - 功能：反向代理，转发请求到 PHP-FPM

---

## 🚀 部署步骤

### 步骤1：部署 PHP-FPM 服务

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

### 1. PHP-FPM 服务名

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

### 问题1：502 Bad Gateway

**原因**：Nginx 无法连接到 PHP-FPM

**解决方案**：
1. 检查 PHP-FPM 服务是否运行
2. 验证 `PHP_FPM_HOST` 环境变量是否正确
3. 确认 PHP 服务名与 Nginx 配置一致

### 问题2：404 Not Found

**原因**：代码路径不正确

**解决方案**：
1. 检查存储卷挂载路径
2. 确认 Laravel `public` 目录存在
3. 验证 Nginx `root` 配置

### 问题3：CORS 错误

**原因**：CORS 配置不允许前端域名

**解决方案**：
1. 在 Nginx 配置中更新 `Access-Control-Allow-Origin`
2. 或使用环境变量配置前端域名

---

## 🔗 相关文档

- [Docker部署指南](./Docker部署.md)
- [环境变量配置指南](./环境变量配置指南.md)

---

**维护者**: 薛小川  
**最后更新**: 2026-01-07

