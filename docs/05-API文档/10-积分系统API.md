# 积分系统 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/credit.php

## 概述

积分系统 API 提供用户积分相关的查询接口，包括积分余额查询、流水历史查询和消耗统计。所有接口均需要用户认证（JWT Token）。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 限流 |
|------|------|------|------|------|
| GET | /api/credits/balance | 获取积分余额 | Bearer Token | - |
| GET | /api/credits/history | 获取积分流水历史 | Bearer Token | - |
| GET | /api/credits/stats | 获取积分消耗统计 | Bearer Token | - |

## 详细说明

### 获取积分余额

- **路径**: GET /api/credits/balance
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth
- **请求参数**: 无

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "daily_quota": 50,
    "daily_consumed": 12,
    "remaining": 38,
    "total_consumed": 1250,
    "membership_tier": "warmheart",
    "is_mvp_phase": false,
    "low_balance_warning": false,
    "last_reset": "2026-01-18"
  }
}
```

#### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| daily_quota | integer | 每日积分配额 |
| daily_consumed | integer | 今日已消耗积分 |
| remaining | integer | 剩余积分 |
| total_consumed | integer | 总消耗积分 |
| membership_tier | string | 会员等级（free/warmheart/energy） |
| is_mvp_phase | boolean | 是否处于MVP阶段 |
| low_balance_warning | boolean | 是否低余额警告 |
| last_reset | string | 最后重置日期（Y-m-d格式） |

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 500 | 获取积分余额失败 | 服务器内部错误 |

---

### 获取积分流水历史

- **路径**: GET /api/credits/history
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 默认值 | 验证规则 | 说明 |
|------|------|------|--------|---------|------|
| page | integer | 否 | 1 | min:1 | 页码 |
| per_page | integer | 否 | 10 | min:1, max:50 | 每页条数 |
| mode | string | 否 | - | in:dag,agent | 筛选模式（DAG或Agent） |
| start_date | string | 否 | - | date_format:Y-m-d | 开始日期 |
| end_date | string | 否 | - | date_format:Y-m-d, after_or_equal:start_date | 结束日期 |

#### 请求示例

```
GET /api/credits/history?page=1&per_page=10&mode=dag&start_date=2026-01-01&end_date=2026-01-31
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "transactions": [
      {
        "id": 123,
        "credits": 3,
        "tokens": 2500,
        "mode": "dag",
        "mode_label": "DAG模式",
        "template_name": "exercise_optimization",
        "conversation_id": "conv_abc123",
        "input_tokens": 800,
        "output_tokens": 1700,
        "description": null,
        "created_at": "2026-01-18 10:30:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_count": 48,
      "per_page": 10
    },
    "summary": {
      "today": 12,
      "this_week": 85,
      "this_month": 320
    }
  }
}
```

#### 响应字段说明

**transactions 数组元素**:

| 字段 | 类型 | 说明 |
|------|------|------|
| id | integer | 交易记录ID |
| credits | integer | 消耗的积分数 |
| tokens | integer | 消耗的Token数 |
| mode | string | 执行模式（dag/agent） |
| mode_label | string | 模式标签（中文） |
| template_name | string | 模板名称 |
| conversation_id | string | 对话ID |
| input_tokens | integer | 输入Token数 |
| output_tokens | integer | 输出Token数 |
| description | string | 描述（可为null） |
| created_at | string | 创建时间 |

**pagination 对象**:

| 字段 | 类型 | 说明 |
|------|------|------|
| current_page | integer | 当前页码 |
| total_pages | integer | 总页数 |
| total_count | integer | 总记录数 |
| per_page | integer | 每页条数 |

**summary 对象**:

| 字段 | 类型 | 说明 |
|------|------|------|
| today | integer | 今日消耗积分 |
| this_week | integer | 本周消耗积分 |
| this_month | integer | 本月消耗积分 |

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 422 | 参数验证失败 | 请求参数不符合验证规则 |
| 500 | 获取积分流水历史失败 | 服务器内部错误 |

---

### 获取积分消耗统计

- **路径**: GET /api/credits/stats
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 默认值 | 验证规则 | 说明 |
|------|------|------|--------|---------|------|
| period | string | 否 | all | in:today,week,month,all | 统计周期 |

#### 请求示例

```
GET /api/credits/stats?period=month
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "summary": {
      "today": 12,
      "this_week": 85,
      "this_month": 320
    },
    "by_mode": {
      "dag": {
        "count": 45,
        "credits": 180,
        "tokens": 150000
      },
      "agent": {
        "count": 15,
        "credits": 140,
        "tokens": 80000
      }
    },
    "by_template": [
      {
        "template_name": "exercise_optimization",
        "count": 20,
        "credits": 80
      }
    ],
    "daily_trend": [
      {
        "date": "2026-01-18",
        "credits": 12,
        "count": 5
      }
    ]
  }
}
```

#### 响应字段说明

**summary 对象**:

| 字段 | 类型 | 说明 |
|------|------|------|
| today | integer | 今日消耗积分 |
| this_week | integer | 本周消耗积分 |
| this_month | integer | 本月消耗积分 |

**by_mode 对象**:

| 字段 | 类型 | 说明 |
|------|------|------|
| dag | object | DAG模式统计 |
| agent | object | Agent模式统计 |

**by_mode.{mode} 对象**:

| 字段 | 类型 | 说明 |
|------|------|------|
| count | integer | 执行次数 |
| credits | integer | 消耗积分总数 |
| tokens | integer | 消耗Token总数 |

**by_template 数组元素**:

| 字段 | 类型 | 说明 |
|------|------|------|
| template_name | string | 模板名称 |
| count | integer | 使用次数 |
| credits | integer | 消耗积分总数 |

**daily_trend 数组元素**:

| 字段 | 类型 | 说明 |
|------|------|------|
| date | string | 日期（Y-m-d格式） |
| credits | integer | 该日消耗积分 |
| count | integer | 该日执行次数 |

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 422 | 参数验证失败 | 请求参数不符合验证规则 |
| 500 | 获取积分消耗统计失败 | 服务器内部错误 |

---

## 通用说明

### 认证方式

所有接口均使用 Bearer Token 认证，请在请求头中添加：

```
Authorization: Bearer {token}
```

### 响应格式

所有接口返回统一的 JSON 格式：

```json
{
  "code": 200,
  "msg": "success",
  "data": {}
}
```

- **code**: HTTP 状态码
- **msg**: 响应消息
- **data**: 响应数据（错误时为 null）

### 日期格式

- 日期参数格式: `Y-m-d`（例：2026-01-18）
- 日期时间格式: `Y-m-d H:i:s`（例：2026-01-18 10:30:00）
