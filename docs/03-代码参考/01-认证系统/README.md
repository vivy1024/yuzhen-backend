# 01-认证系统

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-02

本目录包含用户认证、授权、JWT Token管理的代码实现细节。

---

## 📚 文档列表

### [01-JWT认证实现](./01-JWT认证实现.md)
- JWT Token生成和验证
- Token刷新机制
- JwtService实现
- 中间件配置

### [02-短信验证码实现](./02-短信验证码实现.md)
- 阿里云DYPNS集成
- SmsService实现
- AliyunDypnsClient实现
- 频率限制机制
- Redis存储设计

### [03-邮件验证码实现](./03-邮件验证码实现.md)
- SMTP配置（Spacemail）
- EmailService实现
- VerificationCodeMail邮件类
- 邮件模板设计
- 频率限制机制

### [04-社交登录实现](./04-社交登录实现.md)
- 微信登录实现
- QQ登录实现
- OAuth2.0流程
- 第三方用户绑定

### [05-权限中间件](./05-权限中间件.md)
- Authenticate中间件
- 权限检查逻辑
- 路由保护

---

## 🔧 核心组件

### JWT认证
- **JwtService**: JWT Token生成和验证
- **Authenticate中间件**: 请求认证
- **配置文件**: `config/auth.php`

### 短信验证码
- **SmsService**: 短信验证码业务逻辑
- **AliyunDypnsClient**: 阿里云DYPNS客户端
- **SmsController**: 短信验证码API
- **配置文件**: `config/aliyun.php`

### 邮件验证码
- **EmailService**: 邮件验证码业务逻辑
- **VerificationCodeMail**: 邮件类
- **EmailController**: 邮件验证码API
- **配置文件**: `config/mail.php`

### 社交登录
- **WechatService**: 微信登录服务
- **SocialLoginController**: 社交登录API

---

## 📊 数据流程

### JWT认证流程
```
用户登录 → 验证凭证 → 生成JWT Token → 返回Token
    ↓
后续请求 → 携带Token → 中间件验证 → 解析用户信息
```

### 短信验证码流程
```
发送验证码 → 阿里云DYPNS → 用户收到短信
    ↓
提交验证码 → DYPNS验证 → 验证成功 → 登录/注册
```

### 邮件验证码流程
```
发送验证码 → SMTP发送 → 用户收到邮件
    ↓
提交验证码 → Redis验证 → 验证成功 → 登录/注册
```

---

## 🔗 相关文档

- **API文档**: `../../05-API文档/01-认证系统API.md`
- **API文档**: `../../05-API文档/03-短信验证码API.md`
- **API文档**: `../../05-API文档/04-邮件验证码API.md`

---

**维护者**: 薛小川
**最后更新**: 2026-01-02
