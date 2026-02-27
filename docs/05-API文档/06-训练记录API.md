# 训练记录API文档

**版本**: v1.0.0  
**创建日期**: 2025-12-19  
**状态**: ✅ 已实现

---

## 概述

训练记录API用于记录用户的训练数据和追踪力量进步。系统会自动估算1RM、评估力量水平，并生成力量进步曲线。

**核心功能**：
- 记录训练数据（重量、次数）
- 自动估算1RM（使用Epley公式）
- 动态评估力量水平（beginner/novice/intermediate/advanced/elite）
- 生成力量进步曲线
- 批量记录训练数据
- 删除训练记录

---

## API端点

### 1. 记录训练数据

**端点**: `POST /api/training/record`

**描述**: 记录单次训练数据，自动估算1RM和评估力量水平。

**请求体**:
```json
{
  "user_id": 1,
  "exercise_name": "squat",
  "weight": 100,
  "reps": 5,
  "date": "2025-01-15T10:30:00Z"  // 可选，默认当前时间
}
```

**响应**:
```json
{
  "code": 200,
  "msg": "训练数据记录成功",
  "data": {
    "exercise_name": "squat",
    "progress": {
      "history": [
        {
          "weight": 100,
          "reps": 5,
          "date": "2025-01-15T10:30:00Z",
          "estimated_1rm": 116.7
        }
      ],
      "current_1rm": 116.7,
      "strength_level": "intermediate",
      "last_updated": "2025-01-15T10:30:00Z"
    },
    "overall_strength_level": "intermediate"
  }
}
```

---

### 2. 批量记录训练数据

**端点**: `POST /api/training/record-batch`

**描述**: 批量记录多个动作的训练数据。

**请求体**:
```json
{
  "user_id": 1,
  "records": [
    {
      "exercise_name": "squat",
      "weight": 100,
      "reps": 5,
      "date": "2025-01-15T10:30:00Z"
    },
    {
      "exercise_name": "bench_press",
      "weight": 80,
      "reps": 5,
      "date": "2025-01-15T10:45:00Z"
    },
    {
      "exercise_name": "deadlift",
      "weight": 120,
      "reps": 5,
      "date": "2025-01-15T11:00:00Z"
    }
  ]
}
```

**响应**:
```json
{
  "code": 200,
  "msg": "批量记录训练数据成功",
  "data": {
    "records": [
      {
        "exercise_name": "squat",
        "progress": {
          "history": [...],
          "current_1rm": 116.7,
          "strength_level": "intermediate",
          "last_updated": "2025-01-15T10:30:00Z"
        }
      },
      {
        "exercise_name": "bench_press",
        "progress": {
          "history": [...],
          "current_1rm": 93.3,
          "strength_level": "novice",
          "last_updated": "2025-01-15T10:45:00Z"
        }
      },
      {
        "exercise_name": "deadlift",
        "progress": {
          "history": [...],
          "current_1rm": 140,
          "strength_level": "advanced",
          "last_updated": "2025-01-15T11:00:00Z"
        }
      }
    ],
    "overall_strength_level": "intermediate"
  }
}
```

---

### 3. 获取力量进步曲线（所有动作）

**端点**: `GET /api/training/progress/{user_id}`

**描述**: 获取用户所有动作的力量进步数据。

**响应**:
```json
{
  "code": 200,
  "data": {
    "strength_progress": {
      "squat": {
        "history": [
          {
            "weight": 80,
            "reps": 5,
            "date": "2025-01-01T00:00:00Z",
            "estimated_1rm": 90
          },
          {
            "weight": 100,
            "reps": 5,
            "date": "2025-01-15T10:30:00Z",
            "estimated_1rm": 116.7
          }
        ],
        "current_1rm": 116.7,
        "strength_level": "intermediate",
        "last_updated": "2025-01-15T10:30:00Z"
      },
      "bench_press": {
        "history": [...],
        "current_1rm": 93.3,
        "strength_level": "novice",
        "last_updated": "2025-01-15T10:45:00Z"
      }
    },
    "current_1rms": {
      "squat": 116.7,
      "bench_press": 93.3,
      "deadlift": 140
    },
    "overall_strength_level": "intermediate"
  }
}
```

---

### 4. 获取力量进步曲线（特定动作）

**端点**: `GET /api/training/progress/{user_id}/{exercise_name}`

**描述**: 获取用户特定动作的力量进步数据。

**示例**: `GET /api/training/progress/1/squat`

**响应**:
```json
{
  "code": 200,
  "data": {
    "exercise_name": "squat",
    "progress": {
      "history": [
        {
          "weight": 80,
          "reps": 5,
          "date": "2025-01-01T00:00:00Z",
          "estimated_1rm": 90
        },
        {
          "weight": 100,
          "reps": 5,
          "date": "2025-01-15T10:30:00Z",
          "estimated_1rm": 116.7
        }
      ],
      "current_1rm": 116.7,
      "strength_level": "intermediate",
      "last_updated": "2025-01-15T10:30:00Z"
    }
  }
}
```

