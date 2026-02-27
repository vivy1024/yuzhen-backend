# 训练计划 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/training-plan.php

## 概述

训练计划模块提供完整的训练计划管理功能，支持：
- 手动创建和编辑训练计划
- 从 AI 聊天中导入训练计划
- 计划复制、激活、开始、导出
- 训练进度统计和日志查询
- 训练计划模板库管理

所有端点均需要 JWT 认证（Bearer Token）。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 限流 |
|------|------|------|------|------|
| GET | /api/training/plans | 获取训练计划列表 | ✅ | - |
| POST | /api/training/plans | 手动创建训练计划 | ✅ | - |
| POST | /api/training/plans/import | 从 AI 导入训练计划 | ✅ | - |
| GET | /api/training/plans/{id} | 获取训练计划详情 | ✅ | - |
| PUT | /api/training/plans/{id} | 更新训练计划 | ✅ | - |
| DELETE | /api/training/plans/{id} | 删除训练计划 | ✅ | - |
| POST | /api/training/plans/{id}/copy | 复制训练计划 | ✅ | - |
| POST | /api/training/plans/{id}/activate | 激活训练计划 | ✅ | - |
| POST | /api/training/plans/{id}/start | 开始训练计划 | ✅ | - |
| POST | /api/training/plans/{id}/export | 导出训练计划 | ✅ | - |
| GET | /api/training/plans/{id}/progress-stats | 获取计划进度统计 | ✅ | - |
| GET | /api/training/plans/{id}/training-logs | 获取计划训练日志 | ✅ | - |
| GET | /api/training/templates | 获取训练计划模板列表 | ✅ | - |
| POST | /api/training/templates/{id}/use | 从模板创建个人计划 | ✅ | - |

## 详细说明

### 获取训练计划列表
- **路径**: GET /api/training/plans
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取当前用户的所有训练计划，支持按状态、难度、目标、类型筛选

**查询参数**:

| 参数 | 类型 | 必填 | 说明 | 示例 |
|------|------|------|------|------|
| status | string | 否 | 计划状态：active(激活中)、completed(已完成) | active |
| difficulty | string | 否 | 难度等级：novice、beginner、intermediate、advanced | beginner |
| goal | string | 否 | 训练目标 | hypertrophy |
| type | string | 否 | 计划类型：manual(手动)、ai_generated(AI生成) | manual |

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": [
    {
      "id": 1,
      "name": "增肌计划",
      "description": "12周增肌训练计划",
      "weeks": 12,
      "frequency": 4,
      "difficulty": "intermediate",
      "goal": "hypertrophy",
      "isActive": true,
      "type": "manual",
      "exerciseCount": 8,
      "createdAt": "2026-02-28T10:00:00Z",
      "startedAt": "2026-02-28T10:30:00Z",
      "completedAt": null
    }
  ]
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或 Token 过期 |
| 500 | Internal Server Error | 服务器错误 |

---

### 手动创建训练计划
- **路径**: POST /api/training/plans
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 用户手动创建训练计划，包含动作列表和可选的饮食计划

**请求参数**:

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| name | string | ✅ | max:100 | 计划名称 |
| description | string | 否 | max:500 | 计划描述 |
| goal | string | 否 | in:hypertrophy,fat_loss,strength,... | 训练目标 |
| difficulty | string | 否 | in:novice,beginner,intermediate,advanced | 难度等级 |
| duration_weeks | integer | ✅ | min:1, max:52 | 计划周期（周） |
| workouts_per_week | integer | ✅ | min:1, max:7 | 每周训练次数 |
| exercises | array | ✅ | min:1 | 训练动作列表 |
| exercises[].exercise_id | integer | 否 | exists:exercises,id | 动作 ID（可选） |
| exercises[].exercise_name | string | ✅ | max:100 | 动作名称 |
| exercises[].day_of_week | integer | 否 | min:1, max:7 | 周几训练（1-7） |
| exercises[].sets | integer | ✅ | min:1, max:20 | 组数 |
| exercises[].reps | string | ✅ | max:20 | 次数（如"8-12"） |
| exercises[].weight | string | 否 | max:50 | 重量 |
| exercises[].rest_time | string | 否 | max:20 | 休息时间（如"60s"） |
| exercises[].notes | string | 否 | max:200 | 备注 |
| exercises[].order_index | integer | 否 | min:0 | 排序索引 |
| nutrition | array | 否 | - | 饮食计划列表 |
| nutrition[].food_id | integer | 否 | exists:foods,id | 食物 ID（可选） |
| nutrition[].food_name | string | ✅ | max:100 | 食物名称 |
| nutrition[].meal_type | string | ✅ | in:breakfast,lunch,dinner,snack | 餐次类型 |
| nutrition[].portion_grams | numeric | 否 | min:1, max:5000 | 份量（克） |
| nutrition[].day_of_week | integer | 否 | min:1, max:7 | 周几食用 |
| nutrition[].notes | string | 否 | max:200 | 备注 |

