# 06-服务层

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含业务逻辑封装、数据处理、服务层架构的代码实现细节。

---

## 📚 文档列表

### [01-服务层架构](./01-服务层架构.md)
- 服务层设计原则
- 服务类组织结构
- 依赖注入模式
- 服务层最佳实践

---

## 🔧 核心服务

### 认证服务
- **JwtService**: JWT Token生成和验证
- **SmsService**: 短信验证码服务
- **EmailService**: 邮件验证码服务
- **WechatService**: 微信登录服务

### 用户服务
- **UserService**: 用户管理业务逻辑
- **UserProfileService**: 用户档案管理

### 训练服务
- **TrainingPlanService**: 训练计划管理
- **TrainingSessionService**: 训练记录管理
- **ExerciseService**: 动作库查询

### AI服务
- **ChatTopicService**: 话题管理
- **ChatSessionService**: 消息历史管理
- **DamlRagService**: DAML-RAG集成

---

## 📊 服务层架构

### 设计原则

1. **单一职责**: 每个服务类只负责一个业务领域
2. **依赖注入**: 通过构造函数注入依赖
3. **接口隔离**: 定义清晰的服务接口
4. **错误处理**: 统一的异常处理机制

### 目录结构

```
app/Services/
├── Auth/
│   ├── JwtService.php
│   ├── SmsService.php
│   ├── EmailService.php
│   └── WechatService.php
├── User/
│   ├── UserService.php
│   └── UserProfileService.php
├── Training/
│   ├── TrainingPlanService.php
│   ├── TrainingSessionService.php
│   └── ExerciseService.php
└── AI/
    ├── ChatTopicService.php
    ├── ChatSessionService.php
    └── DamlRagService.php
```

---

## 🔄 工作流程

### 服务调用流程

```
Controller → Service → Repository → Model → Database
    ↓
Response ← Service ← Repository ← Model ← Database
```

### 依赖注入示例

```php
class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    public function show(int $id)
    {
        $user = $this->userService->findById($id);
        return response()->json($user);
    }
}
```

---

## 🔗 相关文档

- [控制器层](../07-控制器层/README.md)
- [数据库层](../08-数据库层/README.md)
- [API文档](../../05-API文档/README.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
