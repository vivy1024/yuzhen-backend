# 认证系统 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/auth.php

## 概述

认证系统 API 提供用户注册、登录、验证码管理、Token 刷新、权限查询等功能。支持邮箱注册、手机号注册、邮箱登录、手机号登录、密码重置等多种认证方式。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 限流 |
|------|------|------|------|------|
| POST | /api/auth/register | 邮箱注册 | 无 | 无 |
| POST | /api/auth/register/phone | 手机号注册 | 无 | 无 |
| POST | /api/auth/login | 邮箱/用户名/手机号登录 | 无 | 5次/分钟 |
| POST | /api/auth/refresh | 刷新 Token | 无 | 无 |
| POST | /api/auth/logout | 登出 | Bearer Token | 无 |
| POST | /api/auth/sms/send | 发送短信验证码 | 无 | 业务层限流 |
| POST | /api/auth/sms/verify | 验证短信验证码 | 无 | 无 |
| POST | /api/auth/sms/login | 手机号验证码登录 | 无 | 无 |
| GET | /api/auth/sms/check-phone | 检查手机号是否已注册 | 无 | 无 |
| POST | /api/auth/email/send | 发送邮箱验证码 | 无 | 业务层限流 |
| POST | /api/auth/email/verify | 验证邮箱验证码 | 无 | 无 |
| POST | /api/auth/email/login | 邮箱验证码登录 | 无 | 无 |
| POST | /api/auth/email/reset-password | 重置密码 | 无 | 无 |
| GET | /api/auth/email/check | 检查邮箱是否已注册 | 无 | 无 |
| GET | /api/auth/permissions | 获取当前用户权限 | Bearer Token | 无 |
| GET | /api/auth/permissions/check | 检查权限并建议刷新 | Bearer Token | 无 |
| POST | /api/auth/permissions/refresh-token | 强制刷新 Token（权限变更后调用） | Bearer Token | 无 |

## 详细说明

### 邮箱注册
- 路径: POST /api/auth/register
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| nickname | string | 是 | 昵称 | 2-50字符，唯一 |
| email | string | 是 | 邮箱 | 有效邮箱格式，唯一 |
| email_code | string | 是 | 邮箱验证码 | 6位数字 |
| password | string | 是 | 密码 | 至少6位，需确认 |
| password_confirmation | string | 是 | 密码确认 | 与 password 一致 |
| phone | string | 否 | 手机号 | 中国手机号格式 |
| gender | string | 否 | 性别 | male/female |
| age | integer | 否 | 年龄 | 13-120 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "注册成功",
  "data": {
    "user": {
      "id": 1,
      "name": "用户昵称",
      "email": "user@example.com",
      "phone": null,
      "avatar": null,
      "created_at": "2026-02-28T10:00:00Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 参数验证失败 | 请求参数不符合规则 |
| 422 | 邮箱已被注册 | 邮箱已存在 |
| 422 | 昵称已存在 | 昵称已被使用 |
| 422 | 验证码错误 | 邮箱验证码不正确或已过期 |

---

### 手机号注册
- 路径: POST /api/auth/register/phone
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| nickname | string | 是 | 昵称 | 2-50字符，唯一 |
| phone | string | 是 | 手机号 | 中国手机号格式，唯一 |
| phone_code | string | 是 | 手机验证码 | 6位数字 |
| password | string | 是 | 密码 | 至少6位，需确认 |
| password_confirmation | string | 是 | 密码确认 | 与 password 一致 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "注册成功",
  "data": {
    "user": {
      "id": 2,
      "name": "用户昵称",
      "email": "phone_13800138000@placeholder.local",
      "phone": "13800138000",
      "avatar": null,
      "created_at": "2026-02-28T10:00:00Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 参数验证失败 | 请求参数不符合规则 |
| 422 | 该手机号已被注册 | 手机号已存在 |
| 422 | 该昵称已被使用 | 昵称已被使用 |
| 422 | 验证码错误 | 手机验证码不正确或已过期 |

---

### 邮箱/用户名/手机号登录
- 路径: POST /api/auth/login
- 认证: 无
- 中间件: throttle:5,1（同一IP 5次/分钟限制）
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| identifier | string | 是 | 用户名/邮箱/手机号 | 非空字符串 |
| password | string | 是 | 密码 | 至少6位 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "登录成功",
  "data": {
    "user": {
      "id": 1,
      "name": "用户昵称",
      "email": "user@example.com",
      "phone": "13800138000",
      "avatar": "/storage/avatars/1.jpg",
      "created_at": "2026-02-28T10:00:00Z"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 参数验证失败 | 请求参数不符合规则 |
| 401 | 用户名或密码错误 | 认证失败 |
| 403 | 账号已禁用 | 用户被禁用 |
| 429 | 登录尝试过于频繁 | 超过限流阈值 |

---

### 刷新 Token
- 路径: POST /api/auth/refresh
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| refresh_token | string | 是 | 刷新令牌 | 有效的 JWT Token |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  },
  "msg": "刷新令牌成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 缺少刷新令牌 | 未提供 refresh_token |
| 401 | Token 已过期或无效 | 刷新令牌失效 |

---

### 登出
- 路径: POST /api/auth/logout
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "data": null,
  "msg": "登出成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 发送短信验证码
- 路径: POST /api/auth/sms/send
- 认证: 无
- 中间件: 无（业务层限流）
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| phone | string | 是 | 手机号 | 中国手机号格式 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "expires_at": "2026-02-28T10:10:00Z",
    "wait_seconds": 60
  },
  "msg": "验证码已发送"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 手机号格式不正确 | 手机号不符合规则 |
| 429 | 发送过于频繁，请稍后再试 | 超过发送限制 |
| 429 | 今日发送次数已达上限 | 每日限制 |
| 403 | 账号已被锁定 | 尝试次数过多 |
| 500 | 发送失败 | 短信服务异常 |

---

### 验证短信验证码
- 路径: POST /api/auth/sms/verify
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| phone | string | 是 | 手机号 | 中国手机号格式 |
| code | string | 是 | 验证码 | 6位数字 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "verified": true
  },
  "msg": "验证成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 手机号格式不正确 | 手机号不符合规则 |
| 400 | 验证码错误 | 验证码不正确 |
| 400 | 验证码已过期 | 验证码超时 |
| 403 | 账号已被锁定 | 尝试次数过多 |

---

### 手机号验证码登录
- 路径: POST /api/auth/sms/login
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| phone | string | 是 | 手机号 | 中国手机号格式 |
| code | string | 是 | 验证码 | 6位数字 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "user": {
      "id": 1,
      "name": "用户昵称",
      "email": "user@example.com",
      "phone": "13800138000",
      "avatar": "/storage/avatars/1.jpg"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  },
  "msg": "登录成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 手机号格式不正确 | 手机号不符合规则 |
| 400 | 验证码错误 | 验证码不正确 |
| 404 | 手机号未注册 | 用户不存在 |
| 403 | 账号已禁用 | 用户被禁用 |
| 403 | 账号已被锁定 | 尝试次数过多 |

---

### 检查手机号是否已注册
- 路径: GET /api/auth/sms/check-phone?phone=13800138000
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| phone | string | 是 | 手机号 | 中国手机号格式 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "phone": "13800138000",
    "is_registered": true
  },
  "msg": "查询成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 手机号格式不正确 | 手机号不符合规则 |

---

### 发送邮箱验证码
- 路径: POST /api/auth/email/send
- 认证: 无
- 中间件: 无（业务层限流）
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| email | string | 是 | 邮箱 | 有效邮箱格式，最多255字符 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "expires_at": "2026-02-28T10:10:00Z"
  },
  "msg": "验证码已发送"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 邮箱格式不正确 | 邮箱不符合规则 |
| 429 | 发送过于频繁，请稍后再试 | 超过发送限制 |
| 429 | 今日发送次数已达上限 | 每日限制 |
| 403 | 账号已被锁定 | 尝试次数过多 |
| 500 | 发送失败 | 邮件服务异常 |

---

### 验证邮箱验证码
- 路径: POST /api/auth/email/verify
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| email | string | 是 | 邮箱 | 有效邮箱格式，最多255字符 |
| code | string | 是 | 验证码 | 6位数字 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "verified": true
  },
  "msg": "验证成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 邮箱格式不正确 | 邮箱不符合规则 |