**请求示例**:
```json
{
  "name": "12周增肌计划",
  "description": "针对初级健身者的增肌训练计划",
  "goal": "hypertrophy",
  "difficulty": "beginner",
  "duration_weeks": 12,
  "workouts_per_week": 4,
  "exercises": [
    {
      "exercise_name": "卧推",
      "day_of_week": 1,
      "sets": 4,
      "reps": "8-10",
      "weight": "80kg",
      "rest_time": "90s",
      "notes": "主要动作"
    }
  ],
  "nutrition": [
    {
      "food_name": "鸡胸肉",
      "meal_type": "lunch",
      "portion_grams": 200,
      "day_of_week": 1
    }
  ]
}
```

**响应示例**:
```json
{
  "code": 200,
  "msg": "创建成功",
  "data": {
    "id": 1,
    "name": "12周增肌计划",
    "exerciseCount": 1,
    "createdAt": "2026-02-28T10:00:00Z"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 422 | Validation Error | 参数验证失败 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 从 AI 导入训练计划
- **路径**: POST /api/training/plans/import
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 从 AI 聊天中导入训练计划，支持灵活的参数命名

**请求参数**:

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| name | string | ✅ | max:100 | 计划名称 |
| description | string | 否 | - | 计划描述 |
| duration_weeks | integer | 条件 | min:1, max:52 | 计划周期（与 weeks 二选一） |
| weeks | integer | 条件 | min:1, max:52 | 计划周期（与 duration_weeks 二选一） |
| workouts_per_week | integer | 条件 | min:1, max:7 | 每周训练次数（与 frequency 二选一） |
| frequency | integer | 条件 | min:1, max:7 | 每周训练次数（与 workouts_per_week 二选一） |
| exercises | array | ✅ | - | 训练动作列表 |
| target_muscles | array | 否 | - | 目标肌群 |
| safety_notes | array | 否 | - | 安全提示 |
| difficulty | string | 否 | in:beginner,intermediate,advanced | 难度等级 |
| chat_session_id | integer | 否 | exists:chat_sessions,id | 关联的聊天会话 ID |

**请求示例**:
```json
{
  "name": "AI生成的增肌计划",
  "description": "根据用户体型生成的个性化计划",
  "weeks": 12,
  "frequency": 4,
  "exercises": [
    {
      "name": "卧推",
      "sets": 4,
      "reps": "8-10"
    }
  ],
  "target_muscles": ["胸部", "三头肌"],
  "safety_notes": ["热身充分", "控制速度"],
  "difficulty": "intermediate",
  "chat_session_id": 123
}
```

**响应示例**:
```json
{
  "code": 200,
  "msg": "导入成功",
  "data": {
    "id": 2,
    "name": "AI生成的增肌计划",
    "createdAt": "2026-02-28T10:05:00Z"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 422 | Validation Error | 参数验证失败 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取训练计划详情
- **路径**: GET /api/training/plans/{id}
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取单个训练计划的完整信息，包括动作列表和饮食计划

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "id": 1,
    "name": "12周增肌计划",
    "description": "针对初级健身者的增肌训练计划",
    "weeks": 12,
    "frequency": 4,
    "exercises": null,
    "planExercises": [
      {
        "id": 1,
        "exerciseId": 10,
        "exerciseName": "卧推",
        "dayOfWeek": 1,
        "sets": 4,
        "reps": "8-10",
        "weight": "80kg",
        "restTime": "90s",
        "notes": "主要动作",
        "orderIndex": 0
      }
    ],
    "nutritionPlans": [
      {
        "id": 1,
        "foodId": 5,
        "foodName": "鸡胸肉",
        "mealType": "lunch",
        "portionGrams": 200,
        "dayOfWeek": 1,
        "notes": null,
        "nutrition": {
          "energyKcal": 165,
          "protein": 31,
          "fat": 3.6,
          "carbohydrate": 0
        }
      }
    ],
    "targetMuscles": null,
    "safetyNotes": null,
    "difficulty": "beginner",
    "goal": "hypertrophy",
    "isActive": true,
    "type": "manual",
    "createdAt": "2026-02-28T10:00:00Z",
    "startedAt": "2026-02-28T10:30:00Z",
    "completedAt": null,
    "chatSessionId": null
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 更新训练计划
- **路径**: PUT /api/training/plans/{id}
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 更新训练计划信息，支持部分更新

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**请求参数** (同创建计划，但所有字段均为可选):

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| name | string | 否 | max:100 | 计划名称 |
| description | string | 否 | max:500 | 计划描述 |
| goal | string | 否 | in:hypertrophy,fat_loss,... | 训练目标 |
| difficulty | string | 否 | in:novice,beginner,intermediate,advanced | 难度等级 |
| duration_weeks | integer | 否 | min:1, max:52 | 计划周期 |
| workouts_per_week | integer | 否 | min:1, max:7 | 每周训练次数 |
| exercises | array | 否 | min:1 | 训练动作列表 |
| nutrition | array | 否 | - | 饮食计划列表 |

**响应示例**:
```json
{
  "code": 200,
  "msg": "更新成功",
  "data": {
    "id": 1,
    "name": "12周增肌计划（已更新）",
    "exerciseCount": 1,
    "updatedAt": "2026-02-28T11:00:00Z"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 422 | Validation Error | 参数验证失败 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 删除训练计划
- **路径**: DELETE /api/training/plans/{id}
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 删除训练计划（软删除）

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "删除成功",
  "data": null
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 复制训练计划
- **路径**: POST /api/training/plans/{id}/copy
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 复制现有训练计划，生成新计划（名称后缀"(副本)"，状态为未激活）

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 源训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "复制成功",
  "data": {
    "id": 3,
    "name": "12周增肌计划 (副本)",
    "exerciseCount": 1,
    "createdAt": "2026-02-28T11:05:00Z"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 源计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 激活训练计划
- **路径**: POST /api/training/plans/{id}/activate
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 激活训练计划，同时停用用户的其他计划

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "计划已激活",
  "data": {
    "id": 1,
    "is_active": true
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 开始训练计划
- **路径**: POST /api/training/plans/{id}/start
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 标记训练计划为已开始，记录开始时间

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "计划已开始",
  "data": {
    "id": 1,
    "started_at": "2026-02-28T11:10:00Z"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 导出训练计划
- **路径**: POST /api/training/plans/{id}/export
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 导出训练计划为结构化数据（JSON 格式）

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "导出成功",
  "data": {
    "plan": {
      "id": 1,
      "name": "12周增肌计划",
      "description": "针对初级健身者的增肌训练计划",
      "weeks": 12,
      "frequency": 4,
      "difficulty": "beginner",
      "goal": "hypertrophy",
      "type": "manual",
      "createdAt": "2026-02-28T10:00:00Z"
    },
    "exercises": [
      {
        "exerciseName": "卧推",
        "dayOfWeek": 1,
        "sets": 4,
        "reps": "8-10",
        "weight": "80kg",
        "restTime": "90s",
        "notes": "主要动作"
      }
    ]
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取计划进度统计
- **路径**: GET /api/training/plans/{id}/progress-stats
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取训练计划的进度统计数据

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取计划进度统计成功",
  "data": {
    "total_planned_sessions": 48,
    "completed_sessions": 12,
    "completion_rate": 25.0,
    "avg_completion_rate": 95.5,
    "avg_rpe": 7.2,
    "last_training_date": "2026-02-28"
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取计划训练日志
- **路径**: GET /api/training/plans/{id}/training-logs
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取训练计划关联的所有训练日志

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 训练计划 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取计划训练日志成功",
  "data": [
    {
      "id": 1,
      "training_plan_id": 1,
      "session_date": "2026-02-28",
      "planned_exercises": [...],
      "actual_exercises": [...],
      "completion_rate": 0.95,
      "avg_rpe": 7.5
    }
  ]
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 计划不存在或不属于当前用户 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 获取训练计划模板列表
- **路径**: GET /api/training/templates
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 获取所有可用的训练计划模板，支持按目标和难度筛选

**查询参数**:

| 参数 | 类型 | 必填 | 说明 | 示例 |
|------|------|------|------|------|
| goal | string | 否 | 训练目标 | hypertrophy |
| level | string | 否 | 难度等级：beginner、intermediate、advanced | beginner |

**响应示例**:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": [
    {
      "id": 1,
      "name": "初级增肌计划",
      "description": "适合初学者的12周增肌计划",
      "goal": "hypertrophy",
      "level": "beginner",
      "durationWeeks": 12,
      "workoutsPerWeek": 4,
      "exerciseCount": 8,
      "tags": ["增肌", "初级"],
      "useCount": 156
    }
  ]
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

### 从模板创建个人计划
- **路径**: POST /api/training/templates/{id}/use
- **认证**: Bearer Token (jwt.auth)
- **中间件**: jwt.auth
- **说明**: 基于模板创建个人训练计划，自动复制模板中的动作

**路径参数**:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | ✅ | 模板 ID |

**响应示例**:
```json
{
  "code": 200,
  "msg": "已从模板创建计划",
  "data": {
    "id": 4,
    "name": "初级增肌计划",
    "exerciseCount": 8
  }
}
```

**错误码**:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 模板不存在 |
| 401 | Unauthorized | 未认证 |
| 500 | Internal Server Error | 服务器错误 |

---

## 通用说明

### 认证方式
所有需要认证的端点使用 Bearer Token 认证，在请求头中添加：
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

### 错误处理
- 所有错误响应包含 `code` 和 `msg` 字段
- 验证错误返回 422 状态码
- 未认证返回 401 状态码
- 资源不存在返回 404 状态码
