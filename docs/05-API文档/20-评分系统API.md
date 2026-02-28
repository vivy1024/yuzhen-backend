# 评分系统 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/quality-rating.php

## 概述

评分系统 API 实现玉珍健身的三轨评分体系，用于评估 AI 对话质量。主要功能包括：
1. 用户体验评分（5维度）：清晰度、实用性、详细度、友好度、满意度
2. 个性化感知评分（4维度，自动计算）：档案利用率、目标对齐度、独特性、动态调整
3. 专家专业评分（6维度）：准确性、科学性、安全性、完整性、实用性、个性化

同时提供 Few-Shot 资格检查、冷启动期保护和评分统计等功能。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| POST | /api/v2/quality/rating | 提交三轨评分 | Bearer Token | 无 |
| GET | /api/v2/quality/rating/{session_id} | 获取会话评分 | Bearer Token | 无 |
| GET | /api/v2/quality/rating/{session_id}/eligibility | 检查 Few-Shot 资格 | Bearer Token | 无 |
| POST | /api/v2/quality/rating/{session_id}/expert | 提交专家评审 | Bearer Token | 专家/管理员 |
| GET | /api/v2/quality/cold-start-status | 获取冷启动状态 | Bearer Token | 无 |
| GET | /api/v2/quality/fewshot-eligible | 获取 Few-Shot 合格会话 | Bearer Token | 管理员 |
| GET | /api/v2/quality/fewshot-pool-stats | 获取 Few-Shot 池统计 | Bearer Token | 管理员 |
| GET | /api/v2/quality/stats | 获取评分统计 | Bearer Token | 管理员 |

## 详细说明

