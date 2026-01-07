# 05-AI聊天系统

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-02

本目录包含AI聊天话题管理、消息历史、训练计划导入的代码实现细节。

---

## 📚 文档列表

### [01-话题管理实现](./01-话题管理实现.md)
- ChatTopic模型设计
- 话题CRUD操作
- 话题与会话关联
- ChatTopicController实现

### [02-消息历史存储](./02-消息历史存储.md)
- ChatSession模型设计
- 消息存储结构
- 消息历史查询
- 与DAML-RAG集成

### [03-训练计划导入](./03-训练计划导入.md)
- TrainingPlan模型扩展
- AI生成字段设计
- 训练计划导入逻辑
- TrainingPlanController实现

---

## 🔧 核心组件

### 话题管理
- **ChatTopic模型**: 话题数据模型
- **ChatTopicController**: 话题管理API
- **数据库表**: `chat_topics`

### 消息历史
- **ChatSession模型**: 会话消息模型
- **关联关系**: `topic_id`外键
- **数据库表**: `chat_sessions`

### 训练计划导入
- **TrainingPlan模型**: 训练计划模型
- **AI字段**: `exercises`, `target_muscles`, `safety_notes`
- **TrainingPlanController**: 训练计划API
- **数据库表**: `training_plans`

---

## 📊 数据结构

### chat_topics表
```sql
CREATE TABLE chat_topics (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    message_count INT DEFAULT 0,
    last_message_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### chat_sessions表（添加topic_id）
```sql
ALTER TABLE chat_sessions 
ADD COLUMN topic_id BIGINT,
ADD FOREIGN KEY (topic_id) REFERENCES chat_topics(id);
```

### training_plans表（添加AI字段）
```sql
ALTER TABLE training_plans
ADD COLUMN exercises JSON,
ADD COLUMN target_muscles JSON,
ADD COLUMN safety_notes TEXT;
```

---

## 🔄 工作流程

### 话题管理流程
```
创建话题 → 保存到数据库 → 返回话题ID
    ↓
发送消息 → 关联话题ID → 更新消息计数
    ↓
查询消息 → 按话题ID筛选 → 返回消息列表
```

### 训练计划导入流程
```
AI生成训练计划 → 前端提取结构化数据
    ↓
调用导入API → 保存到training_plans表
    ↓
包含AI字段 → exercises, target_muscles, safety_notes
```

---

## 🔗 相关文档

- **API文档**: `../../05-API文档/02-话题管理API.md`
- **数据库设计**: `../08-数据库层/01-数据库结构总览.md`
- **API分离方案**: `../../API分离实施方案.md`

---

**维护者**: 薛小川
**最后更新**: 2026-01-02
