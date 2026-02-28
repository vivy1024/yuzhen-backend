# 反馈系统 API

> 自动生成 | 对应路由文件: routes/modules/feedback.php

## 概述

反馈系统 API 为用户提供提交反馈、查看反馈列表、上传截图等功能。该模块允许用户提交功能建议、问题反馈、疑问等，并可上传截图以便更好地描述问题。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/feedback | 获取用户反馈列表 | Bearer Token | 用户 |
| POST | /api/feedback | 提交新反馈 | Bearer Token | 用户 |
| GET | /api/feedback/{id} | 获取反馈详情 | Bearer Token | 用户 |
| POST | /api/feedback/upload | 上传反馈截图 | Bearer Token | 用户 |

## 详细说明

### 1. 获取用户反馈列表

**GET** `/api/feedback`

获取当前用户提交的所有反馈列表，按创建时间倒序排列。

**响应示例**

```json
{
  "code": 200,
  "msg": "获取反馈列表成功",
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "type": "feature",
      "content": "希望增加xxx功能",
      "images": ["https://cdn.example.com/feedback/2026/01/xxx.jpg"],
      "contact": "user@example.com",
      "status": "pending",
      "created_at": "2026-01-20T10:30:00+08:00"
    }
  ]
}
```

---

### 2. 提交新反馈

**POST** `/api/feedback`

提交一个新的用户反馈。

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| type | string | 是 | 反馈类型：`feature`（功能建议）、`bug`（问题反馈）、`question`（疑问）、`other`（其他） |
| content | string | 是 | 反馈内容，最多2000字 |
| images | array | 否 | 截图URL数组，最多3张 |
| contact | string | 否 | 联系方式，最多100字 |

**请求示例**

```json
{
  "type": "bug",
  "content": "训练计划无法正常保存",
  "images": [
    "https://cdn.example.com/feedback/2026/01/xxx.jpg"
  ],
  "contact": "user@example.com"
}
```

**响应示例**

```json
{
  "code": 200,
  "msg": "反馈提交成功",
  "data": {
    "id": 2,
    "user_id": 123,
    "type": "bug",
    "content": "训练计划无法正常保存",
    "images": ["https://cdn.example.com/feedback/2026/01/xxx.jpg"],
    "contact": "user@example.com",
    "status": "pending",
    "created_at": "2026-01-20T10:35:00+08:00"
  }
}
```

**验证错误**

```json
{
  "code": 422,
  "msg": "请选择反馈类型",
  "data": null
}
```

---

### 3. 获取反馈详情

**GET** `/api/feedback/{id}` 获取单条反馈的详细信息。

**路径参数**

| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 反馈ID |

**响应示例**

```json
{
  "code": 200,
  "msg": "获取反馈详情成功",
  "data": {
    "id": 1,
    "user_id": 123,
    "type": "feature",
    "content": "希望增加xxx功能",
    "images": ["https://cdn.example.com/feedback/2026/01/xxx.jpg"],
    "contact": "user@example.com",
    "status": "resolved",
    "reply": "感谢您的建议，已安排开发",
    "reply_at": "2026-01-21T09:00:00+08:00",
    "created_at": "2026-01-20T10:30:00+08:00"
  }
}
```

**错误响应**

```json
{
  "code": 404,
  "msg": "反馈不存在",
  "data": null
}
```

---

### 4. 上传反馈截图

**POST** `/api/feedback/upload`

上传反馈截图图片，返回图片URL。

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | 是 | 图片文件，最大5MB，支持 jpg、png、gif、webp |

**响应示例**

```json
{
  "code": 200,
  "msg": "上传成功",
  "data": {
    "url": "https://cdn.example.com/feedback/2026/01/feedback_123_1705734900_abc123.jpg"
  }
}
```

**验证错误**

```json
{
  "code": 422,
  "msg": "请选择图片",
  "data": null
}
```

---

## 错误码

| 错误码 | 说明 |
|--------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未登录或Token无效 |
| 404 | 反馈不存在 |
| 422 | 验证失败 |
| 500 | 服务器内部错误 |

## 反馈状态说明

| 状态 | 说明 |
|------|------|
| pending | 待处理 |
| processing | 处理中 |
| resolved | 已解决 |
| closed | 已关闭 |