### 提交三轨评分
- 路径: POST /api/v2/quality/rating
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 用户提交对会话的体验评分，系统会自动计算个性化感知评分

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| session_id | string | 是 | 会话ID | 必填 |
| user_experience | array | 是 | 用户体验评分 | 必填 |
| user_experience.clarity | integer | 是 | 清晰度 | 1-5 |
| user_experience.practicality | integer | 是 | 实用性 | 1-5 |
| user_experience.detail | integer | 是 | 详细度 | 1-5 |
| user_experience.friendliness | integer | 是 | 友好度 | 1-5 |
| user_experience.satisfaction | integer | 是 | 满意度 | 1-5 |
| feedback_text | string | 否 | 用户反馈文本 | 最大1000字符 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "评分提交成功",
  "data": {
    "session_id": "abc123",
    "user_experience": {
      "clarity": 5,
      "practicality": 4,
      "detail": 4,
      "friendliness": 5,
      "satisfaction": 4
    },
    "personalization": {
      "profile_utilization_rate": 85,
      "goal_alignment": 70,
      "uniqueness": 60,
      "dynamic_adjustment": 50
    },
    "personalization_grade": "A",
    "overall_score": 4.2,
    "fewshot_eligible": true,
    "expert_review": null
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 参数验证失败 | 请求参数不符合规则 |
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 无权限为此会话评分 | 不是自己的会话 |
| 404 | 会话不存在 | session_id 无效 |

---

### 获取会话评分
- 路径: GET /api/v2/quality/rating/{session_id}
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 获取指定会话的完整评分信息，普通用户只能查看自己的会话

- 请求参数:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| session_id | string | 是 | 会话ID（路径参数） |

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "session_id": "abc123",
    "user_experience": {
      "clarity": 5,
      "practicality": 4,
      "detail": 4,
      "friendliness": 5,
      "satisfaction": 4
    },
    "personalization": {
      "profile_utilization_rate": 85,
      "goal_alignment": 70,
      "uniqueness": 60,
      "dynamic_adjustment": 50
    },
    "personalization_grade": "A",
    "overall_score": 4.2,
    "fewshot_eligible": true,
    "user_feedback": "回答很详细",
    "expert_review": {
      "accuracy": 5,
      "scientific": 4,
      "safety": 5,
      "completeness": 4,
      "practicality": 4,
      "personalization": 4
    },
    "created_at": "2026-01-15T10:00:00Z",
    "updated_at": "2026-01-15T10:05:00Z"
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 无权限查看此会话评分 | 无权查看 |
| 404 | 会话不存在 | session_id 无效 |

---

### 检查 Few-Shot 资格
- 路径: GET /api/v2/quality/rating/{session_id}/eligibility
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 检查指定会话是否符合 Few-Shot 池准入条件

- 请求参数:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| session_id | string | 是 | 会话ID（路径参数） |

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "session_id": "abc123",
    "eligible": true,
    "reason": "三轨评分均达标",
    "details": {
      "user_experience_avg": 4.4,
      "personalization_avg": 66.25,
      "overall_score": 4.2,
      "safety_check": "passed"
    }
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 无权限查看此会话 | 无权查看 |
| 404 | 会话不存在 | session_id 无效 |

---

### 提交专家评审
- 路径: POST /api/v2/quality/rating/{session_id}/expert
- 认证: Bearer Token
- 中间件: jwt.auth
- 权限: 专家或管理员

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| accuracy | integer | 是 | 准确性 | 1-5 |
| scientific | integer | 是 | 科学性 | 1-5 |
| safety | integer | 是 | 安全性 | 1-5 |
| completeness | integer | 是 | 完整性 | 1-5 |
| practicality | integer | 是 | 实用性 | 1-5 |
| personalization | integer | 是 | 个性化 | 1-5 |
| comments | string | 否 | 评审意见 | 最大2000字符 |
| improvement_suggestions | array | 否 | 改进建议 | 数组 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "专家评审提交成功",
  "data": {
    "session_id": "abc123",
    "expert_review": {
      "accuracy": 5,
      "scientific": 4,
      "safety": 5,
      "completeness": 4,
      "practicality": 4,
      "personalization": 4,
      "comments": "整体表现良好"
    },
    "overall_score": 4.3,
    "fewshot_eligible": true,
    "eligibility_details": {
      "eligible": true,
      "reason": "三轨评分均达标，安全性通过"
    }
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 参数验证失败 | 请求参数不符合规则 |
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 只有专家或管理员可以提交专家评审 | 权限不足 |
| 404 | 会话不存在 | session_id 无效 |

---

### 获取冷启动状态
- 路径: GET /api/v2/quality/cold-start-status
- 认证: Bearer Token
- 中间件: jwt.auth
- 说明: 获取用户是否处于冷启动期（前3条对话享受评分门槛降低）

- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "is_cold_start": true,
    "session_count": 2,
    "cold_start_threshold": 3,
    "remaining_cold_start_sessions": 1,
    "rated_session_count": 1,
    "eligible_session_count": 1,
    "cold_start_benefits": [
      "降低评分门槛（3.5分即可进入Few-Shot池）",
      "帮助系统快速学习您的偏好",
      "提供更个性化的建议"
    ],
    "message": "您还有 1 次冷启动期对话机会，评分门槛已降低"
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |

---

### 获取 Few-Shot 合格会话列表
- 路径: GET /api/v2/quality/fewshot-eligible
- 认证: Bearer Token
- 中间件: jwt.auth
- 权限: 管理员
- 说明: 获取符合 Few-Shot 池条件的会话列表

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| limit | integer | 否 | 每页数量 | 默认20 |
| page | integer | 否 | 页码 | 默认1 |

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "items": [
      {
        "session_id": "abc123",
        "user_query": "如何增肌？",
        "llm_response": "增肌需要...",
        "tools_used": ["exercise_db"],
        "user_experience": {
          "clarity": 5,
          "practicality": 4,
          "detail": 4,
          "friendliness": 5,
          "satisfaction": 4
        },
        "personalization": {
          "profile_utilization_rate": 85,
          "goal_alignment": 70,
          "uniqueness": 60,
          "dynamic_adjustment": 50
        },
        "personalization_grade": "A",
        "overall_score": 4.2,
        "created_at": "2026-01-15T10:00:00Z"
      }
    ],
    "total": 150,
    "page": 1,
    "limit": 20
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 权限不足 | 非管理员无法访问 |

---

### 获取 Few-Shot 池统计
- 路径: GET /api/v2/quality/fewshot-pool-stats
- 认证: Bearer Token
- 中间件: jwt.auth
- 权限: 管理员
- 说明: 获取 Few-Shot 池的整体统计信息

- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "total_sessions": 1000,
    "fewshot_eligible": 150,
    "fewshot_rate": 15.0,
    "pool_stats": {
      "avg_overall_score": 4.1,
      "avg_user_experience": 4.3,
      "avg_personalization": 68.5
    }
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 权限不足 | 非管理员无法访问 |

---

### 获取评分统计
- 路径: GET /api/v2/quality/stats
- 认证: Bearer Token
- 中间件: jwt.auth
- 权限: 管理员
- 说明: 获取全局评分统计数据

- 请求参数: 无

- 响应示例:
```json
{
  "code": 200,
  "msg": "获取成功",
  "data": {
    "total_sessions": 1000,
    "rated_sessions": 650,
    "fewshot_eligible": 150,
    "fewshot_rate": 15.0,
    "grade_distribution": {
      "A": 200,
      "B": 300,
      "C": 100,
      "D": 50
    },
    "avg_scores": {
      "ux_clarity": 4.2,
      "ux_practicality": 4.0,
      "ux_detail": 3.8,
      "ux_friendliness": 4.5,
      "ux_satisfaction": 4.1,
      "profile_utilization_rate": 65.5,
      "overall_score": 4.1
    },
    "expert_review_count": 50,
    "unsafe_count": 2
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未认证 | Token 无效或已过期 |
| 403 | 权限不足 | 非管理员无法访问 |