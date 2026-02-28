# 管理后台 API

> 自动生成 | 对应路由文件: routes/modules/admin.php

## 概述

管理后台 API 为管理员提供用户管理、订单管理、反馈管理、会话评审、运营KPI监控、积分管理、系统监控等功能。所有接口需要管理员权限（JWT Token + admin 角色）。

## 端点列表

### 基础认证

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/verify | 验证管理员身份 | Bearer Token | 无 |

### 用户管理

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/users | 获取用户列表 | Bearer Token | 管理员 |
| GET | /api/admin/users/{id} | 获取用户详情 | Bearer Token | 管理员 |
| PUT | /api/admin/users/{id}/role | 更新用户角色 | Bearer Token | 管理员 |

### 用户积分管理

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/users/{userId}/usage | 获取用户用量统计 | Bearer Token | 管理员 |
| POST | /api/admin/users/{userId}/credits | 添加额外次数 | Bearer Token | 管理员 |
| POST | /api/admin/users/credits/batch | 批量添加额外次数 | Bearer Token | 管理员 |
| GET | /api/admin/credits/config | 获取积分配置 | Bearer Token | 管理员 |

### 订单管理

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/orders/stats | 获取订单统计 | Bearer Token | 管理员 |
| GET | /api/admin/orders/pending | 获取待审核订单 | Bearer Token | 管理员 |
| GET | /api/admin/orders | 获取订单列表 | Bearer Token | 管理员 |
| GET | /api/admin/orders/{id} | 获取订单详情 | Bearer Token | 管理员 |
| POST | /api/admin/orders/{id}/approve | 审核通过 | Bearer Token | 管理员 |
| POST | /api/admin/orders/{id}/reject | 审核拒绝 | Bearer Token | 管理员 |

### 反馈管理

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/feedback/stats | 获取反馈统计 | Bearer Token | 管理员 |
| GET | /api/admin/feedback | 获取反馈列表 | Bearer Token | 管理员 |
| GET | /api/admin/feedback/{id} | 获取反馈详情 | Bearer Token | 管理员 |
| PUT | /api/admin/feedback/{id}/reply | 回复反馈 | Bearer Token | 管理员 |
| PUT | /api/admin/feedback/{id}/status | 更新反馈状态 | Bearer Token | 管理员 |
| PUT | /api/admin/feedback/batch-status | 批量更新状态 | Bearer Token | 管理员 |
| DELETE | /api/admin/feedback/{id} | 删除反馈 | Bearer Token | 管理员 |

### 会话管理（三轨评分）

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/sessions/pending-review | 获取待评审会话 | Bearer Token | 管理员 |
| GET | /api/admin/sessions/reviewed | 获取已评审会话 | Bearer Token | 管理员 |

### 运营 KPI

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/kpi/overview | 今日运营概览 | Bearer Token | 管理员 |
| GET | /api/admin/kpi/growth | 用户增长趋势 | Bearer Token | 管理员 |
| GET | /api/admin/kpi/activity | DAU/MAU活跃度趋势 | Bearer Token | 管理员 |
| GET | /api/admin/kpi/retention | 用户留存率 | Bearer Token | 管理员 |

### 监控指标

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/admin/metrics/query | Prometheus即时查询 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/query_range | Prometheus范围查询 | Bearer Token | 管理员 |
| POST | /api/admin/metrics/batch | 批量查询 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/daml-rag/health | DAML-RAG健康状态 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/daml-rag/metrics | DAML-RAG系统指标 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/daml-rag/streaming | 流式监控统计 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/daml-rag/streaming/recent | 流式会话记录 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/daml-rag/logs | DAML-RAG日志 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/loki/query | Loki日志查询 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/loki/labels | Loki日志标签 | Bearer Token | 管理员 |
| GET | /api/admin/metrics/prometheus/raw | Prometheus原始指标 | Bearer Token | 管理员 |

## 详细说明

### 1. 验证管理员身份

**GET** `/api/admin/verify`

