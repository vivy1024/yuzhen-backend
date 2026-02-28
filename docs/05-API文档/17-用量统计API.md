# 用量统计 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/usage.php

## 概述

用量统计 API 提供用户 AI 查询用量的查询、检查和额度管理功能。支持获取今日用量、额外额度余额、检查查询权限、增加用量计数以及用量历史统计等功能。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/usage/today | 获取今日用量统计 | Bearer Token | 无 |
| GET | /api/usage/credits | 获取额外额度余额 | Bearer Token | 无 |
| POST | /api/usage/check | 检查是否可执行查询 | Bearer Token | 无 |
| POST | /api/usage/increment | 增加用量计数 | Bearer Token | 无 |
| GET | /api/usage/history | 获取用量历史统计 | Bearer Token | 无 |

## 详细说明

### 获取今日用量统计
- 路径: GET /api/usage/today
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "dag_used": 3,
    "dag_limit": 10,
    "dag_remaining": 7,
    "agent_used": 1,
    "agent_limit": 3,
    "agent_remaining": 2,
    "dag_credits": 5,
    "agent_credits": 2,
    "date": "2026-01-11",
    "has_warning": false,
    "warnings": []
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 请先登录 | 用户未认证 |
| 500 | 获取失败 | 服务器内部错误 |

---

### 获取额外额度余额
- 路径: GET /api/usage/credits
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "dag_credits": 5,
    "agent_credits": 2,
    "total_credits": 7
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 请先登录 | 用户未认证 |

---

### 检查是否可执行查询
- 路径: POST /api/usage/check
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| mode | string | 是 | 查询模式 | dag/agent/DAG/Agent |

- 响应示例:
```json
{
  "code": 200,
  "msg": "检查通过",
  "data": {
    "allowed": true,
    "remaining": 7,
    "use_credits": false,
    "message": "可以执行查询（剩余7次）"
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 请先登录 | 用户未认证 |
| 422 | 参数验证失败 | mode 参数不符合规则 |
| 429 | 用量已达上限 | 次数已用完 |

---

### 增加用量计数
- 路径: POST /api/usage/increment
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 此接口通常由 DAML-RAG 服务在查询完成后调用

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| mode | string | 是 | 查询模式 | dag/agent/DAG/Agent |

- 响应示例:
```json
{
  "code": 200,
  "msg": "计数成功",
  "data": {
    "success": true,
    "used_credits": false,
    "new_count": 4,
    "remaining": 6,
    "message": "查询成功（剩余6次）"
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 请先登录 | 用户未认证 |
| 422 | 参数验证失败 | mode 参数不符合规则 |
| 429 | 用量已达上限 | 额度不足 |

---

### 获取用量历史统计
- 路径: GET /api/usage/history
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| days | integer | 否 | 统计天数 | 1-365，默认30 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "period_days": 30,
    "total_dag_queries": 150,
    "total_agent_queries": 45,
    "avg_dag_per_day": 5.0,
    "avg_agent_per_day": 1.5,
    "daily_stats": []
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 请先登录 | 用户未认证 |
| 422 | 参数验证失败 | days 参数不符合规则 |