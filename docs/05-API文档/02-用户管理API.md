# 用户管理 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/user.php

## 概述

用户管理 API 提供用户信息查询、档案管理、统计数据、FFMI 历史记录等功能。支持获取当前用户信息、用户档案、训练统计、体测历史等操作。管理员可以进行用户列表查询、创建、更新、删除等操作。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/users/me | 获取当前登录用户信息 | Bearer Token | 无 |
| GET | /api/users/profile | 获取用户档案 | Bearer Token | 无 |
| PUT | /api/users/profile | 更新用户档案 | Bearer Token | 无 |
| POST | /api/users/profile | 更新用户档案（兼容POST） | Bearer Token | 无 |
| GET | /api/users/statistics | 获取用户统计信息 | Bearer Token | 无 |
| GET | /api/users/profile/ffmi-history | 获取FFMI历史记录 | Bearer Token | 无 |
| POST | /api/users/profile/ffmi-history | 保存FFMI记录 | Bearer Token | 无 |
| GET | /api/users/{id} | 获取用户详情 | Bearer Token | 无 |
| GET | /api/users | 获取用户列表（管理员） | Bearer Token | admin |
| POST | /api/users | 创建用户（管理员） | Bearer Token | admin |
| PUT | /api/users/{id} | 更新用户（管理员） | Bearer Token | admin |
| DELETE | /api/users/{id} | 删除用户（管理员） | Bearer Token | admin |

## 详细说明

