# 用户设置 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/user-settings.php

## 概述

用户设置 API 提供用户偏好设置、账号安全管理、手机号绑定、头像上传、版本信息查询等功能。支持修改密码、注销账号、手机号绑定/解绑/更换等操作。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 限流 |
|------|------|------|------|------|
| GET | /api/settings | 获取用户设置 | Bearer Token | 无 |
| PUT | /api/settings | 更新用户设置 | Bearer Token | 无 |
| POST | /api/user/change-password | 修改密码 | Bearer Token | 无 |
| DELETE | /api/user/account | 注销账号 | Bearer Token | 无 |
| GET | /api/user/phone/status | 获取手机号绑定状态 | Bearer Token | 无 |
| POST | /api/user/phone/bind | 绑定手机号 | Bearer Token | 无 |
| POST | /api/user/phone/unbind | 解绑手机号 | Bearer Token | 无 |
| POST | /api/user/phone/change | 更换手机号 | Bearer Token | 无 |
| POST | /api/user/phone/send-bind-code | 发送绑定验证码 | Bearer Token | 无 |
| GET | /api/version | 获取版本信息 | Bearer Token | 无 |
| POST | /api/users/avatar | 上传头像 | Bearer Token | 无 |

## 详细说明

### 获取用户设置
- 路径: GET /api/settings
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "success": true,
  "data": {
    "theme": "system",
    "language": "zh-CN",
    "notification_enabled": true,
    "reminder_time": "08:00"
  },
  "message": "获取设置成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 更新用户设置
- 路径: PUT /api/settings
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| theme | string | 否 | 主题 | light/dark/system |
| language | string | 否 | 语言 | 最多10字符 |
| notification_enabled | boolean | 否 | 是否启用通知 | true/false |
| reminder_time | string | 否 | 提醒时间 | HH:mm 格式 |

- 响应示例:
```json
{
  "success": true,
  "data": {
    "theme": "dark",
    "language": "zh-CN",
    "notification_enabled": false,
    "reminder_time": "09:00"
  },
  "message": "设置更新成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 参数验证失败 | 请求参数不符合规则 |

---

### 修改密码
- 路径: POST /api/user/change-password
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| current_password | string | 是 | 当前密码 | 非空字符串 |
| new_password | string | 是 | 新密码 | 至少6位 |
| new_password_confirmation | string | 是 | 新密码确认 | 与 new_password 一致 |

- 响应示例:
```json
{
  "success": true,
  "data": null,
  "message": "密码修改成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 当前密码不正确 | 密码验证失败 |
| 422 | 两次输入的新密码不一致 | 密码确认不匹配 |
| 422 | 新密码至少6个字符 | 密码长度不足 |

---

### 注销账号
- 路径: DELETE /api/user/account
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| password | string | 是 | 密码确认 | 非空字符串 |

- 响应示例:
```json
{
  "success": true,
  "data": null,
  "message": "账号已注销"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 请输入密码确认注销 | 未提供密码 |
| 422 | 密码不正确 | 密码验证失败 |

---

### 获取手机号绑定状态
- 路径: GET /api/user/phone/status
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "success": true,
  "data": {
    "has_phone": true,
    "phone": "138****8000",
    "phone_verified": true,
    "phone_bound_at": "2026-02-28T10:00:00"
  },
  "message": "获取状态成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 绑定手机号
- 路径: POST /api/user/phone/bind
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| phone | string | 是 | 手机号 | 中国手机号格式 |
| code | string | 是 | 验证码 | 6位数字 |

- 响应示例:
```json
{
  "success": true,
  "data": {
    "phone": "138****8000",
    "phone_verified": true
  },
  "message": "手机号绑定成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 您已绑定手机号，如需更换请使用更换功能 | 已绑定 |
| 422 | 手机号格式不正确 | 手机号不符合规则 |
| 422 | 验证码必须是6位 | 验证码长度错误 |
| 422 | 该手机号已被其他账号绑定 | 手机号已被使用 |
| 422 | 验证码错误或已过期 | 验证码验证失败 |

---

### 解绑手机号
- 路径: POST /api/user/phone/unbind
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| password | string | 是 | 密码确认 | 非空字符串 |

- 响应示例:
```json
{
  "success": true,
  "data": null,
  "message": "手机号已解绑"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 未绑定手机号 | 没有绑定手机号 |
| 422 | 请输入密码确认解绑 | 未提供密码 |
| 422 | 密码错误 | 密码验证失败 |
| 422 | 请先绑定邮箱后再解绑手机号，否则您将无法登录 | 无其他登录方式 |

---

### 更换手机号
- 路径: POST /api/user/phone/change
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| new_phone | string | 是 | 新手机号 | 中国手机号格式 |
| new_code | string | 是 | 新手机验证码 | 6位数字 |
| old_code | string | 否 | 原手机验证码 | 6位数字（已绑定时必填） |

- 响应示例:
```json
{
  "success": true,
  "data": {
    "phone": "138****8001"
  },
  "message": "手机号更换成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 新手机号格式不正确 | 新手机号不符合规则 |
| 422 | 验证码必须是6位 | 验证码长度错误 |
| 422 | 该手机号已被其他账号绑定 | 新手机号已被使用 |
| 422 | 请输入原手机号验证码 | 已绑定时未提供原验证码 |
| 422 | 原手机号验证失败 | 原验证码错误 |
| 422 | 新手机号验证失败 | 新验证码错误 |

---

### 发送绑定验证码
- 路径: POST /api/user/phone/send-bind-code
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| phone | string | 是 | 手机号 | 中国手机号格式 |

- 响应示例:
```json
{
  "success": true,
  "data": {
    "expires_at": "2026-02-28T10:10:00Z"
  },
  "message": "验证码已发送"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 手机号格式不正确 | 手机号不符合规则 |
| 422 | 该手机号已被其他账号绑定 | 手机号已被使用 |

---

### 获取版本信息
- 路径: GET /api/version
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "success": true,
  "data": {
    "version": "1.6.3",
    "api_version": "2.0.0"
  },
  "message": "获取版本信息成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 上传头像
- 路径: POST /api/users/avatar
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| avatar | file | 是 | 头像图片 | jpg/jpeg/png/webp，最大2MB |

- 响应示例:
```json
{
  "success": true,
  "data": {
    "avatar_url": "/storage/avatars/1.jpg"
  },
  "message": "头像上传成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 422 | 请选择头像图片 | 未上传文件 |
| 422 | 文件必须是图片 | 文件类型错误 |
| 422 | 仅支持 jpg、png、webp 格式 | 不支持的图片格式 |
| 422 | 图片大小不能超过 2MB | 文件过大 |