| 400 | 验证码错误 | 验证码不正确 |
| 400 | 验证码已过期 | 验证码超时 |
| 403 | 账号已被锁定 | 尝试次数过多 |

---

### 邮箱验证码登录
- 路径: POST /api/auth/email/login
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| email | string | 是 | 邮箱 | 有效邮箱格式，最多255字符 |
| code | string | 是 | 验证码 | 6位数字 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "user": {
      "id": 1,
      "name": "用户昵称",
      "email": "user@example.com",
      "phone": "13800138000",
      "avatar": "/storage/avatars/1.jpg"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  },
  "msg": "登录成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 邮箱格式不正确 | 邮箱不符合规则 |
| 400 | 验证码错误 | 验证码不正确 |
| 404 | 邮箱未注册 | 用户不存在 |
| 400 | 验证码已过期 | 验证码超时 |
| 403 | 账号已被锁定 | 尝试次数过多 |

---

### 重置密码
- 路径: POST /api/auth/email/reset-password
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| email | string | 是 | 邮箱 | 有效邮箱格式，最多255字符 |
| code | string | 是 | 验证码 | 6位数字 |
| password | string | 是 | 新密码 | 6-32字符 |
| password_confirmation | string | 是 | 密码确认 | 与 password 一致 |

- 响应示例:
```json
{
  "code": 200,
  "data": null,
  "msg": "密码重置成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 邮箱格式不正确 | 邮箱不符合规则 |
| 400 | 验证码错误 | 验证码不正确 |
| 404 | 邮箱未注册 | 用户不存在 |
| 400 | 验证码已过期 | 验证码超时 |
| 403 | 账号已被锁定 | 尝试次数过多 |

---

### 检查邮箱是否已注册
- 路径: GET /api/auth/email/check?email=user@example.com
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| email | string | 是 | 邮箱 | 有效邮箱格式，最多255字符 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "exists": true
  },
  "msg": "查询成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 邮箱格式不正确 | 邮箱不符合规则 |

---

### 获取当前用户权限
- 路径: GET /api/auth/permissions
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "tier": "energy",
    "permissions": ["chat.dag", "chat.agent", "credit.share"],
    "membership": {
      "id": 3,
      "slug": "energy",
      "tier": "energy",
      "name": "能量会员",
      "price": 99,
      "features": {
        "daily_quota": 200,
        "agent_mode": true,
        "credit_share": true
      }
    },
    "user_id": 1
  },
  "msg": "获取权限成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 检查权限并建议刷新
- 路径: GET /api/auth/permissions/check
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "jwt_tier": "free",
    "current_tier": "energy",
    "jwt_permissions": ["chat.dag"],
    "current_permissions": ["chat.dag", "chat.agent", "credit.share"],
    "needs_refresh": true,
    "tier_mismatch": true,
    "permissions_mismatch": true
  },
  "msg": "权限检查完成"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 强制刷新 Token（权限变更后调用）
- 路径: POST /api/auth/permissions/refresh-token
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "msg": "Token已刷新，权限已更新"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