验证当前用户是否为管理员。

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "is_admin": true,
    "role": "admin"
  }
}
```

---

### 2. 获取用户列表

**GET** `/api/admin/users`

获取所有用户列表，支持搜索和角色筛选。

**查询参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| search | string | 搜索关键词（姓名、用户名、邮箱） |
| role | string | 角色筛选：`user`、`expert`、`admin` |
| page | integer | 页码，默认1 |
| per_page | integer | 每页数量，默认20 |

**响应示例**

```json
{
  "code": 200,
  "msg": "获取用户列表成功",
  "data": {
    "users": [
      {
        "id": 1,
        "name": "张三",
        "email": "user@example.com",
        "role": "user",
        "membership_tier": "free"
      }
    ],
    "stats": {
      "total": 1000,
      "members": 150,
      "admins": 5
    },
    "total": 1000,
    "current_page": 1
  }
}
```

---

### 3. 获取用户详情

**GET** `/api/admin/users/{id}`

获取指定用户的详细信息，包括会员状态和档案。

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 用户ID |

**响应示例**

```json
{
  "code": 200,
  "msg": "获取用户详情成功",
  "data": {
    "id": 1,
    "name": "张三",
    "email": "user@example.com",
    "role": "user",
    "membership_tier": "energy",
    "profile": {
      "body_type": "ectomorph",
      "fitness_goals": ["增肌"]
    }
  }
}
```

---

### 4. 更新用户角色

**PUT** `/api/admin/users/{id}/role`

更新指定用户的角色。

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 用户ID |

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| role | string | 是 | 角色：`user`、`expert`、`admin` |

**响应示例**

```json
{
  "code": 200,
  "msg": "角色更新成功",
  "data": {
    "id": 1,
    "role": "expert"
  }
}
```

**错误响应**

```json
{
  "code": 400,
  "msg": "不能修改自己的角色",
  "data": null
}
```

---

### 5. 获取用户用量统计

**GET** `/api/admin/users/{userId}/usage`

获取指定用户的AI对话用量统计。

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| userId | integer | 用户ID |

**响应示例**

```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "user_id": 123,
    "nickname": "张三",
    "email": "user@example.com",
    "usage": {
      "dag_credits": 50,
      "dag_used": 20,
      "dag_remaining": 30,
      "agent_credits": 10,
      "agent_used": 3,
      "agent_remaining": 7
    }
  }
}
```

---

### 6. 添加额外次数

**POST** `/api/admin/users/{userId}/credits`

为用户添加额外的AI对话次数（打赏奖励）。

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| userId | integer | 用户ID |

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| dag_credits | integer | 否 | DAG模式额外次数，0-10000 |
| agent_credits | integer | 否 | Agent模式额外次数，0-1000 |
| reason | string | 否 | 原因说明 |

**请求示例**

```json
{
  "dag_credits": 50,
  "agent_credits": 10,
  "reason": "打赏10元奖励"
}
```

**响应示例**

```json
{
  "code": 200,
  "msg": "添加成功",
  "data": {
    "user_id": 123,
    "added": {
      "dag_credits": 50,
      "agent_credits": 10
    },
    "current_usage": {
      "dag_remaining": 80,
      "agent_remaining": 17
    }
  }
}
```

---

### 7. 批量添加额外次数

**POST** `/api/admin/users/credits/batch`

批量为多个用户添加额外次数。

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| user_ids | array | 是 | 用户ID数组，最多100个 |
| dag_credits | integer | 否 | DAG模式额外次数 |
| agent_credits | integer | 否 | Agent模式额外次数 |
| reason | string | 否 | 原因说明 |

**响应示例**

```json
{
  "code": 200,
  "msg": "成功为 5 个用户添加额外次数",
  "data": {
    "total": 5,
    "success": 5,
    "failed": 0,
    "failed_users": []
  }
}
```

---

### 8. 获取反馈统计

**GET** `/api/admin/feedback/stats`

获取反馈统计数据，包括各状态和类型的数量。

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "total": 100,
    "pending": 20,
    "processing": 10,
    "resolved": 60,
    "closed": 10,
    "today": 5,
    "by_type": {
      "feature": 30,
      "bug": 40,
      "question": 20,
      "other": 10
    }
  }
}
```

---

### 9. 获取反馈列表

**GET** `/api/admin/feedback`

获取所有用户提交的反馈列表，支持状态和类型筛选。