### 获取当前登录用户信息
- 路径: GET /api/users/me
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取用户信息成功",
  "data": {
    "id": 1,
    "name": "用户昵称",
    "email": "user@example.com",
    "phone": "13800138000",
    "avatar": "/storage/avatars/1.jpg",
    "role": "user",
    "membership_tier": "energy",
    "body_type": "ectomorph",
    "user_type": "regular",
    "personal_volume_multiplier": 1.0,
    "created_at": "2026-02-28T10:00:00Z",
    "profile": {
      "id": 1,
      "user_id": 1,
      "basic_info": {
        "nickname": "用户昵称",
        "gender": "male",
        "age": 28,
        "height": 180,
        "weight": 75,
        "body_fat_percentage": 15,
        "fitness_level": "intermediate"
      },
      "fitness_goals": {
        "primary_goal": "hypertrophy",
        "secondary_goals": ["strength"],
        "target_weight": 80
      }
    },
    "membership": {
      "id": 3,
      "slug": "energy",
      "tier": "energy",
      "name": "能量会员"
    }
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 404 | 用户不存在 | 用户已被删除 |

---

### 获取用户档案
- 路径: GET /api/users/profile
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取用户档案成功",
  "data": {
    "id": 1,
    "user_id": 1,
    "basic_info": {
      "nickname": "用户昵称",
      "gender": "male",
      "age": 28,
      "height": 180,
      "weight": 75,
      "body_fat_percentage": 15,
      "fitness_level": "intermediate",
      "region": "北京",
      "sleep_hours": 7.5
    },
    "fitness_goals": {
      "primary_goal": "hypertrophy",
      "secondary_goals": ["strength"],
      "target_weight": 80,
      "training_split": "PPL"
    },
    "training_preferences": {
      "training_location": "健身房",
      "available_equipment": ["杠铃", "哑铃", "固定器械"],
      "training_intensity": "high",
      "exercise_preferences": ["复合动作"],
      "disliked_exercises": ["跑步"]
    },
    "preferred_rest_pattern": "练五休二",
    "health_status": {
      "chronic_diseases": [],
      "medications": [],
      "injury_history": ["无"],
      "other_notes": "无特殊说明"
    },
    "nutrition_profile": {
      "user_settings": {}
    },
    "strength_data": {
      "bench_press_1rm": 100,
      "squat_1rm": 150,
      "deadlift_1rm": 180
    },
    "ffmi_assessment": {
      "ffmi": 23.5,
      "lean_mass": 64,
      "fat_mass": 11
    },
    "current_streak": 15,
    "version": 1,
    "created_at": "2026-02-28T10:00:00Z",
    "updated_at": "2026-02-28T10:00:00Z"
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 404 | 用户档案不存在，请先创建档案 | 档案未初始化 |

---

### 更新用户档案
- 路径: PUT /api/users/profile 或 POST /api/users/profile
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| nickname | string | 否 | 昵称 | 最多50字符 |
| gender | string | 否 | 性别 | male/female/other |
| age | integer | 否 | 年龄 | 10-120 |
| height | number | 否 | 身高(cm) | 50-300 |
| weight | number | 否 | 体重(kg) | 20-500 |
| body_fat_percentage | number | 否 | 体脂率(%) | 3-60 |
| fitness_level | string | 否 | 健身水平 | novice/beginner/intermediate/advanced |
| region | string | 否 | 地区 | 最多100字符 |
| sleep_hours | number | 否 | 睡眠时长(小时) | 0-24 |
| primary_goal | string | 否 | 主要训练目标 | hypertrophy/fat_loss/strength/endurance/body_shaping/general_fitness/functional/rehabilitation |
| primary_goals | array | 否 | 主要训练目标(数组) | 同上 |
| secondary_goals | array | 否 | 次要训练目标 | 同上 |
| target_weight | number | 否 | 目标体重(kg) | 20-500 |
| training_split | string | 否 | 训练分割 | 自定义字符串 |
| training_location | string | 否 | 训练地点 | 自定义字符串 |
| available_equipment | array | 否 | 可用器械 | 杠铃/杠铃片/哑铃/固定器械/自由重量架/史密斯架/龙门架/弹力带/壶铃/TRX/药球/波速球/健身球/跳箱/战绳/徒手 |
| training_intensity | string | 否 | 训练强度 | 自定义字符串 |
| exercise_preferences | array | 否 | 喜欢的动作 | 自定义数组 |
| disliked_exercises | array | 否 | 不喜欢的动作 | 自定义数组 |
| preferred_rest_pattern | string | 否 | 休息模式 | 练一休一/练二休一/练三休一/练四休一/练五休一/练五休二/练六休一/练七休一 |
| chronic_diseases | array | 否 | 慢性病 | 自定义数组 |
| medications | array | 否 | 用药 | 自定义数组 |
| injury_history | array | 否 | 伤病史 | 无/下背部疼痛/腰椎间盘突出/前交叉韧带损伤/膝盖受伤/髌骨软化症/髂胫束综合征/肩峰撞击/肩袖损伤/肩部受伤/腕管综合征/腕部受伤/跟腱炎/足底筋膜炎/踝关节扭伤/颈椎病/颈部受伤/网球肘/高尔夫球肘/髋关节撞击/髋滑囊炎/髋部受伤/其他 |
| health_notes | string | 否 | 健康备注 | 自定义字符串 |
| nutrition_settings | object | 否 | 营养设置 | 自定义对象 |
| strength_data | object | 否 | 力量数据 | 自定义对象 |
| ffmi_assessment | object | 否 | FFMI评估 | 自定义对象 |
| version | integer | 否 | 版本号 | 最小值1 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "id": 1,
    "user_id": 1,
    "basic_info": {
      "nickname": "用户昵称",
      "gender": "male",
      "age": 28,
      "height": 180,
      "weight": 75,
      "body_fat_percentage": 15,
      "fitness_level": "intermediate"
    },
    "fitness_goals": {
      "primary_goal": "hypertrophy",
      "secondary_goals": ["strength"],
      "target_weight": 80
    },
    "version": 2,
    "updated_at": "2026-02-28T10:05:00Z"
  },
  "msg": "更新档案成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 无法获取用户ID | Token 无效 |
| 404 | 用户档案不存在 | 档案未初始化 |
| 422 | 参数验证失败 | 请求参数不符合规则 |

---

### 获取用户统计信息
- 路径: GET /api/users/statistics
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "total_training_sessions": 45,
    "total_training_hours": 67.5,
    "total_exercises_completed": 450,
    "current_streak": 15,
    "longest_streak": 30,
    "average_session_duration": 90,
    "favorite_exercise": "Bench Press",
    "total_volume_lifted": 15000,
    "personal_bests": {
      "bench_press": 100,
      "squat": 150,
      "deadlift": 180
    }
  },
  "msg": "获取统计信息成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |

---

### 获取FFMI历史记录
- 路径: GET /api/users/profile/ffmi-history?limit=10
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| limit | integer | 否 | 返回记录数 | 最大50条，默认10条 |

