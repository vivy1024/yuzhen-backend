# 进度记录 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/progress.php

## 概述

提供进度追踪相关的 API 接口，包括进度记录的增删查改、健身目标管理、训练日历查询和趋势数据分析等功能。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 限流 |
|------|------|------|------|------|
| GET | /api/progress/overview | 获取进度概览 | Bearer Token | - |
| GET | /api/progress/records | 获取进度记录列表 | Bearer Token | - |
| POST | /api/progress/records | 创建进度记录 | Bearer Token | - |
| GET | /api/progress/records/{id} | 获取单条进度记录 | Bearer Token | - |
| PUT | /api/progress/records/{id} | 更新进度记录 | Bearer Token | - |
| DELETE | /api/progress/records/{id} | 删除进度记录 | Bearer Token | - |
| GET | /api/progress/goals | 获取目标列表 | Bearer Token | - |
| POST | /api/progress/goals | 创建目标 | Bearer Token | - |
| PUT | /api/progress/goals/{id} | 更新目标 | Bearer Token | - |
| DELETE | /api/progress/goals/{id} | 删除目标 | Bearer Token | - |
| GET | /api/progress/calendar | 获取训练日历 | Bearer Token | - |
| GET | /api/progress/trends/weight | 获取体重趋势 | Bearer Token | - |
| GET | /api/progress/trends/ffmi | 获取FFMI趋势 | Bearer Token | - |

## 详细说明