**查询参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| status | string | 状态筛选：`pending`、`processing`、`resolved`、`closed`、`all` |
| type | string | 类型筛选：`feature`、`bug`、`question`、`other`、`all` |
| search | string | 搜索关键词 |
| per_page | integer | 每页数量，默认20 |

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "user_id": 123,
        "type": "bug",
        "content": "训练计划无法保存",
        "status": "pending",
        "created_at": "2026-01-20T10:30:00+08:00"
      }
    ],
    "total": 100,
    "current_page": 1,
    "per_page": 20
  }
}
```

---

### 10. 回复反馈

**PUT** `/api/admin/feedback/{id}/reply`

管理员回复用户反馈并更新状态。

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 反馈ID |

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | string | 是 | 状态：`pending`、`processing`、`resolved`、`closed` |
| reply | string | 是 | 回复内容，最多2000字 |

**响应示例**

```json
{
  "code": 200,
  "msg": "回复成功",
  "data": {
    "id": 1,
    "status": "resolved",
    "reply": "已修复",
    "reply_at": "2026-01-21T10:00:00+08:00"
  }
}
```

---

### 11. 获取待评审会话

**GET** `/api/admin/sessions/pending-review`

获取需要专家评审的会话列表（有用户评分但无专家评分）。

**查询参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| page | integer | 页码 |
| per_page | integer | 每页数量 |

**响应示例**

```json
{
  "code": 200,
  "msg": "获取待评审会话成功",
  "data": {
    "sessions": [
      {
        "id": 1,
        "session_id": "uuid-xxx",
        "user_query": "如何增肌？",
        "llm_response": "建议...",
        "model_used": "sonnet-4.5",
        "ux_clarity": 4,
        "ux_practicality": 5,
        "ux_detail": 4,
        "ux_friendliness": 5,
        "ux_satisfaction": 4,
        "profile_utilization_rate": 0.8,
        "goal_alignment": 0.9,
        "overall_score": 88,
        "user": {
          "id": 123,
          "name": "张三"
        },
        "created_at": "2026-01-20T10:30:00+08:00"
      }
    ],
    "total": 50,
    "current_page": 1
  }
}
```

---

### 12. 今日运营概览

**GET** `/api/admin/kpi/overview`

获取今日的核心运营指标。

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "dau": 150,
    "mau": 800,
    "stickiness_pct": 18.8,
    "new_users_today": 20,
    "total_users": 1000,
    "active_members": 150,
    "today_queries": {
      "dag": 500,
      "agent": 100,
      "total": 600
    },
    "today_revenue": 299.00
  }
}
```

---

### 13. 用户增长趋势

**GET** `/api/admin/kpi/growth`

获取用户增长趋势数据。

**查询参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| period | string | 时间周期：`7d`、`30d`、`90d`，默认30d |

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "period": "30d",
    "daily": [
      {
        "date": "2026-01-01",
        "new_users": 10,
        "total_users": 900
      }
    ],
    "summary": {
      "total_new": 200,
      "total_members": 30,
      "conversion_rate_pct": 15.0
    }
  }
}
```

---

### 14. Prometheus 即时查询

**GET** `/api/admin/metrics/query`

代理Prometheus即时查询。

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| query | string | 是 | PromQL查询语句 |
| time | string | 否 | 时间戳 |

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "status": "success",
    "data": {
      "resultType": "vector",
      "result": [
        {
          "metric": {"__name__": "up"},
          "value": [1234567890, "1"]
        }
      ]
    }
  }
}
```

---

### 15. DAML-RAG 健康状态

**GET** `/api/admin/metrics/daml-rag/health`

获取 DAML-RAG AI服务的健康状态。

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "status": "healthy",
    "version": "1.0.0",
    "uptime": 86400
  }
}
```

---

### 16. DAML-RAG 日志

**GET** `/api/admin/metrics/daml-rag/logs`

获取 DAML-RAG 服务日志。

**查询参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| lines | integer | 返回行数，默认200 |
| level | string | 日志级别：`all`、`INFO`、`WARN`、`ERROR` |

**响应示例**

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "logs": [
      {
        "id": "log_xxx",
        "timestamp": "2026-01-20T10:30:00+08:00",
        "level": "INFO",
        "message": "Request processed",
        "component": "daml_rag"
      }
    ],
    "stats": {
      "total": 100,
      "error": 5,
      "warn": 10,
      "info": 80,
      "debug": 5
    }
  }
}
```

---

### 17. Loki 日志查询

**GET** `/api/admin/metrics/loki/query`

从 Loki 日志系统查询日志。

**查询参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| query | string | LogQL查询语句 |
| limit | integer | 返回数量，默认100 |
| start | string | 开始时间 |
| end | string | 结束时间 |

---

## 错误码

| 错误码 | 说明 |
|--------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未登录或Token无效 |
| 403 | 无管理员权限 |
| 404 | 资源不存在 |
| 422 | 验证失败 |
| 500 | 服务器内部错误 |