---

### 5. 删除训练记录

**端点**: `DELETE /api/training/record/{user_id}/{exercise_name}/{index}`

**描述**: 删除用户特定动作的某条训练记录。

**示例**: `DELETE /api/training/record/1/squat/0`

**响应**:
```json
{
  "code": 200,
  "msg": "训练记录删除成功",
  "data": {
    "strength_progress": {
      "squat": {
        "history": [
          {
            "weight": 100,
            "reps": 5,
            "date": "2025-01-15T10:30:00Z",
            "estimated_1rm": 116.7
          }
        ],
        "current_1rm": 116.7,
        "strength_level": "intermediate",
        "last_updated": "2025-01-15T10:30:00Z"
      }
    }
  }
}
```

---

## 数据结构

### StrengthProgressRecord（训练记录）

```typescript
interface StrengthProgressRecord {
  weight: number;           // 重量（kg）
  reps: number;             // 次数
  date: string;             // 日期（ISO 8601）
  estimated_1rm: number;    // 估算的1RM
}
```

### ExerciseStrengthProgress（动作力量进步）

```typescript
interface ExerciseStrengthProgress {
  history: StrengthProgressRecord[];  // 历史记录
  current_1rm: number | null;         // 当前1RM
  strength_level: string | null;      // 力量水平
  last_updated: string | null;        // 最后更新时间
}
```

### StrengthProgress（力量进步曲线）

```typescript
interface StrengthProgress {
  [exerciseName: string]: ExerciseStrengthProgress;
}
```

---

## 力量水平评估

系统根据1RM/体重比例自动评估力量水平：

| 力量水平 | 深蹲 | 卧推 | 硬拉 |
|---------|------|------|------|
| **Untrained** | < 0.5× | < 0.3× | < 0.75× |
| **Beginner** | 0.5-1.0× | 0.3-0.6× | 0.75-1.25× |
| **Novice** | 1.0-1.5× | 0.6-1.0× | 1.25-1.75× |
| **Intermediate** | 1.5-2.0× | 1.0-1.5× | 1.75-2.5× |
| **Advanced** | 2.0-2.5× | 1.5-2.0× | 2.5-3.0× |
| **Elite** | > 2.5× | > 2.0× | > 3.0× |

**注意**: 这是简化版标准，实际应从Neo4j的StrengthStandard节点获取。

---

## 1RM估算公式

系统使用Epley公式估算1RM：

```
1RM = weight × (1 + reps / 30)
```

**示例**：
- 100kg × 5次 → 1RM = 100 × (1 + 5/30) = 116.7kg
- 80kg × 10次 → 1RM = 80 × (1 + 10/30) = 106.7kg

---

## 错误响应

### 422 Validation Error

```json
{
  "code": 400,
  "msg": "数据验证失败",
  "errors": {
    "weight": ["The weight field is required."],
    "reps": ["The reps must be at least 1."]
  }
}
```

### 404 Not Found

```json
{
  "code": 400,
  "msg": "未找到动作 squat 的训练记录"
}
```

### 500 Server Error

```json
{
  "code": 400,
  "msg": "记录训练数据失败：Database connection error"
}
```

---

## 使用示例

### 前端记录训练

```typescript
// 用户完成深蹲训练：100kg × 5次
const response = await fetch('/api/training/record', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    user_id: 1,
    exercise_name: 'squat',
    weight: 100,
    reps: 5,
  }),
});

const data = await response.json();
console.log('估算1RM:', data.data.progress.current_1rm);  // 116.7kg
console.log('力量水平:', data.data.progress.strength_level);  // intermediate
```

### 前端展示力量进步图表

```typescript
// 获取深蹲的力量进步曲线
const response = await fetch('/api/training/progress/1/squat');
const data = await response.json();

// 绘制图表
const chartData = data.data.progress.history.map(record => ({
  date: record.date,
  estimated_1rm: record.estimated_1rm,
}));

// 使用Chart.js或其他图表库绘制
```

### AI训练计划参考

```python
# DAML-RAG工作流获取用户力量进步数据
user_profile = await mcp_client.call_tool('get_user_profile', {'user_id': '1'})
strength_progress = user_profile['strength_progress']

# 根据力量进步曲线调整训练重量
squat_1rm = strength_progress['squat']['current_1rm']  # 116.7kg
training_weight = squat_1rm * 0.7  # 增肌训练使用70% 1RM = 81.7kg
```

---

## 相关文档

- [用户档案MCP数据结构](../../daml-rag-server/docs/02-核心架构/05-用户档案MCP数据结构.md)
- [数据库结构](../../daml-rag-server/docs/02-核心架构/04-数据库结构.md)
- [MCP工具架构](../../daml-rag-server/docs/02-核心架构/06-MCP工具架构.md)

---

**维护者**: 薛小川  
**最后更新**: 2025-12-19  
**状态**: ✅ 已实现