### 获取进度概览
- 路径: GET /api/progress/overview
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取用户的进度概览，包括体重趋势、FFMI趋势、训练日历、活跃目标、最近记录和统计数据

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| start_date | string | 否 | 开始日期，默认3个月前 | date, format:Y-m-d |
| end_date | string | 否 | 结束日期，默认今天 | date, format:Y-m-d |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取进度概览成功",
  "data": {
    "weightTrend": [
      {
        "date": "2026-02-28",
        "weight": 75.5,
        "bodyFat": 18.5
      }
    ],
    "ffmiTrend": [
      {
        "date": "2026-02-28",
        "ffmi": 24.8,
        "leanBodyMass": 61.5
      }
    ],
    "trainingCalendar": [
      {
        "date": "2026-02-28",
        "hasTraining": true,
        "sessionCount": 1,
        "totalVolume": 5000
      }
    ],
    "activeGoals": [
      {
        "id": 1,
        "userId": 1,
        "type": "weight",
        "name": "减重目标",
        "targetValue": 70,
        "currentValue": 75.5,
        "startValue": 80,
        "unit": "kg",
        "startDate": "2026-01-01",
        "targetDate": "2026-06-30",
        "completedAt": null,
        "status": "active",
        "progress": 55,
        "estimatedCompletion": "2026-05-15",
        "createdAt": "2026-01-01T00:00:00Z",
        "updatedAt": "2026-02-28T10:00:00Z"
      }
    ],
    "recentRecords": [
      {
        "id": 1,
        "userId": 1,
        "date": "2026-02-28",
        "weight": 75.5,
        "bodyFat": 18.5,
        "ffmi": 24.8,
        "leanBodyMass": 61.5,
        "measurements": {
          "chest": 100,
          "waist": 85,
          "hips": 95,
          "arms": 35,
          "thighs": 58
        },
        "photos": [],
        "notes": "今天状态不错",
        "createdAt": "2026-02-28T10:00:00Z",
        "updatedAt": "2026-02-28T10:00:00Z"
      }
    ],
    "stats": {
      "totalVolume": 15000,
      "trainingDaysThisMonth": 12,
      "trainingDaysLastMonth": 10,
      "currentWeight": 75.5,
      "weightChange": -4.5,
      "currentBodyFat": 18.5,
      "bodyFatChange": null,
      "currentFFMI": 24.8,
      "ffmiChange": 0.5,
      "totalRecords": 28
    }
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 500 | Server Error | 服务器错误 |

---

### 获取进度记录列表
- 路径: GET /api/progress/records
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取用户的进度记录列表，支持日期范围筛选和分页

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| start_date | string | 否 | 开始日期 | date, format:Y-m-d |
| end_date | string | 否 | 结束日期 | date, format:Y-m-d |
| per_page | integer | 否 | 每页数量，默认20 | integer, min:1 |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取进度记录成功",
  "data": {
    "data": [
      {
        "id": 1,
        "userId": 1,
        "date": "2026-02-28",
        "weight": 75.5,
        "bodyFat": 18.5,
        "ffmi": 24.8,
        "leanBodyMass": 61.5,
        "measurements": {
          "chest": 100,
          "waist": 85,
          "hips": 95,
          "arms": 35,
          "thighs": 58
        },
        "photos": [],
        "notes": "今天状态不错",
        "createdAt": "2026-02-28T10:00:00Z",
        "updatedAt": "2026-02-28T10:00:00Z"
      }
    ],
    "total": 28,
    "currentPage": 1,
    "lastPage": 2
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 500 | Server Error | 服务器错误 |

---

### 创建进度记录
- 路径: POST /api/progress/records
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 创建新的进度记录，如果当天已有记录则更新。同时更新用户档案中的体重和体脂数据

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| date | string | 是 | 记录日期 | required, date, format:Y-m-d |
| weight | number | 是 | 体重(kg) | required, numeric, min:20, max:300 |
| body_fat | number | 否 | 体脂率(%) | nullable, numeric, min:1, max:60 |
| measurements | object | 否 | 身体围度 | nullable, array |
| measurements.chest | number | 否 | 胸围(cm) | nullable, numeric, min:50, max:200 |
| measurements.waist | number | 否 | 腰围(cm) | nullable, numeric, min:40, max:200 |
| measurements.hips | number | 否 | 臀围(cm) | nullable, numeric, min:50, max:200 |
| measurements.arms | number | 否 | 臂围(cm) | nullable, numeric, min:20, max:60 |
| measurements.thighs | number | 否 | 腿围(cm) | nullable, numeric, min:30, max:100 |
| notes | string | 否 | 备注 | nullable, string, max:500 |

**请求示例:**

```json
{
  "date": "2026-02-28",
  "weight": 75.5,
  "body_fat": 18.5,
  "measurements": {
    "chest": 100,
    "waist": 85,
    "hips": 95,
    "arms": 35,
    "thighs": 58
  },
  "notes": "今天状态不错"
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "记录已创建",
  "data": {
    "id": 1,
    "userId": 1,
    "date": "2026-02-28",
    "weight": 75.5,
    "bodyFat": 18.5,
    "ffmi": 24.8,
    "leanBodyMass": 61.5,
    "measurements": {
      "chest": 100,
      "waist": 85,
      "hips": 95,
      "arms": 35,
      "thighs": 58
    },
    "photos": [],
    "notes": "今天状态不错",
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:00:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 422 | Validation Error | 参数验证失败 |
| 500 | Server Error | 服务器错误 |

---

### 获取单条进度记录
- 路径: GET /api/progress/records/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取指定的进度记录详情

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 记录ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取记录成功",
  "data": {
    "id": 1,
    "userId": 1,
    "date": "2026-02-28",
    "weight": 75.5,
    "bodyFat": 18.5,
    "ffmi": 24.8,
    "leanBodyMass": 61.5,
    "measurements": {
      "chest": 100,
      "waist": 85,
      "hips": 95,
      "arms": 35,
      "thighs": 58
    },
    "photos": [],
    "notes": "今天状态不错",
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:00:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 记录不存在 |
| 500 | Server Error | 服务器错误 |

---

### 更新进度记录
- 路径: PUT /api/progress/records/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 更新指定的进度记录

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 记录ID |

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| weight | number | 否 | 体重(kg) | nullable, numeric, min:20, max:300 |
| body_fat | number | 否 | 体脂率(%) | nullable, numeric, min:1, max:60 |
| measurements | object | 否 | 身体围度 | nullable, array |
| notes | string | 否 | 备注 | nullable, string, max:500 |

**请求示例:**

```json
{
  "weight": 75.2,
  "body_fat": 18.3,
  "notes": "更新后的备注"
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "记录已更新",
  "data": {
    "id": 1,
    "userId": 1,
    "date": "2026-02-28",
    "weight": 75.2,
    "bodyFat": 18.3,
    "ffmi": 24.7,
    "leanBodyMass": 61.4,
    "measurements": {
      "chest": 100,
      "waist": 85,
      "hips": 95,
      "arms": 35,
      "thighs": 58
    },
    "photos": [],
    "notes": "更新后的备注",
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:05:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 记录不存在 |
| 422 | Validation Error | 参数验证失败 |
| 500 | Server Error | 服务器错误 |

---

### 删除进度记录
- 路径: DELETE /api/progress/records/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 删除指定的进度记录

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 记录ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "记录已删除",
  "data": null
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 记录不存在 |
| 500 | Server Error | 服务器错误 |

---

### 获取目标列表
- 路径: GET /api/progress/goals
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取用户的健身目标列表，支持按状态筛选

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| status | string | 否 | 目标状态，默认all | in:all,active,completed,abandoned |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取目标列表成功",
  "data": [
    {
      "id": 1,
      "userId": 1,
      "type": "weight",
      "name": "减重目标",
      "targetValue": 70,
      "currentValue": 75.5,
      "startValue": 80,
      "unit": "kg",
      "startDate": "2026-01-01",
      "targetDate": "2026-06-30",
      "completedAt": null,
      "status": "active",
      "progress": 55,
      "estimatedCompletion": "2026-05-15",
      "createdAt": "2026-01-01T00:00:00Z",
      "updatedAt": "2026-02-28T10:00:00Z"
    }
  ]
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 500 | Server Error | 服务器错误 |

---

### 创建目标
- 路径: POST /api/progress/goals
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 创建新的健身目标

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| type | string | 是 | 目标类型 | required, in:weight,body_fat,muscle_mass,strength,custom |
| name | string | 是 | 目标名称 | required, string, max:100 |
| target_value | number | 是 | 目标值 | required, numeric |
| current_value | number | 是 | 当前值 | required, numeric |
| unit | string | 是 | 单位 | required, string, max:20 |
| target_date | string | 否 | 目标完成日期 | nullable, date, after:today |

**请求示例:**

```json
{
  "type": "weight",
  "name": "减重目标",
  "target_value": 70,
  "current_value": 75.5,
  "unit": "kg",
  "target_date": "2026-06-30"
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "目标已创建",
  "data": {
    "id": 1,
    "userId": 1,
    "type": "weight",
    "name": "减重目标",
    "targetValue": 70,
    "currentValue": 75.5,
    "startValue": 75.5,
    "unit": "kg",
    "startDate": "2026-02-28",
    "targetDate": "2026-06-30",
    "completedAt": null,
    "status": "active",
    "progress": 0,
    "estimatedCompletion": null,
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:00:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 422 | Validation Error | 参数验证失败 |
| 500 | Server Error | 服务器错误 |

---

### 更新目标
- 路径: PUT /api/progress/goals/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 更新指定的健身目标，支持更新当前值、目标值、目标日期和状态

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 目标ID |

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| current_value | number | 否 | 当前值 | nullable, numeric |
| target_value | number | 否 | 目标值 | nullable, numeric |
| target_date | string | 否 | 目标完成日期 | nullable, date |
| status | string | 否 | 目标状态 | nullable, in:active,completed,abandoned |

**请求示例:**

```json
{
  "current_value": 73.5,
  "status": "active"
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "目标已更新",
  "data": {
    "id": 1,
    "userId": 1,
    "type": "weight",
    "name": "减重目标",
    "targetValue": 70,
    "currentValue": 73.5,
    "startValue": 75.5,
    "unit": "kg",
    "startDate": "2026-02-28",
    "targetDate": "2026-06-30",
    "completedAt": null,
    "status": "active",
    "progress": 40,
    "estimatedCompletion": "2026-05-20",
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:05:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 目标不存在 |
| 422 | Validation Error | 参数验证失败 |
| 500 | Server Error | 服务器错误 |

---

### 删除目标
- 路径: DELETE /api/progress/goals/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 删除指定的健身目标

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 目标ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "目标已删除",
  "data": null
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 目标不存在 |
| 500 | Server Error | 服务器错误 |

---

### 获取训练日历
- 路径: GET /api/progress/calendar
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取指定月份的训练日历数据，显示每天的训练情况和总训练量

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| year | integer | 否 | 年份，默认当前年 | integer |
| month | integer | 否 | 月份，默认当前月 | integer, min:1, max:12 |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取日历数据成功",
  "data": [
    {
      "date": "2026-02-01",
      "hasTraining": false,
      "sessionCount": 0,
      "totalVolume": 0
    },
    {
      "date": "2026-02-02",
      "hasTraining": true,
      "sessionCount": 1,
      "totalVolume": 5000
    },
    {
      "date": "2026-02-03",
      "hasTraining": true,
      "sessionCount": 2,
      "totalVolume": 8500
    }
  ]
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 500 | Server Error | 服务器错误 |

---

### 获取体重趋势
- 路径: GET /api/progress/trends/weight
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取指定时间范围内的体重趋势数据

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| start_date | string | 否 | 开始日期，默认3个月前 | date, format:Y-m-d |
| end_date | string | 否 | 结束日期，默认今天 | date, format:Y-m-d |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取体重趋势成功",
  "data": [
    {
      "date": "2026-02-01",
      "weight": 76.5,
      "bodyFat": 19.2
    },
    {
      "date": "2026-02-15",
      "weight": 75.8,
      "bodyFat": 18.8
    },
    {
      "date": "2026-02-28",
      "weight": 75.5,
      "bodyFat": 18.5
    }
  ]
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 500 | Server Error | 服务器错误 |

---

### 获取FFMI趋势
- 路径: GET /api/progress/trends/ffmi
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取指定时间范围内的FFMI（无脂体重指数）趋势数据

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| start_date | string | 否 | 开始日期，默认3个月前 | date, format:Y-m-d |
| end_date | string | 否 | 结束日期，默认今天 | date, format:Y-m-d |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取FFMI趋势成功",
  "data": [
    {
      "date": "2026-02-01",
      "ffmi": 25.2,
      "leanBodyMass": 62.1
    },
    {
      "date": "2026-02-15",
      "ffmi": 25.0,
      "leanBodyMass": 61.8
    },
    {
      "date": "2026-02-28",
      "ffmi": 24.8,
      "leanBodyMass": 61.5
    }
  ]
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 500 | Server Error | 服务器错误 |

---

## 通用说明

### 认证方式
所有端点均需要 Bearer Token 认证，在请求头中添加：
```
Authorization: Bearer {token}
```

### 响应格式
所有响应遵循统一的 JSON 格式：
```json
{
  "code": 0,
  "message": "操作成功",
  "data": {}
}
```

- `code`: 0 表示成功，非 0 表示失败
- `message`: 操作结果说明
- `data`: 返回的数据，失败时为 null

### 时间格式
- 日期格式: `Y-m-d` (例: 2026-02-28)
- ISO 8601 格式: `2026-02-28T10:00:00Z`
- 毫秒时间戳: `1740700800000`

### 数据类型说明
- **weight**: 体重，单位 kg
- **body_fat**: 体脂率，单位 %
- **ffmi**: 无脂体重指数 (Fat-Free Mass Index)
- **lean_body_mass**: 无脂体重，单位 kg
- **measurements**: 身体围度，包括胸围、腰围、臀围、臂围、腿围，单位 cm
