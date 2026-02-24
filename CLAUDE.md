# yuzhen-backend 开发规则

> 本文件为后端子项目专属规则，通用规则见根仓库 `CLAUDE.md`。

## 技术栈

- **框架**: Laravel 11 (PHP 8.3)
- **数据库**: MySQL 8.0 (`fitness_mysql` 容器)
- **缓存**: Redis (`fitness_redis` 容器)
- **运行环境**: `fitness_php_v2` 容器 + `fitness_nginx_v2` 反向代理
- **认证**: Laravel Sanctum (JWT Bearer Token)

## 测试命令

```bash
# 单元测试（必须在容器内运行）
docker exec fitness_php_v2 php artisan test --filter=具体测试类

# 全量测试
docker exec fitness_php_v2 php artisan test
```

## API 响应规范

所有 Controller 必须使用统一响应格式：

```php
// ✅ 正确：继承 BaseController，使用 $this->success() / $this->fail()
return $this->success($data, '操作成功');

// ❌ 错误：直接 response()->json()
return response()->json(['status' => 'ok']);
```

统一格式：`{ code: int, data: any, msg: string }`
- 200 成功 | 401 未授权 | 403 权限不足 | 422 验证失败 | 429 限流 | 500 服务器错误

## 代码规范

- Controller 必须使用 FormRequest 验证输入，禁止直接 `$request->input()` 无验证
- 数据库查询使用 Eloquent ORM，`DB::raw()` 必须参数绑定
- 路由前缀：`/api/v1/`（外部）、`/internal/`（内部服务间调用）
- 异常处理使用 `handleException()` 方法，生产环境隐藏技术细节
- 禁止硬编码密钥，使用环境变量

## 部署

- **本地**: Docker 容器 `fitness_php_v2`（端口 9000 内部）
- **生产**: Zeabur（阿里云北京），Git 推送自动部署
- **域名**: api.yuzhen-fitness.cn
- **AI 代理**: `/api/ai/*` 路由转发到 DAML-RAG 内网（jwt.auth → quota.check → internal.jwt.forward）
- **关键环境变量**: `ENABLE_INTERNAL_JWT=true`

## 关键目录

```
app/Http/Controllers/       # API 控制器
app/Http/Requests/          # FormRequest 验证类
app/Services/               # 业务逻辑层
app/Models/                 # Eloquent 模型
app/Infrastructure/Http/    # BaseController + ApiResponse
routes/api.php              # API 路由
routes/internal.php         # 内部服务路由
database/migrations/        # 数据库迁移（禁止 fresh/refresh）
```

## 按需加载参考

| 场景 | 参考文件 |
|------|---------|
| API 响应标准详细版 | `.kiro/steering/api-design.md` |
| Zeabur 生产环境 | `.kiro/steering/zeabur-production.md` |
| Zeabur 环境变量 | `.kiro/steering/zeabur-env-vars.md` |
| 跨端枚举/字段变更 | `.kiro/steering/cross-stack-data-contract.md` |
