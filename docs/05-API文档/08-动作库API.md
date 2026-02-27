# 动作库 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/exercise.php

## 概述

动作库 API 提供健身动作的查询、搜索和管理功能。支持两个版本的路由前缀：`/api/exercises`（v1，兼容前端）和 `/api/exercises-v2`（v2，前端v2使用）。两个版本的端点功能完全相同，仅路由前缀不同。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/exercises/filter-options | 获取筛选选项 | 无 | - |
| GET | /api/exercises-v2/filter-options/clear-cache | 清除筛选选项缓存 | 无 | - |
| GET | /api/exercises/search | 搜索动作 | 无 | - |
| GET | /api/exercises | 获取动作列表 | 无 | - |
| GET | /api/exercises/{id} | 获取动作详情 | 无 | - |
| POST | /api/exercises | 创建动作 | Bearer Token | admin |
| PUT | /api/exercises/{id} | 更新动作 | Bearer Token | admin |
| DELETE | /api/exercises/{id} | 删除动作 | Bearer Token | admin |

## 详细说明

### 获取筛选选项

- **路径**: GET /api/exercises/filter-options 或 GET /api/exercises-v2/filter-options
- **认证**: 无
- **中间件**: 无
- **请求参数**: 无
- **缓存**: 24小时 HTTP 缓存

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "muscle": [
      {
        "id": 1,
        "name": "Chest",
        "name_zh": "胸部"
      },
      {
        "id": 2,
        "name": "Back",
        "name_zh": "背部"
      }
    ],
    "equipment": [
      {
        "id": 1,
        "name": "Barbell",
        "name_zh": "杠铃"
      },
      {
        "id": 2,
        "name": "Dumbbell",
        "name_zh": "哑铃"
      }
    ],
    "difficulty": [
      {
        "value": "Beginner",
        "label": "初级"
      },
      {
        "value": "Novice",
        "label": "新手"
      },
      {
        "value": "Intermediate",
        "label": "中级"
      },
      {
        "value": "Advanced",
        "label": "高级"
      }
    ]
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | 获取筛选选项失败 | 服务器内部错误 |

---

### 清除筛选选项缓存

- **路径**: GET /api/exercises-v2/filter-options/clear-cache
- **认证**: 无
- **中间件**: 无
- **请求参数**: 无
- **说明**: 用于数据更新后刷新缓存，仅在 v2 路由中提供

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "cleared": true,
    "cache_key": "exercise_filter_options",
    "new_data_count": {
      "muscle": 15,
      "equipment": 20,
      "difficulty": 4
    }
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | 清除缓存失败 | 服务器内部错误 |

---

### 搜索动作

- **路径**: GET /api/exercises/search 或 GET /api/exercises-v2/search
- **认证**: 无
- **中间件**: 无
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 默认值 | 验证规则 | 说明 |
|------|------|------|--------|---------|------|
| q | string | 是 | - | max:200 | 搜索关键词 |
| page | integer | 否 | 1 | min:1 | 页码 |
| per_page | integer | 否 | 20 | min:1, max:100 | 每页条数 |

#### 请求示例

