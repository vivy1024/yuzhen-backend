# 知识库 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/knowledge.php

## 概述

知识库 API 提供健身知识内容的浏览、搜索和管理功能。支持获取知识文章列表、查看文章详情、获取分类树、随机知识卡片以及全文搜索等功能。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/knowledge | 获取知识列表（分页） | 无 | 无 |
| GET | /api/knowledge/{id} | 获取知识详情 | 无 | 无 |
| GET | /api/knowledge/categories | 获取分类树 | 无 | 无 |
| GET | /api/knowledge/cards | 获取知识卡片（随机） | 无 | 无 |
| GET | /api/knowledge/search | 搜索知识 | 无 | 无 |

## 详细说明

### 获取知识列表（分页）
- 路径: GET /api/knowledge
- 认证: 无
- 中间件: 无
- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| per_page | integer | 否 | 每页数量 | 默认15，最大50 |
| category_id | integer | 否 | 分类ID | 整数 |
| tag | string | 否 | 标签 | 字符串 |
| difficulty | string | 否 | 难度级别 | 字符串 |

- 响应示例:
```json
{
  "data": [
    {
      "id": 1,
      "title": "卧推的正确姿势",
      "summary": "本文介绍卧推的基本要领...",
      "category_id": 1,
      "tags": ["胸肌", "力量训练"],
      "source_book": "健身百科全书",
      "difficulty": "beginner",
      "view_count": 1234,
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

- 错误码: 无

---

### 获取知识详情
- 路径: GET /api/knowledge/{id}
- 认证: 无
- 中间件: 无
- 说明: 获取文章详情时会自动增加阅读计数，并返回相关文章推荐

- 请求参数:

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | integer | 是 | 文章ID（路径参数） |

- 响应示例:
```json
{
  "data": {
    "id": 1,
    "title": "卧推的正确姿势",
    "summary": "本文介绍卧推的基本要领...",
    "content": "详细内容...",
    "category_id": 1,
    "category": {
      "id": 1,
      "name": "胸部训练",
      "slug": "chest-training"
    },
    "tags": ["胸肌", "力量训练"],
    "source_book": "健身百科全书",
    "difficulty": "beginner",
    "view_count": 1235,
    "created_at": "2026-01-15T10:00:00Z",
    "updated_at": "2026-01-20T15:30:00Z",
    "references": [],
    "related": [
      {
        "id": 2,
        "title": "哑铃卧推技巧",
        "summary": "哑铃卧推的要点...",
        "difficulty": "intermediate",
        "view_count": 856
      }
    ]
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | Not Found | 文章不存在 |

---

### 获取分类树
- 路径: GET /api/knowledge/categories
- 认证: 无
- 中间件: 无
- 说明: 返回树形结构的分类列表，每个分类包含文章数量

- 请求参数: 无

- 响应示例:
```json
{
  "data": [
    {
      "id": 1,
      "name": "胸部训练",
      "slug": "chest-training",
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "name": "卧推",
          "slug": "bench-press",
          "parent_id": 1,
          "children": [],
          "articles_count": 15
        }
      ],
      "articles_count": 30
    }
  ]
}
```

- 错误码: 无

---

### 获取知识卡片（随机）
- 路径: GET /api/knowledge/cards
- 认证: 无
- 中间件: 无
- 说明: 随机获取知识卡片，用于首页推荐等场景

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| count | integer | 否 | 获取数量 | 默认10，最大20 |
| category_id | integer | 否 | 分类ID | 整数 |

- 响应示例:
```json
{
  "data": [
    {
      "id": 1,
      "title": "卧推的正确姿势",
      "summary": "本文介绍卧推的基本要领...",
      "category_id": 1,
      "category": {
        "id": 1,
        "name": "胸部训练"
      },
      "tags": ["胸肌", "力量训练"]
    },
    {
      "id": 5,
      "title": "深蹲常见错误",
      "summary": "深蹲时常见的错误姿势...",
      "category_id": 3,
      "category": {
        "id": 3,
        "name": "腿部训练"
      },
      "tags": ["深蹲", "腿部"]
    }
  ]
}
```

- 错误码: 无

---

### 搜索知识
- 路径: GET /api/knowledge/search
- 认证: 无
- 中间件: 无
- 说明: 在标题、摘要和内容中进行全文搜索

- 请求参数:

| 参数 | 类型 | 必填 | 说明 | 验证规则 |
|------|------|------|------|---------|
| q | string | 是 | 搜索关键词 | 必填 |
| per_page | integer | 否 | 每页数量 | 默认15，最大50 |

- 响应示例:
```json
{
  "data": [
    {
      "id": 1,
      "title": "卧推的正确姿势",
      "summary": "本文介绍卧推的基本要领...",
      "category_id": 1,
      "tags": ["胸肌", "力量训练"],
      "source_book": "健身百科全书",
      "difficulty": "beginner",
      "view_count": 1234,
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 45
  }
}
```

- 错误码:

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 422 | validation_error | 搜索关键词不能为空 |