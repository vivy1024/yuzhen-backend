# 会员系统 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/membership.php

## 概述

会员系统模块提供完整的会员等级管理和订单处理功能，支持：
- 会员等级查询和配置管理
- 用户会员信息查询和权限检查
- 订单创建、查询、取消和删除
- 支付截图上传和代理获取
- 收款码获取
- 会员统计（管理员）

部分端点无需认证（公开端点），其他端点需要 JWT 认证。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/membership/config | 获取会员系统配置 | ❌ | - |
| GET | /api/membership/tiers | 获取所有会员等级 | ❌ | - |
| GET | /api/membership/plans | 获取可购买的会员套餐 | ❌ | - |
| GET | /api/membership/orders/{orderNo}/proof-image | 获取支付截图（代理） | ❌ | - |
| GET | /api/membership/current | 获取当前用户会员信息 | ✅ | - |
| POST | /api/membership/check-permission | 检查用户权限 | ✅ | - |
| POST | /api/membership/orders | 创建订单 | ✅ | - |
| GET | /api/membership/orders | 获取用户订单列表 | ✅ | - |
| GET | /api/membership/orders/{orderNo} | 查询订单详情 | ✅ | - |
| POST | /api/membership/orders/{orderId}/cancel | 取消订单 | ✅ | - |
| DELETE | /api/membership/orders/{orderId} | 删除订单 | ✅ | - |
| POST | /api/membership/orders/{orderNo}/upload-proof | 上传支付截图 | ✅ | - |
| GET | /api/membership/payment-qrcodes | 获取收款码 | ✅ | - |
| GET | /api/membership/stats | 获取会员统计 | ✅ | admin |

## 详细说明

