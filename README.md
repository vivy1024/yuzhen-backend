# 🏋️ Yuzhen Backend - 健身APP后端（微服务化架构）

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10.x-red)](https://laravel.com/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

**版本**: v2.122.0
**架构**: 微服务化单体（Modular Monolith）
**更新**: 2026-02-20

---

## 🎯 项目简介

Yuzhen Backend是一个采用**微服务化架构**设计的健身APP后端系统。虽然当前是单体应用，但采用了模块化设计，每个模块独立、高内聚低耦合，可以渐进式地拆分为真正的微服务。

### 核心特性

- ✅ **微服务化模块设计** - 8个独立业务模块，高内聚低耦合
- ✅ **统一API响应格式** - `{code, msg, data}` 标准响应
- ✅ **双重认证** - JWT用户认证 + Internal JWT（DAML-RAG服务间认证）
- ✅ **安全加固** - CORS配置、安全响应头、审计日志、凭证清理
- ✅ **缓存策略** - Redis自动缓存管理
- ✅ **Docker部署** - fitness_php_v2 + fitness_nginx_v2 容器

---

## 📦 核心模块

| 模块 | 状态 | 说明 |
|-----|------|-----|
| Exercise | ✅ 已完成 | 动作库模块（1,790个动作） |
| Food | ✅ 已完成 | 食物库模块（1,880个食物） |
| User | ✅ 已完成 | 用户模块（注册/登录/档案） |
| Auth | ✅ 已完成 | 认证模块（JWT + Internal JWT） |
| Training | ✅ 已完成 | 训练模块（计划/记录/执行） |
| Membership | ✅ 已完成 | 会员模块（三级会员体系） |
| AI Chat | ✅ 已完成 | AI聊天模块（DAML-RAG集成） |
| Admin | ✅ 已完成 | 管理模块（用户/数据管理） |

---

## 🏗️ 架构设计

### 目录结构

```
yuzhen-backend/
├── app/
│   ├── Modules/                     # 业务模块层
│   │   ├── Exercise/               # 动作库模块 ✅
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   ├── Models/
│   │   │   └── ...
│   │   ├── User/                   # 用户模块
│   │   ├── Auth/                   # 认证模块
│   │   └── Training/               # 训练模块
│   │
│   ├── Infrastructure/              # 基础设施层
│   │   ├── Http/
│   │   │   ├── Controllers/BaseController.php
│   │   │   ├── Middleware/
│   │   │   └── Responses/ApiResponse.php
│   │   ├── Database/
│   │   ├── Cache/
│   │   └── Storage/
│   │
│   └── Shared/                      # 共享层
│       ├── Events/
│       ├── Exceptions/
│       └── Services/
│
├── routes/
│   ├── api.php                     # API入口
│   └── modules/                    # 模块路由
│       └── exercise.php
│
└── config/
    └── modules.php                 # 模块配置
```

### Exercise模块示例

```
app/Modules/Exercise/
├── Controllers/              # API控制器
│   ├── ExerciseController.php
│   └── ExerciseFilterController.php
│
├── Services/                 # 业务逻辑
│   ├── ExerciseService.php
│   ├── ExerciseCacheService.php
│   └── ExerciseFilterService.php
│
├── Repositories/             # 数据访问
│   ├── Interfaces/
│   │   └── ExerciseRepositoryInterface.php
│   └── ExerciseRepository.php
│
├── Models/                   # 数据模型
│   ├── Exercise.php
│   ├── ExerciseMedia.php
│   └── ...
│
├── Requests/                 # 请求验证
│   └── FilterExerciseRequest.php
│
├── Resources/                # 响应转换
│   ├── ExerciseResource.php
│   └── ExerciseDetailResource.php
│
└── Events/                   # 事件
    └── ExerciseViewed.php
```

---

## 🚀 快速开始

### 1. 安装依赖

```bash
composer install
```

### 2. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

### 3. 配置数据库

编辑 `.env`：

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=fitnessdb
DB_USERNAME=fitnessuser
DB_PASSWORD=your_password
```

### 4. 运行迁移

```bash
php artisan migrate
php artisan db:seed
```

### 5. 启动服务

```bash
php artisan serve
```

访问: `http://localhost:8000`

---

## 📝 API文档

### 基础接口

#### 健康检查
```
GET /api/health
```

**响应**:
```json
{
  "code": 200,
  "msg": "OK",
  "data": {
    "status": "healthy",
    "version": "2.0.0"
  }
}
```

### Exercise模块

#### 获取动作列表
```
GET /api/exercises?page=1&per_page=20
```

#### 获取筛选选项
```
GET /api/exercises/filter-options
```

#### 搜索动作
```
GET /api/exercises/search?q=chest
```

#### 获取动作详情
```
GET /api/exercises/{id}
```

---

## 🔧 开发指南

### 添加新功能

1. 在对应模块的`Services/`创建Service类
2. 在`Repositories/`创建Repository（如需数据库操作）
3. 在`Controllers/`创建Controller
4. 在`routes/modules/`添加路由

### 测试

```bash
php artisan test
```

### 代码格式化

```bash
./vendor/bin/pint
```

---

## 📚 技术栈

- **框架**: Laravel 10.x
- **PHP**: 8.2+
- **数据库**: MySQL 8.0 + Redis 7.0
- **容器**: Docker（fitness_php_v2 + fitness_nginx_v2）
- **端口**: 8000（Nginx）
- **AI服务**: DAML-RAG（Internal JWT认证）

---

## 📖 相关文档

- [CHANGELOG.md](./CHANGELOG.md) - 版本历史（当前 v2.122.0）
- [docs/01-快速开始/](./docs/01-快速开始/) - 快速启动指南
- [docs/02-核心架构/](./docs/02-核心架构/) - 架构设计文档
- [docs/03-代码参考/](./docs/03-代码参考/) - 代码实现参考
- [docs/04-开发指南/](./docs/04-开发指南/) - 开发指南
- [docs/05-API文档/](./docs/05-API文档/) - API接口文档

---

## 近期变更（2025-12 ~ 2026-02）

- 安全加固：凭证清理、API认证、CORS配置、安全响应头、审计日志
- 权限系统重构：Internal JWT验证 + fail-closed双认证
- API响应规范合规性修复（高优先级控制器）
- 错误消息用户友好性优化
- 帮助中心FAQ系统、用户反馈系统
- 训练反馈记录API
- 代码库清理：删除废弃命令

---

**开发者**: 薛小川 · **版本**: v2.122.0 · **更新**: 2026-02-20
