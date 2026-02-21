# yuzhen-backend 开发规则

> 本文件为后端子项目专属规则，通用规则见根仓库 `CLAUDE.md`。

## 技术栈

- **框架**: Laravel 11 (PHP 8.3)
- **数据库**: MySQL 8.0 (`fitness_mysql` 容器)
- **缓存**: Redis (`fitness_redis` 容器)
- **运行环境**: `fitness_php_v2` 容器 + `fitness_nginx_v2` 反向代理

## 测试命令

```bash
# 单元测试（必须在容器内运行）
docker exec fitness_php_v2 php artisan test --filter=具体测试类

# 全量测试
docker exec fitness_php_v2 php artisan test

# 属性测试
docker exec fitness_php_v2 php artisan test --filter=PropertyTest
```

## 代码规范

- Controller 必须使用 FormRequest 验证输入，禁止直接 `$request->input()` 无验证
- 数据库查询使用 Eloquent ORM，`DB::raw()` 必须参数绑定
- API 响应统一格式：`{ code: int, data: any, msg: string }`
- 路由前缀：`/api/v1/`（外部）、`/internal/`（内部服务间调用）

## 部署

- 本地：Docker 容器 `fitness_php_v2`（端口 9000 内部）
- 生产：Zeabur（阿里云北京），通过 GitHub 推送自动部署
- 环境变量：`.env` 文件（本地）、Zeabur 控制台（生产）

## 关键目录

```
app/Http/Controllers/   # API 控制器
app/Services/            # 业务逻辑层
app/Models/              # Eloquent 模型
routes/api.php           # API 路由
routes/internal.php      # 内部服务路由
database/migrations/     # 数据库迁移（禁止 fresh/refresh）
```