### 获取会员系统配置
- **路径**: GET /api/membership/config
- **认证**: 无需认证
- **中间件**: 无
- **说明**: 获取会员系统的前端配置，用于控制 UI 显示

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取配置成功",
  "data": {
    "system_enabled": true,
    "membership_tiers": ["free", "warmheart", "energy"],
    "features": {
      "ai_chat": true,
      "training_plans": true,
      "nutrition_plans": true
    }
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | Internal Server Error | 服务器错误 |

---

### 获取所有会员等级
- **路径**: GET /api/membership/tiers
- **认证**: 无需认证
- **中间件**: 无
- **说明**: 获取系统中所有可用的会员等级。如果会员系统禁用，返回空列表

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取会员等级成功",
  "data": {
    "memberships": [
      {
        "id": 1,
        "slug": "free",
        "name": "免费版",
        "description": "基础功能体验",
        "price": 0,
        "features": {
          "daily_quota": 10,
          "ai_chat": true,
          "training_plans": true
        }
      },
      {
        "id": 2,
        "slug": "warmheart",
        "name": "暖心版",
        "description": "增强功能",
        "price": 29.99,
        "features": {
          "daily_quota": 50,
          "ai_chat": true,
          "training_plans": true,
          "nutrition_plans": true
        }
      },
      {
        "id": 3,
        "slug": "energy",
        "name": "能量版",
        "description": "完整功能",
        "price": 99.99,
        "features": {
          "daily_quota": 200,
          "ai_chat": true,
          "training_plans": true,
          "nutrition_plans": true,
          "credit_sharing": true
        }
      }
    ],
    "count": 3,
    "system_enabled": true
  }
}
```

**系统禁用时的响应**:
```json
{
  "code": 200,
  "msg": "会员系统暂未开放",
  "data": {
    "memberships": [],
    "count": 0,
    "system_enabled": false,
    "message": "会员系统暂未开放，当前为免费体验模式"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | Internal Server Error | 服务器错误 |

---

### 获取可购买的会员套餐
- **路径**: GET /api/membership/plans
- **认证**: 无需认证（已登录用户返回个性化套餐）
- **中间件**: 无
- **说明**: 获取所有激活的会员套餐。已登录用户根据首充使用情况返回套餐，未登录用户返回所有套餐

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取套餐列表成功",
  "data": {
    "plans": [
      {
        "id": 1,
        "membership_id": 2,
        "name": "暖心版月卡",
        "description": "30天暖心版会员",
        "price": 29.99,
        "duration_days": 30,
        "is_first_charge": false
      },
      {
        "id": 2,
        "membership_id": 3,
        "name": "能量版年卡",
        "description": "365天能量版会员",
        "price": 199.99,
        "duration_days": 365,
        "is_first_charge": false
      }
    ],
    "count": 2,
    "system_enabled": true
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | Internal Server Error | 服务器错误 |

---

### 获取支付截图（代理）
- **路径**: GET /api/membership/orders/{orderNo}/proof-image
- **认证**: 无需认证
- **中间件**: 无
- **说明**: 通过 API 返回支付截图，绕过浏览器 CORS 限制。返回图片二进制数据

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| orderNo | string | ✅ | 订单号 |

**响应**:
- Content-Type: image/jpeg 或 image/png
- 图片二进制数据
- Cache-Control: public, max-age=86400

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 订单不存在或截图不存在 |
| 500 | Internal Server Error | 获取图片失败 |

---

### 获取当前用户会员信息
- **路径**: GET /api/membership/current
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取当前登录用户的会员信息。如果会员系统禁用，返回统一限制信息

**响应示例**（会员系统启用）:
```json
{
  "code": 200,
  "msg": "获取会员信息成功",
  "data": {
    "id": 1,
    "user_id": 123,
    "membership_id": 2,
    "tier": "warmheart",
    "tier_name": "暖心版",
    "status": "active",
    "expires_at": "2026-03-28T23:59:59Z",
    "system_enabled": true,
    "features": {
      "daily_quota": 50,
      "ai_chat": true,
      "training_plans": true,
      "nutrition_plans": true
    }
  }
}
```

**响应示例**（会员系统禁用）:
```json
{
  "code": 200,
  "msg": "获取用户限制成功",
  "data": {
    "tier": "unified",
    "tier_name": "体验用户",
    "system_enabled": false,
    "limits": {
      "daily_quota": 10,
      "ai_chat": true,
      "training_plans": true
    },
    "message": "当前为免费体验模式，所有用户享受统一服务"
  }
}
```

**响应示例**（未购买会员）:
```json
{
  "code": 200,
  "msg": "暂无会员信息",
  "data": {
    "tier": "free",
    "system_enabled": true,
    "message": "当前为免费版，升级即可解锁更多功能"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 用户未认证或 Token 过期 |
| 500 | Internal Server Error | 服务器错误 |

---

### 检查用户权限
- **路径**: POST /api/membership/check-permission
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 检查当前用户是否有权限使用某个功能

**请求参数**:

| 参数 | 类型 | 必填 | 说明 | 示例 |
|------|------|------|------|------|
| feature | string | ✅ | 功能名称 | ai_chat, training_plans, nutrition_plans |

**请求示例**:
```json
{
  "feature": "ai_chat"
}
```

**响应示例**:
```json
{
  "code": 200,
  "msg": "权限检查完成",
  "data": {
    "feature": "ai_chat",
    "has_permission": true
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 创建订单
- **路径**: POST /api/membership/orders
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 创建新的会员订单

**请求参数**:

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| membership_id | integer | ✅ | exists:memberships,id | 会员等级 ID |

**请求示例**:
```json
{
  "membership_id": 2
}
```

**响应示例**:
```json
{
  "code": 200,
  "msg": "订单创建成功",
  "data": {
    "id": 1,
    "order_no": "ORD20260228001",
    "user_id": 123,
    "membership_id": 2,
    "membership_name": "暖心版",
    "amount": 29.99,
    "status": "pending",
    "payment_method": null,
    "payment_proof_url": null,
    "created_at": "2026-02-28T10:00:00Z",
    "expires_at": null
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 422 | Validation Error | 参数验证失败 |
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取用户订单列表
- **路径**: GET /api/membership/orders
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取当前用户的所有订单，按创建时间倒序排列，分页返回

**查询参数**:

| 参数 | 类型 | 必填 | 说明 | 默认值 |
|------|------|------|------|--------|
| page | integer | 否 | 页码 | 1 |
| per_page | integer | 否 | 每页数量 | 10 |

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取订单列表成功",
  "data": {
    "orders": [
      {
        "id": 1,
        "order_no": "ORD20260228001",
        "user_id": 123,
        "membership_id": 2,
        "membership_name": "暖心版",
        "amount": 29.99,
        "status": "pending",
        "payment_method": null,
        "payment_proof_url": null,
        "created_at": "2026-02-28T10:00:00Z"
      }
    ],
    "total": 1
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 查询订单详情
- **路径**: GET /api/membership/orders/{orderNo}
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 查询单个订单的详细信息

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| orderNo | string | ✅ | 订单号 |

**响应示例**:
```json
{
  "code": 200,
  "msg": "查询订单成功",
  "data": {
    "id": 1,
    "order_no": "ORD20260228001",
    "user_id": 123,
    "membership_id": 2,
    "membership_name": "暖心版",
    "amount": 29.99,
    "status": "pending",
    "payment_method": null,
    "payment_proof_url": null,
    "created_at": "2026-02-28T10:00:00Z",
    "expires_at": null
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 订单不存在或不属于当前用户 |
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 取消订单
- **路径**: POST /api/membership/orders/{orderId}/cancel
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 取消待支付或审核中的订单

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| orderId | integer | ✅ | 订单 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "订单已取消",
  "data": null
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 订单不存在或不属于当前用户 |
| 400 | Bad Request | 订单状态不允许取消 |
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 删除订单
- **路径**: DELETE /api/membership/orders/{orderId}
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 删除待支付状态的订单。只能删除待支付订单

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| orderId | integer | ✅ | 订单 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "订单已删除",
  "data": null
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 订单不存在或不属于当前用户 |
| 400 | Bad Request | 只能删除待支付订单 |
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 上传支付截图
- **路径**: POST /api/membership/orders/{orderNo}/upload-proof
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 上传支付截图，用于收款码支付方式。支持 JPG、PNG 格式，最大 5MB

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| orderNo | string | ✅ | 订单号 |

**请求参数**:

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| payment_proof | file | ✅ | image, max:5120 | 支付截图（最大 5MB） |
| pay_method | string | ✅ | in:wechat,alipay | 支付方式 |

**请求示例** (multipart/form-data):
```
payment_proof: [图片文件]
pay_method: wechat
```

**响应示例**:
```json
{
  "code": 200,
  "msg": "上传成功",
  "data": {
    "order_no": "ORD20260228001",
    "status": "reviewing",
    "payment_proof_url": "http://localhost:8000/storage/payment_proofs/ORD20260228001_1709024400.jpg",
    "message": "截图上传成功，请等待审核"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 订单不存在或不属于当前用户 |
| 422 | Validation Error | 参数验证失败（文件格式、大小等） |
| 400 | Bad Request | 订单状态不允许上传截图 |
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取收款码
- **路径**: GET /api/membership/payment-qrcodes
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取微信和支付宝收款码 URL

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取收款码成功",
  "data": {
    "wechat": "http://localhost:8000/wechat-qrcode.jpg",
    "alipay": "http://localhost:8000/alipay-qrcode.jpg"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取会员统计（管理员）
- **路径**: GET /api/membership/stats
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth, admin
- **说明**: 获取会员系统的统计数据（仅管理员可访问）

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取统计成功",
  "data": {
    "total_users": 1000,
    "total_members": 350,
    "membership_breakdown": {
      "free": 650,
      "warmheart": 200,
      "energy": 150
    },
    "total_revenue": 15999.50,
    "pending_orders": 25,
    "completed_orders": 500,
    "average_membership_duration_days": 180
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 403 | Forbidden | 无管理员权限 |
| 401 | Unauthorized | 用户未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

## 通用说明

### 认证方式
需要认证的端点使用 Bearer Token 认证，在请求头中添加：
```
Authorization: Bearer {token}
```

### 响应格式
所有 API 响应遵循统一格式：
```json
{
  "code": 200,
  "msg": "操作成功",
  "data": {}
}
```

- `code`: HTTP 状态码
- `msg`: 操作消息
- `data`: 响应数据（成功时返回数据，失败时为 null）

### 订单状态
订单可能的状态值：
- `pending`: 待支付
- `reviewing`: 审核中（已上传截图）
- `completed`: 已完成
- `cancelled`: 已取消
- `expired`: 已过期

### 支付方式
支持的支付方式：
- `wechat`: 微信支付
- `alipay`: 支付宝支付

### 错误处理
- 所有错误响应包含 `code` 和 `msg` 字段
- 验证错误返回 422 状态码
- 未认证返回 401 状态码
- 权限不足返回 403 状态码
- 资源不存在返回 404 状态码
- 业务逻辑错误返回 400 状态码
