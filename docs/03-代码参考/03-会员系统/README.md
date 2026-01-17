# 03-会员系统

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17

本目录包含会员等级管理、会员权益控制、订阅管理的代码实现细节。

---

## 📚 文档列表

### [01-会员系统总览](./01-会员系统总览.md)
- Membership模型设计
- 会员等级定义
- 权益控制逻辑
- MembershipController实现

### [04-定价策略分析](./04-定价策略分析.md)
- 会员套餐定价
- 优惠策略设计
- 支付集成方案

---

## 🔧 核心组件

### Membership模型
- **模型文件**: `app/Models/Membership.php`
- **数据库表**: `memberships`
- **会员等级**: Free, Basic, Premium, VIP

### 权益控制
- **AI对话次数限制**
- **训练计划生成限制**
- **高级功能访问权限**
- **数据存储容量**

---

## 📊 数据结构

### memberships表
```sql
CREATE TABLE memberships (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    tier VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### membership_tiers表
```sql
CREATE TABLE membership_tiers (
    id BIGINT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10,2),
    duration_days INT,
    features JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 🔄 工作流程

### 会员订阅流程
```
选择套餐 → 创建订单 → 支付 → 激活会员 → 更新权益
```

### 权益检查流程
```
用户请求 → 检查会员状态 → 验证权益 → 允许/拒绝访问
```

---

## 🔗 相关文档

- [API文档](../../05-API文档/02-会员系统API.md)
- [用户系统](../02-用户系统/README.md)
- [支付集成](../../04-部署指南/会员系统完整实施指南.md)

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