```
GET /api/exercises/search?q=卧推&page=1&per_page=20
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "keyword": "卧推",
    "results": [
      {
        "id": 1,
        "name": "Barbell Bench Press",
        "name_zh": "杠铃卧推",
        "primary_muscle": "Chest",
        "primary_muscle_zh": "胸部",
        "equipment": "Barbell",
        "equipment_zh": "杠铃",
        "difficulty": "Intermediate",
        "difficulty_zh": "中级"
      }
    ],
    "total": 5
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 搜索关键词不能为空 | 未提供搜索关键词 |
| 500 | 搜索动作失败 | 服务器内部错误 |

---

### 获取动作列表

- **路径**: GET /api/exercises 或 GET /api/exercises-v2
- **认证**: 无
- **中间件**: 无
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 默认值 | 验证规则 | 说明 |
|------|------|------|--------|---------|------|
| muscle | string | 否 | - | max:100 | 肌肉群筛选 |
| equipment | string | 否 | - | max:100 | 器材筛选 |
| difficulty | string | 否 | - | in:Beginner,Novice,Intermediate,Advanced | 难度筛选 |
| force_type | string | 否 | - | in:Push,Pull,Static,Hold | 力量类型筛选 |
| mechanic_type | string | 否 | - | in:Compound,Isolation | 机制类型筛选 |
| search | string | 否 | - | max:200 | 搜索关键词 |
| query | string | 否 | - | max:200 | 搜索关键词（同search） |
| page | integer | 否 | 1 | min:1 | 页码 |
| per_page | integer | 否 | 20 | min:1, max:100 | 每页条数 |

#### 请求示例

```
GET /api/exercises?muscle=Chest&difficulty=Intermediate&page=1&per_page=20
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": [
    {
      "id": 1,
      "name": "Barbell Bench Press",
      "name_zh": "杠铃卧推",
      "primary_muscle": "Chest",
      "primary_muscle_zh": "胸部",
      "equipment": "Barbell",
      "equipment_zh": "杠铃",
      "difficulty": "Intermediate",
      "difficulty_zh": "中级"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 3,
    "total_count": 45,
    "per_page": 20
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 422 | 参数验证失败 | 请求参数不符合验证规则 |
| 500 | 获取动作列表失败 | 服务器内部错误 |

---

### 获取动作详情

- **路径**: GET /api/exercises/{id} 或 GET /api/exercises-v2/{id}
- **认证**: 无
- **中间件**: 无
- **路径参数**:
  - id (integer, 必需): 动作ID

#### 请求示例

```
GET /api/exercises/1
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "id": 1,
    "name": "Barbell Bench Press",
    "name_zh": "杠铃卧推",
    "primary_muscle": "Chest",
    "primary_muscle_zh": "胸部",
    "secondary_muscles": ["Front Deltoids", "Triceps"],
    "secondary_muscles_zh": ["前三角肌", "三头肌"],
    "equipment": "Barbell",
    "equipment_zh": "杠铃",
    "difficulty": "Intermediate",
    "difficulty_zh": "中级",
    "force_type": "Push",
    "force_type_zh": "推",
    "mechanic_type": "Compound",
    "mechanic_type_zh": "复合动作",
    "description": "A compound upper body exercise...",
    "description_zh": "一个复合上身动作...",
    "instructions": ["Step 1...", "Step 2..."],
    "instructions_zh": ["步骤1...", "步骤2..."],
    "media": [
      {
        "type": "image",
        "url": "https://cdn.example.com/exercise/1/image.jpg"
      },
      {
        "type": "video",
        "url": "https://cdn.example.com/exercise/1/video.mp4"
      }
    ]
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | 动作不存在 | 指定的动作ID不存在 |
| 500 | 获取动作详情失败 | 服务器内部错误 |

---

### 创建动作

- **路径**: POST /api/exercises 或 POST /api/exercises-v2
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth, role:admin
- **权限**: 仅管理员可操作
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 验证规则 | 说明 |
|------|------|------|---------|------|
| name | string | 是 | required, max:255 | 英文名称 |
| name_zh | string | 是 | required, max:255 | 中文名称 |
| primary_muscle | string | 是 | required, max:100 | 主要肌肉群 |
| equipment | string | 是 | required, max:100 | 器材 |
| difficulty | string | 是 | required, in:Beginner,Novice,Intermediate,Advanced | 难度 |
| force_type | string | 否 | in:Push,Pull,Static,Hold | 力量类型 |
| mechanic_type | string | 否 | in:Compound,Isolation | 机制类型 |
| description | string | 否 | max:1000 | 英文描述 |
| description_zh | string | 否 | max:1000 | 中文描述 |

#### 请求示例

```json
POST /api/exercises
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Barbell Bench Press",
  "name_zh": "杠铃卧推",
  "primary_muscle": "Chest",
  "equipment": "Barbell",
  "difficulty": "Intermediate",
  "force_type": "Push",
  "mechanic_type": "Compound",
  "description": "A compound upper body exercise...",
  "description_zh": "一个复合上身动作..."
}
```

#### 响应示例

```json
{
  "code": 201,
  "msg": "success",
  "data": {
    "id": 100,
    "name": "Barbell Bench Press",
    "name_zh": "杠铃卧推",
    "primary_muscle": "Chest",
    "equipment": "Barbell",
    "difficulty": "Intermediate",
    "force_type": "Push",
    "mechanic_type": "Compound"
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 403 | 权限不足 | 用户不是管理员 |
| 422 | 参数验证失败 | 请求参数不符合验证规则 |
| 500 | 创建动作失败 | 服务器内部错误 |

---

### 更新动作

- **路径**: PUT /api/exercises/{id} 或 PUT /api/exercises-v2/{id}
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth, role:admin
- **权限**: 仅管理员可操作
- **路径参数**:
  - id (integer, 必需): 动作ID
- **请求参数**: 同创建动作（所有参数可选）

#### 请求示例

```json
PUT /api/exercises/1
Content-Type: application/json
Authorization: Bearer {token}

{
  "name_zh": "杠铃卧推（更新）",
  "difficulty": "Advanced"
}
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "id": 1,
    "name": "Barbell Bench Press",
    "name_zh": "杠铃卧推（更新）",
    "primary_muscle": "Chest",
    "equipment": "Barbell",
    "difficulty": "Advanced",
    "force_type": "Push",
    "mechanic_type": "Compound"
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 403 | 权限不足 | 用户不是管理员 |
| 404 | 动作不存在 | 指定的动作ID不存在 |
| 422 | 参数验证失败 | 请求参数不符合验证规则 |
| 500 | 更新动作失败 | 服务器内部错误 |

---

### 删除动作

- **路径**: DELETE /api/exercises/{id} 或 DELETE /api/exercises-v2/{id}
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth, role:admin
- **权限**: 仅管理员可操作
- **路径参数**:
  - id (integer, 必需): 动作ID
- **请求参数**: 无

#### 请求示例

```
DELETE /api/exercises/1
Authorization: Bearer {token}
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "id": 1,
    "deleted": true
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 403 | 权限不足 | 用户不是管理员 |
| 404 | 动作不存在 | 指定的动作ID不存在 |
| 500 | 删除动作失败 | 服务器内部错误 |

---

## 通用说明

### 认证方式

需要认证的接口使用 Bearer Token 认证，请在请求头中添加：

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

### 难度等级

| 值 | 中文 | 说明 |
|----|------|------|
| Beginner | 初级 | 适合完全初学者 |
| Novice | 新手 | 适合有基础的初学者 |
| Intermediate | 中级 | 适合有训练经验的人 |
| Advanced | 高级 | 适合高级训练者 |

### 力量类型

| 值 | 中文 | 说明 |
|----|------|------|
| Push | 推 | 向外推动的动作 |
| Pull | 拉 | 向内拉动的动作 |
| Static | 静态 | 静止保持的动作 |
| Hold | 保持 | 保持姿态的动作 |

### 机制类型

| 值 | 中文 | 说明 |
|----|------|------|
| Compound | 复合动作 | 涉及多个关节和肌肉群 |
| Isolation | 孤立动作 | 主要针对单一肌肉群 |

### 版本说明

- `/api/exercises` - v1 版本，用于兼容前端 v1
- `/api/exercises-v2` - v2 版本，用于前端 v2，额外提供清除缓存端点
