# 🏋️ Yuzhen Backend - 健身APP后端（微服务化架构）

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10.x-red)](https://laravel.com/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

**版本**: 2.0.0  
**架构**: 微服务化单体（Modular Monolith）  
**更新**: 2025-11-01

---

## 🎯 项目简介

Yuzhen Backend是一个采用**微服务化架构**设计的健身APP后端系统。虽然当前是单体应用，但采用了模块化设计，每个模块独立、高内聚低耦合，可以渐进式地拆分为真正的微服务。

### 核心特性

- ✅ **微服务化模块设计** - 每个功能模块独立，易于维护和扩展
- ✅ **三层架构** - Modules（业务）→ Infrastructure（基础设施）→ Shared（共享）
- ✅ **统一API响应格式** - `{code, msg, data}` 标准响应
- ✅ **分层清晰** - Controller → Service → Repository → Model
- ✅ **依赖注入** - 基于接口编程，易于测试和扩展
- ✅ **缓存策略** - 自动缓存管理，提升性能
- ✅ **事件驱动** - 模块间通过事件解耦

---

## 📦 模块列表

| 模块 | 状态 | 说明 |
|-----|------|-----|
| Exercise | ✅ 已完成 | 动作库模块（1603个动作） |
| User | 🚧 待实现 | 用户模块 |
| Auth | 🚧 待实现 | 认证模块（JWT） |
| Training | 🚧 待实现 | 训练模块 |
| Membership | 🚧 待实现 | 会员模块 |
| Admin | 🚧 待实现 | 管理模块 |

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
- **数据库**: MySQL 8.0
- **缓存**: Redis
- **队列**: Redis Queue
- **文档**: OpenAPI 3.0

---

## 📖 相关文档

- [项目结构规划.md](./项目结构规划.md) - 完整架构设计
- [微服务化架构说明.md](./微服务化架构说明.md) - 架构详解
- [快速启动指南.md](./快速启动指南.md) - 开发指南
- [模块化重构完成总结.md](./模块化重构完成总结.md) - 重构总结

---

## 🎯 未来规划

### Phase 1: 单体模块化（当前）✅
- ✅ 模块化目录结构
- ✅ Exercise模块完整实现
- 🚧 User、Auth、Training模块实现

### Phase 2: 服务分离
- 拆分为多个Laravel应用
- 独立部署
- 服务间HTTP通信

### Phase 3: 真正的微服务
- 独立数据库
- 服务注册与发现
- API网关
- Docker + Kubernetes

---

## 🤝 贡献

欢迎贡献代码！请遵循以下步骤：

1. Fork本项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建Pull Request

---

## 📄 License

MIT License

---

## 📧 联系方式

- **项目主页**: GitHub Repository
- **问题反馈**: GitHub Issues

---

**🎉 Happy Coding!**