- 响应示例:
```json
{
  "code": 200,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "height": 180,
      "weight": 75,
      "body_fat_percentage": 15,
      "ffmi_data": {
        "ffmi": 23.5,
        "lean_mass": 64,
        "fat_mass": 11
      },
      "recorded_at": "2026-02-28T10:00:00Z"
    },
    {
      "id": 2,
      "user_id": 1,
      "height": 180,
      "weight": 74,
      "body_fat_percentage": 14.5,
      "ffmi_data": {
        "ffmi": 23.2,
        "lean_mass": 63.2,
        "fat_mass": 10.8
      },
      "recorded_at": "2026-02-27T10:00:00Z"
    }
  ],
  "msg": "获取FFMI历史成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |

---

### 保存FFMI记录
- 路径: POST /api/users/profile/ffmi-history
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| height | number | 是 | 身高(cm) | 100-250 |
| weight | number | 是 | 体重(kg) | 30-300 |
| body_fat_percentage | number | 否 | 体脂率(%) | 5-50 |
| ffmi_data | object | 是 | FFMI数据 | 必须是对象 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "id": 1,
    "user_id": 1,
    "height": 180,
    "weight": 75,
    "body_fat_percentage": 15,
    "ffmi_data": {
      "ffmi": 23.5,
      "lean_mass": 64,
      "fat_mass": 11
    },
    "recorded_at": "2026-02-28T10:00:00Z"
  },
  "msg": "保存FFMI记录成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 404 | 用户档案不存在，请先完善基础信息 | 档案未初始化 |
| 422 | 参数验证失败 | 请求参数不符合规则 |

---

### 获取用户详情
- 路径: GET /api/users/{id}
- 认证: Bearer Token
- 中间件: jwt.auth
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| id | integer | 是 | 用户ID | 正整数 |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "id": 1,
    "name": "用户昵称",
    "email": "user@example.com",
    "phone": "13800138000",
    "avatar": "/storage/avatars/1.jpg",
    "role": "user",
    "membership_tier": "energy",
    "body_type": "ectomorph",
    "user_type": "regular",
    "personal_volume_multiplier": 1.0,
    "created_at": "2026-02-28T10:00:00Z",
    "profile": {
      "id": 1,
      "user_id": 1,
      "basic_info": {},
      "fitness_goals": {}
    }
  },
  "msg": "获取用户详情成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | 用户不存在 | 用户ID不存在 |

---

### 获取用户列表（管理员）
- 路径: GET /api/users?page=1&per_page=20&role=user&status=active&search=keyword
- 认证: Bearer Token
- 中间件: jwt.auth, role:admin
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| page | integer | 否 | 页码 | 默认1 |
| per_page | integer | 否 | 每页数量 | 默认20 |
| role | string | 否 | 角色过滤 | user/admin/moderator |
| status | string | 否 | 状态过滤 | active/inactive/banned |
| search | string | 否 | 搜索关键词 | 用户名/邮箱/手机号 |

- 响应示例:
```json
{
  "code": 200,
  "data": [
    {
      "id": 1,
      "name": "用户昵称",
      "email": "user@example.com",
      "phone": "13800138000",
      "role": "user",
      "membership_tier": "energy",
      "created_at": "2026-02-28T10:00:00Z"
    }
  ],
  "meta": {
    "total": 100,
    "page": 1,
    "per_page": 20,
    "last_page": 5
  },
  "msg": "获取用户列表成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 403 | 无权限 | 非管理员 |

---

### 创建用户（管理员）
- 路径: POST /api/users
- 认证: Bearer Token
- 中间件: jwt.auth, role:admin
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| name | string | 是 | 用户名 | 唯一 |
| email | string | 是 | 邮箱 | 有效邮箱，唯一 |
| password | string | 是 | 密码 | 至少6位 |
| phone | string | 否 | 手机号 | 中国手机号格式 |
| role | string | 否 | 角色 | user/admin/moderator，默认user |
| membership_tier | string | 否 | 会员等级 | free/warmheart/energy，默认free |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "id": 2,
    "name": "新用户",
    "email": "newuser@example.com",
    "phone": null,
    "role": "user",
    "membership_tier": "free",
    "created_at": "2026-02-28T10:00:00Z"
  },
  "msg": "用户创建成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 403 | 无权限 | 非管理员 |
| 422 | 邮箱已被注册 | 邮箱重复 |

---

### 更新用户（管理员）
- 路径: PUT /api/users/{id}
- 认证: Bearer Token
- 中间件: jwt.auth, role:admin
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| id | integer | 是 | 用户ID | 正整数 |
| name | string | 否 | 用户名 | 唯一 |
| email | string | 否 | 邮箱 | 有效邮箱，唯一 |
| phone | string | 否 | 手机号 | 中国手机号格式 |
| role | string | 否 | 角色 | user/admin/moderator |
| membership_tier | string | 否 | 会员等级 | free/warmheart/energy |

- 响应示例:
```json
{
  "code": 200,
  "data": {
    "id": 1,
    "name": "更新后的用户名",
    "email": "user@example.com",
    "phone": "13800138000",
    "role": "admin",
    "membership_tier": "energy",
    "updated_at": "2026-02-28T10:05:00Z"
  },
  "msg": "用户更新成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 403 | 无权限 | 非管理员 |
| 404 | 用户不存在 | 用户ID不存在 |
| 422 | 邮箱已被注册 | 邮箱重复 |

---

### 删除用户（管理员）
- 路径: DELETE /api/users/{id}
- 认证: Bearer Token
- 中间件: jwt.auth, role:admin
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| id | integer | 是 | 用户ID | 正整数 |

- 响应示例:
```json
{
  "code": 200,
  "data": null,
  "msg": "用户删除成功"
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 未认证 | Token 无效或已过期 |
| 403 | 无权限 | 非管理员 |
| 404 | 用户不存在 | 用户ID不存在 |
