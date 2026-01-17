# 02-用户系统

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含用户档案管理、用户数据验证的代码实现细节。

---

## 📚 文档列表

### [01-用户系统总览](./01-用户系统总览.md)
- User模型设计
- 用户档案字段
- 用户数据验证规则
- UserController实现

---

## 🔧 核心组件

### User模型
- **模型文件**: `app/Models/User.php`
- **数据库表**: `users`
- **关联关系**: 
  - hasOne: UserProfile
  - hasMany: TrainingPlans, TrainingSessions
  - hasOne: Membership

### 用户档案
- **字段**: 性别、年龄、身高、体重、健身目标、经验等级
- **验证**: 数据类型、范围验证
- **更新**: 实时同步到DAML-RAG用户档案MCP

---

## 📊 数据结构

### users表
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255),
    email_verified_at TIMESTAMP,
    phone_verified_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### user_profiles表
```sql
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    gender ENUM('male', 'female', 'other'),
    age INT,
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    fitness_goal VARCHAR(255),
    experience_level VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 🔄 工作流程

### 用户注册流程
```
验证码验证 → 创建用户 → 生成JWT Token → 返回用户信息
```

### 用户档案更新流程
```
接收更新请求 → 验证数据 → 更新数据库 → 同步到DAML-RAG → 返回结果
```

---

## 🔗 相关文档

- [API文档](../../05-API文档/01-用户系统API.md)
- [认证系统](../01-认证系统/README.md)
- [数据库设计](../08-数据库层/01-数据库结构总览.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
