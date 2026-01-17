# 04-训练系统

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含训练计划管理、训练记录存储、动作库查询的代码实现细节。

---

## 📚 文档列表

### [01-训练系统总览](./01-训练系统总览.md)
- TrainingPlan模型设计
- TrainingSession模型设计
- 训练记录存储逻辑
- TrainingController实现

### [03-动作库系统](./03-动作库系统.md)
- Exercise模型设计
- 动作查询接口
- 动作分类和筛选
- 动作详情展示

---

## 🔧 核心组件

### TrainingPlan模型
- **模型文件**: `app/Models/TrainingPlan.php`
- **数据库表**: `training_plans`
- **AI字段**: exercises, target_muscles, safety_notes

### TrainingSession模型
- **模型文件**: `app/Models/TrainingSession.php`
- **数据库表**: `training_sessions`
- **记录内容**: 训练日期、动作、组数、次数、重量

### Exercise模型
- **模型文件**: `app/Models/Exercise.php`
- **数据库表**: `exercises`
- **动作数量**: 1,790个（34字段完整）

---

## 📊 数据结构

### training_plans表
```sql
CREATE TABLE training_plans (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    exercises JSON,
    target_muscles JSON,
    safety_notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### training_sessions表
```sql
CREATE TABLE training_sessions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    plan_id BIGINT,
    session_date DATE NOT NULL,
    exercises JSON,
    duration INT,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (plan_id) REFERENCES training_plans(id)
);
```

### exercises表
```sql
CREATE TABLE exercises (
    id BIGINT PRIMARY KEY,
    name_en VARCHAR(255) NOT NULL,
    name_zh VARCHAR(255),
    category VARCHAR(100),
    primary_muscle_zh VARCHAR(100),
    equipment VARCHAR(100),
    difficulty VARCHAR(50),
    description_zh TEXT,
    steps_zh JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 🔄 工作流程

### 训练计划创建流程
```
AI生成计划 → 前端提取数据 → 调用API → 保存到数据库 → 返回计划ID
```

### 训练记录保存流程
```
用户完成训练 → 记录数据 → 调用API → 保存到数据库 → 更新统计
```

### 动作查询流程
```
用户搜索 → 筛选条件 → 查询数据库 → 返回动作列表 → 展示详情
```

---

## 🔗 相关文档

- [API文档](../../05-API文档/03-训练系统API.md)
- [AI聊天系统](../05-AI聊天系统/README.md)
- [数据库设计](../08-数据库层/01-数据库结构总览.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
