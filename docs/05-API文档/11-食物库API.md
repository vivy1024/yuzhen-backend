# 食物库 API

> 自动生成于 2026-02-28 | 对应路由文件: routes/modules/food.php

## 概述

食物库 API 提供食物数据的查询、搜索和分类功能。包括获取食物列表、详情、分类、筛选选项等接口。所有查询接口无需认证，仅清除缓存接口需要管理员权限。

## 端点列表

| 方法 | 路径 | 说明 | 认证 | 权限 |
|------|------|------|------|------|
| GET | /api/foods/categories | 获取分类列表 | 无 | - |
| GET | /api/foods/filter-options | 获取筛选选项 | 无 | - |
| GET | /api/foods/clear-cache | 清除缓存 | Bearer Token | admin |
| GET | /api/foods/search | 搜索食物 | 无 | - |
| GET | /api/foods | 获取食物列表 | 无 | - |
| GET | /api/foods/{id} | 获取食物详情 | 无 | - |

## 详细说明

### 获取分类列表

- **路径**: GET /api/foods/categories
- **认证**: 无
- **中间件**: 无
- **请求参数**: 无
- **缓存**: 24小时 HTTP 缓存

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": [
    {
      "id": 1,
      "name": "Meat & Poultry",
      "name_zh": "肉类和家禽",
      "subcategories": [
        {
          "id": 101,
          "name": "Beef",
          "name_zh": "牛肉"
        },
        {
          "id": 102,
          "name": "Chicken",
          "name_zh": "鸡肉"
        }
      ]
    },
    {
      "id": 2,
      "name": "Vegetables",
      "name_zh": "蔬菜",
      "subcategories": [
        {
          "id": 201,
          "name": "Leafy Greens",
          "name_zh": "叶菜类"
        }
      ]
    }
  ]
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | 获取分类列表失败 | 服务器内部错误 |

---

### 获取筛选选项

- **路径**: GET /api/foods/filter-options
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
    "categories": [
      {
        "id": 1,
        "name": "Meat & Poultry",
        "name_zh": "肉类和家禽"
      },
      {
        "id": 2,
        "name": "Vegetables",
        "name_zh": "蔬菜"
      }
    ],
    "subcategories": [
      {
        "id": 101,
        "name": "Beef",
        "name_zh": "牛肉",
        "category_id": 1
      }
    ],
    "protein_ranges": [
      {
        "min": 0,
        "max": 10,
        "label": "Low"
      },
      {
        "min": 10,
        "max": 20,
        "label": "Medium"
      },
      {
        "min": 20,
        "max": 100,
        "label": "High"
      }
    ],
    "calorie_ranges": [
      {
        "min": 0,
        "max": 100,
        "label": "Low"
      },
      {
        "min": 100,
        "max": 300,
        "label": "Medium"
      },
      {
        "min": 300,
        "max": 1000,
        "label": "High"
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

### 清除缓存

- **路径**: GET /api/foods/clear-cache
- **认证**: Bearer Token（必需）
- **中间件**: jwt.auth, role:admin
- **权限**: 仅管理员可操作
- **请求参数**: 无
- **说明**: 用于数据更新后刷新缓存，防止缓存击穿

#### 请求示例

```
GET /api/foods/clear-cache
Authorization: Bearer {token}
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "cleared": true,
    "categories_count": 15
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 401 | 用户未登录 | 未提供有效的 JWT Token |
| 403 | 权限不足 | 用户不是管理员 |
| 500 | 清除缓存失败 | 服务器内部错误 |

---

### 搜索食物

- **路径**: GET /api/foods/search
- **认证**: 无
- **中间件**: 无
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 默认值 | 验证规则 | 说明 |
|------|------|------|--------|---------|------|
| q | string | 是 | - | required, max:200 | 搜索关键词 |
| page | integer | 否 | 1 | min:1 | 页码 |
| per_page | integer | 否 | 20 | min:1, max:100 | 每页条数 |

#### 请求示例

```
GET /api/foods/search?q=鸡肉&page=1&per_page=20
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "keyword": "鸡肉",
    "results": [
      {
        "id": 1,
        "food_code": "MEAT_CHICKEN_001",
        "name": "Chicken Breast",
        "name_zh": "鸡胸肉",
        "category": "Meat & Poultry",
        "category_zh": "肉类和家禽",
        "subcategory": "Chicken",
        "subcategory_zh": "鸡肉",
        "energy_kcal": 165,
        "protein": 31,
        "fat": 3.6,
        "carbohydrate": 0,
        "fiber": 0
      }
    ],
    "total": 8
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 400 | 搜索关键词不能为空 | 未提供搜索关键词 |
| 500 | 搜索食物失败 | 服务器内部错误 |

---

### 获取食物列表

- **路径**: GET /api/foods
- **认证**: 无
- **中间件**: 无
- **请求参数**: 见下表

#### 请求参数

| 参数 | 类型 | 必填 | 默认值 | 验证规则 | 说明 |
|------|------|------|--------|---------|------|
| category | string | 否 | - | - | 分类ID或名称 |
| subcategory | string | 否 | - | - | 子分类ID或名称 |
| search | string | 否 | - | max:200 | 搜索关键词 |
| query | string | 否 | - | max:200 | 搜索关键词（同search） |
| high_protein | boolean | 否 | false | - | 高蛋白筛选 |
| low_calorie | boolean | 否 | false | - | 低热量筛选 |
| sort_by | string | 否 | id | - | 排序字段（id/name/energy_kcal/protein） |
| sort_order | string | 否 | asc | - | 排序顺序（asc/desc） |
| page | integer | 否 | 1 | min:1 | 页码 |
| per_page | integer | 否 | 20 | min:1, max:100 | 每页条数 |

#### 请求示例

```
GET /api/foods?category=Meat&high_protein=true&sort_by=protein&sort_order=desc&page=1&per_page=20
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": [
    {
      "id": 1,
      "food_code": "MEAT_CHICKEN_001",
      "name": "Chicken Breast",
      "name_zh": "鸡胸肉",
      "category": "Meat & Poultry",
      "category_zh": "肉类和家禽",
      "subcategory": "Chicken",
      "subcategory_zh": "鸡肉",
      "energy_kcal": 165,
      "protein": 31,
      "fat": 3.6,
      "carbohydrate": 0,
      "fiber": 0
    },
    {
      "id": 2,
      "food_code": "MEAT_BEEF_001",
      "name": "Lean Beef",
      "name_zh": "瘦牛肉",
      "category": "Meat & Poultry",
      "category_zh": "肉类和家禽",
      "subcategory": "Beef",
      "subcategory_zh": "牛肉",
      "energy_kcal": 250,
      "protein": 26,
      "fat": 15,
      "carbohydrate": 0,
      "fiber": 0
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_count": 95,
    "per_page": 20
  }
}
```

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 500 | 获取食物列表失败 | 服务器内部错误 |

---

### 获取食物详情

- **路径**: GET /api/foods/{id}
- **认证**: 无
- **中间件**: 无
- **路径参数**:
  - id (integer, 必需): 食物ID

#### 请求示例

```
GET /api/foods/1
```

#### 响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "id": 1,
    "food_code": "MEAT_CHICKEN_001",
    "name": "Chicken Breast",
    "name_zh": "鸡胸肉",
    "category": "Meat & Poultry",
    "category_zh": "肉类和家禽",
    "subcategory": "Chicken",
    "subcategory_zh": "鸡肉",
    "energy_kcal": 165,
    "protein": 31,
    "fat": 3.6,
    "carbohydrate": 0,
    "fiber": 0,
    "water": 68.3,
    "ash": 1.1,
    "calcium": 11,
    "phosphorus": 220,
    "iron": 0.9,
    "sodium": 75,
    "potassium": 366,
    "magnesium": 29,
    "zinc": 0.8,
    "copper": 0.05,
    "manganese": 0.02,
    "vitamin_a": 0,
    "vitamin_b1": 0.07,
    "vitamin_b2": 0.1,
    "vitamin_b3": 9.5,
    "vitamin_b5": 1.0,
    "vitamin_b6": 0.9,
    "vitamin_b12": 0.3,
    "vitamin_c": 0,
    "vitamin_d": 0.1,
    "vitamin_e": 0.3,
    "folate": 3,
    "description": "High-quality protein source, low in fat",
    "description_zh": "优质蛋白质来源，脂肪含量低",
    "serving_size": 100,
    "serving_unit": "g"
  }
}
```

#### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| id | integer | 食物ID |
| food_code | string | 食物代码 |
| name | string | 英文名称 |
| name_zh | string | 中文名称 |
| category | string | 分类 |
| category_zh | string | 分类（中文） |
| subcategory | string | 子分类 |
| subcategory_zh | string | 子分类（中文） |
| energy_kcal | float | 能量（千卡） |
| protein | float | 蛋白质（克） |
| fat | float | 脂肪（克） |
| carbohydrate | float | 碳水化合物（克） |
| fiber | float | 纤维（克） |
| water | float | 水分（克） |
| ash | float | 灰分（克） |
| calcium | float | 钙（毫克） |
| phosphorus | float | 磷（毫克） |
| iron | float | 铁（毫克） |
| sodium | float | 钠（毫克） |
| potassium | float | 钾（毫克） |
| magnesium | float | 镁（毫克） |
| zinc | float | 锌（毫克） |
| copper | float | 铜（毫克） |
| manganese | float | 锰（毫克） |
| vitamin_a | float | 维生素A（微克） |
| vitamin_b1 | float | 维生素B1（毫克） |
| vitamin_b2 | float | 维生素B2（毫克） |
| vitamin_b3 | float | 维生素B3（毫克） |
| vitamin_b5 | float | 维生素B5（毫克） |
| vitamin_b6 | float | 维生素B6（毫克） |
| vitamin_b12 | float | 维生素B12（微克） |
| vitamin_c | float | 维生素C（毫克） |
| vitamin_d | float | 维生素D（微克） |
| vitamin_e | float | 维生素E（毫克） |
| folate | float | 叶酸（微克） |
| description | string | 英文描述 |
| description_zh | string | 中文描述 |
| serving_size | integer | 份量大小 |
| serving_unit | string | 份量单位 |

#### 错误码

| 状态码 | 错误信息 | 说明 |
|--------|---------|------|
| 404 | 食物不存在 | 指定的食物ID不存在 |
| 500 | 获取食物详情失败 | 服务器内部错误 |

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

### 分页响应格式

列表接口返回分页信息：

```json
{
  "code": 200,
  "msg": "success",
  "data": [...],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_count": 95,
    "per_page": 20
  }
}
```

### 营养数据说明

- 所有营养数据基于 100g 食物
- 单位说明：
  - 能量：千卡（kcal）
  - 宏量营养素（蛋白质、脂肪、碳水化合物、纤维、水分、灰分）：克（g）
  - 矿物质（钙、磷、铁、钠、钾、镁、锌、铜、锰）：毫克（mg）
  - 维生素A、B12、D、叶酸：微克（μg）
  - 其他维生素：毫克（mg）

### 高蛋白筛选

- 当 `high_protein=true` 时，返回蛋白质含量 ≥ 20g/100g 的食物

### 低热量筛选

- 当 `low_calorie=true` 时，返回能量 ≤ 100 kcal/100g 的食物

### 排序字段

支持的排序字段：
- `id` - 按ID排序
- `name` - 按名称排序
- `energy_kcal` - 按能量排序
- `protein` - 按蛋白质排序

### 缓存策略

- 分类列表、筛选选项使用 24 小时 HTTP 缓存
- 清除缓存接口需要管理员权限
- 缓存清除后会自动重新加载数据
