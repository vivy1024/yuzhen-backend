# 07-控制器层

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含API端点定义、请求处理、响应格式的代码实现细节。

---

## 📚 文档列表

### [01-控制器架构](./01-控制器架构.md)
- 控制器设计原则
- RESTful API规范
- 路由组织结构
- 中间件配置

### [02-API响应格式](./02-API响应格式.md)
- 统一响应格式
- 错误处理机制
- 状态码规范
- 数据转换规则

---

## 🔧 核心控制器

### 认证控制器
- **AuthController**: 登录、注册、登出
- **SmsController**: 短信验证码
- **EmailController**: 邮件验证码
- **SocialLoginController**: 社交登录

### 用户控制器
- **UserController**: 用户信息管理
- **UserProfileController**: 用户档案管理

### 训练控制器
- **TrainingPlanController**: 训练计划管理
- **TrainingSessionController**: 训练记录管理
- **ExerciseController**: 动作库查询

### AI控制器
- **ChatController**: AI对话
- **ChatTopicController**: 话题管理
- **GraphRagController**: GraphRAG查询

---

## 📊 控制器架构

### 设计原则

1. **薄控制器**: 控制器只负责请求处理和响应
2. **业务逻辑分离**: 业务逻辑放在服务层
3. **统一响应**: 使用统一的响应格式
4. **请求验证**: 使用FormRequest验证请求

### 目录结构

```
app/Http/Controllers/
├── Auth/
│   ├── AuthController.php
│   ├── SmsController.php
│   ├── EmailController.php
│   └── SocialLoginController.php
├── User/
│   ├── UserController.php
│   └── UserProfileController.php
├── Training/
│   ├── TrainingPlanController.php
│   ├── TrainingSessionController.php
│   └── ExerciseController.php
└── AI/
    ├── ChatController.php
    ├── ChatTopicController.php
    └── GraphRagController.php
```

---

## 🔄 请求处理流程

### 标准流程

```
HTTP Request → 路由 → 中间件 → 控制器 → 服务层 → 数据库
    ↓
HTTP Response ← 控制器 ← 服务层 ← 数据库
```

### 控制器示例

```php
class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    public function show(int $id)
    {
        try {
            $user = $this->userService->findById($id);
            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $user
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '用户不存在',
                'data' => null
            ], 404);
        }
    }
}
```

---

## 📡 API响应格式

### 成功响应

```json
{
  "code": 200,
  "msg": "操作成功",
  "data": { ... },
  "timestamp": "2026-01-17T10:00:00Z"
}
```

### 错误响应

```json
{
  "code": 400,
  "msg": "参数验证失败",
  "data": null,
  "errors": {
    "email": ["邮箱格式不正确"]
  },
  "timestamp": "2026-01-17T10:00:00Z"
}
```

---

## 🔗 相关文档

- [服务层](../06-服务层/README.md)
- [API文档](../../05-API文档/README.md)
- [请求验证](../../04-开发指南/请求验证指南.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
