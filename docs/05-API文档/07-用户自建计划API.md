# 用户自建计划 API

**版本**: v1.0.0 | **日期**: 2026-02-22

---

## 端点总览

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/training/plans | 创建计划（含 exercises + nutrition） |
| PUT | /api/training/plans/:id | 更新计划 |
| POST | /api/training/plans/:id/copy | 复制计划 |
| GET | /api/training/templates | 模板列表 |
| POST | /api/training/templates/:id/use | 从模板创建 |

> 基础 CRUD（GET列表/详情、DELETE）见 [前端03-训练计划API](../../yuzhen_fitness/docs/05-API文档/03-训练计划API.md)

---

## POST /api/training/plans — 创建计划

**请求体**:
```json
{
  "name": "增肌4周计划",
  "description": "针对上肢的增肌训练",
  "goal": "gain_muscle",
  "difficulty": "intermediate",
  "duration_weeks": 4,
  "workouts_per_week": 4,
  "exercises": [
    {
      "exercise_id": 123,
      "exercise_name": "杠铃深蹲",
      "day_of_week": 1,
      "sets": 4,
      "reps": "8-12",
      "weight": "60kg",
      "rest_time": "90s",
      "order_index": 0
    }
  ],
  "nutrition": [
    {
      "food_id": 42,
      "food_name": "鸡胸肉",
      "meal_type": "lunch",
      "portion_grams": 200,
      "day_of_week": 1
    }
  ]
}
```

**验证规则** (`UserPlanRequest`):

| 字段 | 规则 |
|------|------|
| name | required, string, max:100 |
| goal | nullable, in:gain_muscle,lose_weight,improve_fitness,maintain |
| difficulty | nullable, in:novice,beginner,intermediate,advanced |
| duration_weeks | integer, 1-52 |
| workouts_per_week | integer, 1-7 |
| exercises | required, array, min:1 |
| exercises.*.exercise_name | required, string |
| exercises.*.day_of_week | required, integer, 1-7 |
| exercises.*.sets | integer, 1-20 |
| nutrition | nullable, array |
| nutrition.*.food_name | required, string |
| nutrition.*.meal_type | required, in:breakfast,lunch,dinner,snack |
| nutrition.*.portion_grams | required, integer, min:10 |

---

## POST /api/training/plans/:id/copy — 复制计划

无请求体。复制所有 exercises + nutrition，名称添加"(副本)"后缀。

**响应**: 201，返回新计划完整数据。

---

## GET /api/training/templates — 模板列表

**查询参数**:
| 参数 | 类型 | 说明 |
|------|------|------|
| goal | string | 筛选目标 |
| level | string | 筛选难度 |

**响应**: 200，返回模板数组。

---

## POST /api/training/templates/:id/use — 从模板创建

无请求体。从模板的 `exercises_data` JSON 展开创建个人计划。

**响应**: 201，返回新计划数据。

---

**维护者**: 薛小川
