# 个人最佳 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/personal-best.php

## 概述

个人最佳 API 提供用户训练记录中个人最佳成绩（Personal Best）的管理功能。支持获取个人最佳列表、查看特定动作的最佳记录、自动更新最佳成绩、批量更新以及力量排行榜等功能。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/personal-bests | 获取所有个人最佳记录 | Bearer Token | 无 |
| GET | /api/personal-bests/{exerciseId} | 获取特定动作的个人最佳 | Bearer Token | 无 |
| POST | /api/personal-bests/update | 更新个人最佳记录 | Bearer Token | 无 |
| POST | /api/personal-bests/batch-update | 批量更新个人最佳 | Bearer Token | 无 |
| DELETE | /api/personal-bests/{exerciseId} | 删除个人最佳记录 | Bearer Token | 无 |
| GET | /api/personal-bests/leaderboard | 获取力量排行榜 | Bearer Token | 无 |

## 详细说明

### 获取所有个人最佳记录
- 路径: GET /api/personal-bests
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| per_page | integer | 否 | 每页数量 | 默认50 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取个人最佳记录列表成功",
  "data": {
    "rows": [
      {
        "id": 1,
        "user_id": 1,
        "exercise_id": "bench_press",
        "exercise_name": "卧推",
        "best_weight": 100,
        "best_reps": 8,
        "estimated_1rm": 133.2,
        "created_at": "2026-01-15T10:00:00Z",
        "updated_at": "2026-01-20T15:30:00Z"
      }
    ],
    "total": 10,
    "page": 1,
    "per_page": 50,
    "total_pages": 1
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |

---

### 获取特定动作的个人最佳记录
- 路径: GET /api/personal-bests/{exerciseId}
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| exerciseId | string | 是 | 动作ID（路径参数） |

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取个人最佳记录成功",
  "data": {
    "id": 1,
    "user_id": 1,
    "exercise_id": "bench_press",
    "exercise_name": "卧推",
    "best_weight": 100,
    "best_reps": 8,
    "estimated_1rm": 133.2,
    "created_at": "2026-01-15T10:00:00Z",
    "updated_at": "2026-01-20T15:30:00Z"
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 404 | 未找到该动作的个人最佳记录 | 记录不存在 |

---

### 更新个人最佳记录（自动判断是否打破记录）
- 路径: POST /api/personal-bests/update
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 系统会自动判断新成绩是否超过当前最佳记录，若超过则更新

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| exercise_id | string | 是 | 动作ID | 最大50字符 |
| exercise_name | string | 否 | 动作名称 | 最大255字符 |
| weight | numeric | 是 | 重量 | 0.1-1000 |
| reps | integer | 是 | 次数 | 1-100 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "恭喜！打破个人最佳记录！",
  "data": {
    "personal_best": {
      "id": 1,
      "exercise_id": "bench_press",
      "exercise_name": "卧推",
      "best_weight": 105,
      "best_reps": 8,
      "estimated_1rm": 140.0
    },
    "is_new_record": true,
    "previous_best": {
      "best_weight": 100,
      "best_reps": 8,
      "estimated_1rm": 133.2
    },
    "current_attempt": {
      "weight": 105,
      "reps": 8,
      "estimated_1rm": 140.0
    }
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 422 | 参数验证失败 | 请求参数不符合规则 |

---

### 批量更新个人最佳记录
- 路径: POST /api/personal-bests/batch-update
- 认证: Bearer Token
- 中间件: jwt.auth

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| records | array | 是 | 记录数组 | 1-50条 |
| records[].exercise_id | string | 是 | 动作ID | 最大50字符 |
| records[].exercise_name | string | 否 | 动作名称 | 最大255字符 |
| records[].weight | numeric | 是 | 重量 | 0.1-1000 |
| records[].reps | integer | 是 | 次数 | 1-100 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "批量更新完成，2个新记录",
  "data": {
    "results": [
      {
        "exercise_id": "bench_press",
        "is_new_record": true,
        "current_best": {
          "weight": 105,
          "reps": 8,
          "estimated_1rm": 140.0
        }
      },
      {
        "exercise_id": "squat",
        "is_new_record": false,
        "current_best": {
          "weight": 150,
          "reps": 5,
          "estimated_1rm": 168.75
        }
      }
    ],
    "total_processed": 2,
    "new_records_count": 2
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 422 | 参数验证失败 | 请求参数不符合规则 |

---

### 删除个人最佳记录
- 路径: DELETE /api/personal-bests/{exerciseId}
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| exerciseId | string | 是 | 动作ID（路径参数） |

- 响应示例:
```json
{
  "code": 200,
  "msg": "个人最佳记录删除成功",
  "data": null
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 404 | 未找到该动作的个人最佳记录 | 记录不存在 |

---

### 获取力量排行榜
- 路径: GET /api/personal-bests/leaderboard
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 按估算1RM排序，获取用户力量水平排名前20的动作

- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取力量排行榜成功",
  "data": {
    "leaderboard": [
      {
        "id": 1,
        "exercise_id": "deadlift",
        "exercise_name": "硬拉",
        "best_weight": 180,
        "best_reps": 3,
        "estimated_1rm": 194.4
      },
      {
        "id": 2,
        "exercise_id": "squat",
        "exercise_name": "深蹲",
        "best_weight": 150,
        "best_reps": 5,
        "estimated_1rm": 168.75
      }
    ],
    "total": 2
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |