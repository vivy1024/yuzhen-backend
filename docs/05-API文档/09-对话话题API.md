# 对话话题 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/chat-topic.php

## 概述

提供 AI 聊天话题的增删查改接口，用于组织用户的对话历史。包含对话历史检索、会话管理、话题管理和消息同步等功能。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 限流 |
|------|------|------|------|------|
| GET | /api/chat/history | 获取对话历史 | Bearer Token | - |
| GET | /api/chat/sessions | 获取会话列表 | Bearer Token | - |
| GET | /api/chat/sessions/{sessionId} | 获取会话详情 | Bearer Token | - |
| DELETE | /api/chat/sessions/{sessionId} | 删除会话 | Bearer Token | - |
| GET | /api/chat/topics | 获取话题列表 | Bearer Token | - |
| POST | /api/chat/topics | 创建话题 | Bearer Token | - |
| GET | /api/chat/topics/{id} | 获取话题详情 | Bearer Token | - |
| PUT | /api/chat/topics/{id} | 更新话题 | Bearer Token | - |
| DELETE | /api/chat/topics/{id} | 删除话题 | Bearer Token | - |
| GET | /api/chat/topics/{id}/messages | 获取话题消息 | Bearer Token | - |
| POST | /api/chat/topics/{id}/messages | 保存消息 | Bearer Token | - |
| POST | /api/chat/topics/{id}/messages/sync | 批量同步消息 | Bearer Token | - |

## 详细说明

### 获取对话历史
- 路径: GET /api/chat/history
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 返回用户的对话历史，支持按话题/会话筛选和分页

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| topic_id | string | 否 | 话题ID，用于筛选 | max:100 |
| session_id | string | 否 | 会话ID，用于筛选 | max:100 |
| limit | integer | 否 | 每页数量，默认20 | min:1, max:100 |
| offset | integer | 否 | 分页偏移量，默认0 | min:0 |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取成功",
  "data": {
    "total": 50,
    "limit": 20,
    "offset": 0,
    "history": [
      {
        "id": 1,
        "sessionId": "uuid-xxx",
        "topicId": "1",
        "userQuery": "如何制定训练计划？",
        "llmResponse": "根据你的目标...",
        "modelUsed": "claude-opus-4.6",
        "toolsUsed": ["training_plan_generator"],
        "userRating": 5,
        "userFeedback": "很有帮助",
        "metadata": {},
        "createdAt": "2026-02-28T10:00:00Z",
        "updatedAt": "2026-02-28T10:00:00Z"
      }
    ]
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

### 获取会话列表
- 路径: GET /api/chat/sessions
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 返回用户的会话列表，按session_id分组，每个会话显示最新的对话记录

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| limit | integer | 否 | 每页数量，默认20 | min:1, max:100 |
| offset | integer | 否 | 分页偏移量，默认0 | min:0 |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取成功",
  "data": {
    "total": 10,
    "limit": 20,
    "offset": 0,
    "sessions": [
      {
        "sessionId": "uuid-xxx",
        "title": "如何制定训练计划？",
        "topicId": "1",
        "messageCount": 5,
        "lastQuery": "还有其他建议吗？",
        "lastResponse": "当然，你还可以...",
        "modelUsed": "claude-opus-4.6",
        "createdAt": "2026-02-28T10:00:00Z",
        "updatedAt": "2026-02-28T10:05:00Z"
      }
    ]
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

### 获取会话详情
- 路径: GET /api/chat/sessions/{sessionId}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 返回指定会话的所有对话记录，按时间顺序排列

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| sessionId | string | 是 | 会话ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取成功",
  "data": {
    "sessionId": "uuid-xxx",
    "topicId": "1",
    "messageCount": 4,
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:05:00Z",
    "messages": [
      {
        "id": "user-1",
        "role": "user",
        "content": "如何制定训练计划？",
        "timestamp": 1740700800000
      },
      {
        "id": "assistant-1",
        "role": "assistant",
        "content": "根据你的目标...",
        "timestamp": 1740700801000,
        "modelUsed": "claude-opus-4.6",
        "toolsUsed": ["training_plan_generator"],
        "metadata": {}
      }
    ]
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 会话不存在 |
| 500 | Server Error | 服务器错误 |

---

### 删除会话
- 路径: DELETE /api/chat/sessions/{sessionId}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 删除指定会话的所有对话记录

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| sessionId | string | 是 | 会话ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "sessionId": "uuid-xxx",
    "deletedCount": 5
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 会话不存在 |
| 500 | Server Error | 服务器错误 |

---

### 获取话题列表
- 路径: GET /api/chat/topics
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 返回用户的所有话题，按最后消息时间倒序排列

**响应示例:**

```json
{
  "code": 0,
  "message": "获取成功",
  "data": [
    {
      "id": "1",
      "name": "训练计划讨论",
      "createdAt": "2026-02-28T10:00:00Z",
      "updatedAt": "2026-02-28T10:05:00Z",
      "messageCount": 15,
      "lastMessage": "感谢你的建议",
      "lastMessageAt": "2026-02-28T10:05:00Z"
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

### 创建话题
- 路径: POST /api/chat/topics
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 创建新的聊天话题

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| name | string | 是 | 话题名称 | required, max:100 |
| description | string | 否 | 话题描述 | nullable, string |

**请求示例:**

```json
{
  "name": "训练计划讨论",
  "description": "关于如何制定个性化训练计划的讨论"
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "1",
    "name": "训练计划讨论",
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:00:00Z",
    "messageCount": 0
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

### 获取话题详情
- 路径: GET /api/chat/topics/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取指定话题的详细信息

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 话题ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取成功",
  "data": {
    "id": "1",
    "name": "训练计划讨论",
    "description": "关于如何制定个性化训练计划的讨论",
    "createdAt": "2026-02-28T10:00:00Z",
    "updatedAt": "2026-02-28T10:05:00Z",
    "messageCount": 15,
    "lastMessage": "感谢你的建议",
    "lastMessageAt": "2026-02-28T10:05:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 话题不存在 |
| 500 | Server Error | 服务器错误 |

---

### 更新话题
- 路径: PUT /api/chat/topics/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 更新话题信息

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 话题ID |

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| name | string | 否 | 话题名称 | sometimes, required, max:100 |
| description | string | 否 | 话题描述 | nullable, string |

**请求示例:**

```json
{
  "name": "训练计划讨论（已更新）",
  "description": "更新后的描述"
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "1",
    "name": "训练计划讨论（已更新）",
    "description": "更新后的描述",
    "updatedAt": "2026-02-28T10:10:00Z"
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 话题不存在 |
| 422 | Validation Error | 参数验证失败 |
| 500 | Server Error | 服务器错误 |

---

### 删除话题
- 路径: DELETE /api/chat/topics/{id}
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 删除指定话题

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 话题ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "删除成功",
  "data": null
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 话题不存在 |
| 500 | Server Error | 服务器错误 |

---

### 获取话题消息
- 路径: GET /api/chat/topics/{id}/messages
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 获取指定话题的所有消息，按时间顺序排列

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 话题ID |

**响应示例:**

```json
{
  "code": 0,
  "message": "获取成功",
  "data": [
    {
      "id": "1",
      "topicId": "1",
      "role": "user",
      "content": "如何制定训练计划？",
      "timestamp": 1740700800000,
      "toolCalls": null,
      "trainingPlan": null
    },
    {
      "id": "2",
      "topicId": "1",
      "role": "assistant",
      "content": "根据你的目标...",
      "timestamp": 1740700801000,
      "toolCalls": [
        {
          "id": "tool-0",
          "name": "training_plan_generator",
          "status": "success"
        }
      ],
      "trainingPlan": {}
    }
  ]
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 话题不存在 |
| 500 | Server Error | 服务器错误 |

---

### 保存消息
- 路径: POST /api/chat/topics/{id}/messages
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 保存单条消息到话题，支持去重（通过client_id）

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 话题ID |

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| role | string | 是 | 消息角色 | required, in:user,assistant,system |
| content | string | 是 | 消息内容 | required, string |
| client_id | string | 否 | 客户端消息ID，用于去重 | nullable, max:64 |
| metadata | object | 否 | 消息元数据 | nullable, array |

**请求示例:**

```json
{
  "role": "user",
  "content": "如何制定训练计划？",
  "client_id": "msg-uuid-xxx",
  "metadata": {
    "source": "mobile"
  }
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "保存成功",
  "data": {
    "id": "1",
    "topicId": "1",
    "role": "user",
    "content": "如何制定训练计划？",
    "timestamp": 1740700800000
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 话题不存在 |
| 422 | Validation Error | 参数验证失败 |
| 500 | Server Error | 服务器错误 |

---

### 批量同步消息
- 路径: POST /api/chat/topics/{id}/messages/sync
- 认证: Bearer Token (jwt.auth)
- 中间件: jwt.auth
- 说明: 批量同步消息（从本地缓存同步到后端），支持去重和事务处理

**路径参数:**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 话题ID |

**请求参数:**

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| messages | array | 是 | 消息数组 | required, array |
| messages[].role | string | 是 | 消息角色 | required, in:user,assistant,system |
| messages[].content | string | 是 | 消息内容 | required, string |
| messages[].client_id | string | 是 | 客户端消息ID | required, max:64 |
| messages[].timestamp | integer | 否 | 消息时间戳 | nullable, integer |
| messages[].metadata | object | 否 | 消息元数据 | nullable, array |

**请求示例:**

```json
{
  "messages": [
    {
      "role": "user",
      "content": "如何制定训练计划？",
      "client_id": "msg-uuid-1",
      "timestamp": 1740700800000,
      "metadata": {}
    },
    {
      "role": "assistant",
      "content": "根据你的目标...",
      "client_id": "msg-uuid-2",
      "timestamp": 1740700801000,
      "metadata": {}
    }
  ]
}
```

**响应示例:**

```json
{
  "code": 0,
  "message": "同步完成",
  "data": {
    "synced": [
      {
        "client_id": "msg-uuid-1",
        "server_id": "1"
      },
      {
        "client_id": "msg-uuid-2",
        "server_id": "2"
      }
    ],
    "skipped": [],
    "synced_count": 2,
    "skipped_count": 0
  }
}
```

**错误码:**

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | Unauthorized | 未认证或Token过期 |
| 404 | Not Found | 话题不存在 |
| 422 | Validation Error | 参数验证失败 |
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
- ISO 8601 格式: `2026-02-28T10:00:00Z`
- 毫秒时间戳: `1740700800000`
