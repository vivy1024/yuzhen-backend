# MySQL数据库完整结构文档

**文档状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-17
**维护者**: 薛小川

---

## 📋 概述

### 数据库信息
- **数据库版本**: MySQL 8.0
- **字符集**: utf8mb4_unicode_ci
- **表数量**: 35个核心表
- **数据规模**: 
  - Exercise动作: 1,790条（34字段完整）
  - Food食物: 1,880条
  - Muscle肌肉: 40个
- **最后更新**: 2026-01-17

### 架构特点
- **混合存储**: MySQL（结构化数据） + Neo4j（图关系） + Qdrant（向量检索） + Redis（缓存）
- **JSON字段**: 广泛使用JSON存储复杂数据结构（用户档案、训练参数等）
- **软删除**: 部分表支持软删除（chat_topics等）
- **外键约束**: 严格的外键关系保证数据完整性

---

## 🗂️ 核心表结构（按模块分类）

### 2.1 用户系统表

#### 2.1.1 users - 用户基本信息

**表说明**: 用户核心信息表，包含认证、权限、会员等级等

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| name | VARCHAR(255) | 用户名 | - |
| email | VARCHAR(255) | 邮箱 | UNIQUE |
| phone | VARCHAR(255) | 手机号 | - |
| avatar | VARCHAR(255) | 头像URL | - |
| role | VARCHAR(255) | 角色（user/admin） | - |
| status | VARCHAR(1) | 状态：1正常/0禁用 | - |
| del_flag | VARCHAR(1) | 删除标志：0未删/1已删 | - |
| is_active | BOOLEAN | 是否激活 | - |
| last_login_at | TIMESTAMP | 最后登录时间 | - |
| membership_tier | VARCHAR(255) | 会员等级 | - |
| preferences | JSON | 用户偏好设置 | - |
| favorites | JSON | 收藏的动作ID列表 | - |
| exercise_reviews | JSON | 动作评价 | - |
| email_verified_at | TIMESTAMP | 邮箱验证时间 | - |
| onboarding_completed | BOOLEAN | 是否完成引导 | - |
| profile_completed_at | TIMESTAMP | 档案完成时间 | - |
| password | VARCHAR(255) | 密码哈希 | - |
| remember_token | VARCHAR(100) | 记住我令牌 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 一对一: user_profiles（用户档案）
- 一对多: user_memberships（会员关系）
- 一对多: training_plans（训练计划）
- 一对多: chat_topics（对话话题）
- 一对多: orders（订单）


#### 2.1.2 user_profiles - 用户档案

**表说明**: 用户健身档案，JSON结构存储，与前端 user-profile.ts 类型完全对应

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | UNIQUE, FOREIGN |
| basic_info | JSON | 基础信息：age, gender, height, weight等 | - |
| fitness_goals | JSON | 健身目标：primary_goals, target_weight等 | - |
| training_preferences | JSON | 训练偏好：training_split, available_equipment等 | - |
| health_status | JSON | 健康状况：injuries, chronic_diseases等 | - |
| nutrition_profile | JSON | 营养档案：daily_calories, protein_intake等 | - |
| strength_data | JSON | 力量数据：bench_press, squat, deadlift等 | - |
| ffmi_assessment | JSON | FFMI评估：ffmi, bmi, assessment等 | - |
| version | INT | 数据版本号 | - |
| last_sync_at | TIMESTAMP | 最后同步时间 | - |
| sync_status | VARCHAR(50) | 同步状态 | INDEX |
| is_mcp_temp | BOOLEAN | 是否为MCP临时数据 | INDEX |
| mcp_session_id | VARCHAR(100) | MCP会话ID | - |
| sync_source | VARCHAR(50) | 同步来源 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**JSON字段结构示例**:

```json
{
  "basic_info": {
    "age": 28,
    "gender": "male",
    "height": 175,
    "weight": 70,
    "body_fat_percentage": 15
  },
  "fitness_goals": {
    "primary_goals": ["gain_muscle", "improve_strength"],
    "target_weight": 75,
    "target_body_fat": 12
  },
  "training_preferences": {
    "training_split": "upper_lower",
    "available_equipment": ["barbell", "dumbbell", "cable"],
    "preferred_rest_pattern": "fixed_60s"
  }
}
```

**关联关系**:
- 多对一: users（用户）

#### 2.1.3 social_accounts - 社交账号绑定

**表说明**: 第三方登录账号绑定（微信、QQ、微博、Apple）

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN, INDEX |
| provider | VARCHAR(255) | 登录提供商：wechat/qq/weibo/apple | - |
| provider_id | VARCHAR(255) | 第三方用户ID | - |
| provider_token | VARCHAR(255) | 访问令牌 | - |
| provider_refresh_token | VARCHAR(255) | 刷新令牌 | - |
| provider_data | JSON | 第三方用户数据 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**唯一约束**: (provider, provider_id)

**关联关系**:
- 多对一: users（用户）

---

### 2.2 会员系统表

#### 2.2.1 memberships - 会员套餐定义

**表说明**: 会员等级和套餐配置

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| name | VARCHAR(50) | 等级名称 | - |
| slug | VARCHAR(50) | 唯一标识 | UNIQUE |
| tier | VARCHAR(50) | 等级标识：free/warmheart/energy | - |
| price | DECIMAL(10,2) | 价格（元） | - |
| duration_days | INT | 有效天数 | - |
| max_training_plans | INT | 最大训练计划数量 | - |
| unlock_all_exercises | BOOLEAN | 解锁所有动作 | - |
| ai_recommendation | BOOLEAN | AI推荐功能 | - |
| data_analysis | BOOLEAN | 数据分析功能 | - |
| coach_service | BOOLEAN | 教练服务 | - |
| description | TEXT | 等级描述 | - |
| features | JSON | 功能列表 | - |
| limits | JSON | 限制说明 | - |
| sort_order | INT | 排序 | - |
| is_active | BOOLEAN | 是否启用 | INDEX |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 一对多: user_memberships（用户会员关系）


#### 2.2.2 user_memberships - 用户会员关系

**表说明**: 用户购买的会员记录

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN |
| membership_id | BIGINT UNSIGNED | 会员套餐ID | FOREIGN |
| order_id | VARCHAR(255) | 订单ID | - |
| started_at | TIMESTAMP | 生效时间 | - |
| expires_at | TIMESTAMP | 过期时间 | - |
| is_active | BOOLEAN | 是否激活 | INDEX |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**复合索引**: (user_id, is_active)

**关联关系**:
- 多对一: users（用户）
- 多对一: memberships（会员套餐）

#### 2.2.3 orders - 订单表

**表说明**: 会员订单，支持收款码+截图上传的打赏支付方式

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| order_no | VARCHAR(32) | 订单号 | UNIQUE |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN |
| membership_id | BIGINT UNSIGNED | 会员套餐ID | FOREIGN |
| amount | DECIMAL(10,2) | 订单金额 | - |
| actual_amount | DECIMAL(10,2) | 实付金额 | - |
| discount_amount | DECIMAL(10,2) | 优惠金额 | - |
| status | VARCHAR(20) | 订单状态：pending/paid/cancelled/refunded/reviewing | INDEX |
| pay_method | VARCHAR(20) | 支付方式：wechat/alipay | - |
| pay_trade_no | VARCHAR(64) | 支付交易号 | - |
| paid_at | TIMESTAMP | 支付时间 | - |
| payment_proof_url | VARCHAR(255) | 支付截图URL | - |
| proof_uploaded_at | TIMESTAMP | 截图上传时间 | - |
| reviewer_id | BIGINT UNSIGNED | 审核人ID | - |
| reviewed_at | TIMESTAMP | 审核时间 | - |
| review_note | TEXT | 审核备注 | - |
| remark | TEXT | 订单备注 | - |
| created_at | TIMESTAMP | 创建时间 | INDEX |
| updated_at | TIMESTAMP | 更新时间 | - |

**复合索引**: 
- (user_id, status)
- (status, created_at)

**关联关系**:
- 多对一: users（用户）
- 多对一: memberships（会员套餐）

#### 2.2.4 user_usage_stats - 用户用量统计

**表说明**: 追踪用户的AI对话次数（DAG模式和Agent模式分开统计）

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN |
| date | DATE | 统计日期 | INDEX |
| dag_queries | INT UNSIGNED | DAG模式查询次数 | - |
| dag_limit | INT UNSIGNED | DAG模式每日限制（-1表示无限） | - |
| agent_queries | INT UNSIGNED | Agent模式查询次数 | - |
| agent_limit | INT UNSIGNED | Agent模式每日限制（-1表示无限） | - |
| bonus_dag_queries | INT UNSIGNED | 额外DAG次数（打赏奖励） | - |
| bonus_agent_queries | INT UNSIGNED | 额外Agent次数（打赏奖励） | - |
| metadata | JSON | 元数据（工具调用统计等） | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**唯一约束**: (user_id, date)

**关联关系**:
- 多对一: users（用户）

#### 2.2.5 user_credits - 用户积分

**表说明**: 用户积分账户

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | UNIQUE, FOREIGN |
| total_dag_credits | INT UNSIGNED | 累计DAG额外次数 | - |
| total_agent_credits | INT UNSIGNED | 累计Agent额外次数 | - |
| used_dag_credits | INT UNSIGNED | 已使用DAG额外次数 | - |
| used_agent_credits | INT UNSIGNED | 已使用Agent额外次数 | - |
| donation_history | JSON | 打赏历史记录 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 一对一: users（用户）

#### 2.2.6 credit_logs - 积分日志

**表说明**: 积分变动记录

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN, INDEX |
| type | VARCHAR(50) | 类型：donation/usage/admin_grant | INDEX |
| amount | INT | 变动数量（正数增加，负数减少） | - |
| balance_after | INT | 变动后余额 | - |
| description | TEXT | 描述 | - |
| metadata | JSON | 元数据 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: users（用户）

---

### 2.3 训练系统表

#### 2.3.1 exercises - 动作库（核心表）

**表说明**: 健身动作库，1,790条数据，34个字段完整，中英文对照

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| name_en | VARCHAR(255) | 名称英文 | - |
| name_zh | VARCHAR(255) | 名称中文 | - |
| slug | VARCHAR(255) | URL友好名称 | UNIQUE |
| description_en | TEXT | 描述英文 | - |
| description_zh | TEXT | 描述中文 | - |
| primary_muscle_en | VARCHAR(100) | 主要肌肉英文 | INDEX |
| primary_muscle_zh | VARCHAR(100) | 主要肌肉中文 | INDEX |
| all_muscles_zh | JSON | 所有肌肉中文 | - |
| equipment_en | VARCHAR(100) | 器械英文 | INDEX |
| equipment_zh | VARCHAR(100) | 器械中文 | INDEX |
| difficulty_en | VARCHAR(50) | 难度英文 | INDEX |
| difficulty_zh | VARCHAR(50) | 难度中文 | - |
| force_en | VARCHAR(50) | 力量类型英文 | - |
| force_zh | VARCHAR(50) | 力量类型中文 | - |
| mechanic_en | VARCHAR(50) | 动作类型英文 | - |
| mechanic_zh | VARCHAR(50) | 动作类型中文 | - |
| grips_en | JSON | 握法英文 | - |
| grips_zh | JSON | 握法中文 | - |
| correct_steps_en | JSON | 正确步骤英文 | - |
| correct_steps_zh | JSON | 正确步骤中文 | - |
| smart_tags | JSON | 智能标签 | - |
| rep_range | VARCHAR(50) | 推荐次数范围 | - |
| set_range | VARCHAR(50) | 推荐组数范围 | - |
| rest_period | VARCHAR(50) | 休息时间 | - |
| intensity_percentage | VARCHAR(50) | 训练强度百分比 | - |
| safety_level | VARCHAR(50) | 安全等级 | INDEX |
| safety_pre_check | JSON | 训练前检查项 | - |
| equipment_risks | JSON | 器械风险 | - |
| kinetic_chain_type | VARCHAR(50) | 动力链类型 | - |
| technique_checkpoints | JSON | 技术检查点 | - |
| rom_requirements | JSON | 活动范围要求 | - |
| key_nutrients | JSON | 关键营养素 | - |
| recommended_foods | JSON | 推荐食物 | - |
| nutrition_timing | VARCHAR(255) | 营养补充时机 | - |
| progression_options | JSON | 进阶选项 | - |
| regression_options | JSON | 退阶选项 | - |
| categories | JSON | 分类 | - |
| data_source | VARCHAR(50) | 数据来源 | - |
| source_reference | VARCHAR(255) | 来源引用 | - |
| license_type | VARCHAR(50) | 授权类型 | - |
| original_source | VARCHAR(255) | 原始来源 | - |
| last_verified_at | TIMESTAMP | 最后验证时间 | - |
| verified_by | VARCHAR(100) | 验证人 | - |
| rating | INT | 评分 | - |
| view_count | INT | 浏览次数 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**重要说明**:
- v2.80.0版本统一了肌肉字段命名：`primary_muscle_zh`、`all_muscles_zh`
- 与Neo4j图数据库同步，支持复杂的肌肉关系查询
- 与Qdrant向量库同步，支持语义搜索

**关联关系**:
- 一对多: training_plan_exercises（训练计划动作）
- 一对多: user_favorite_exercises（用户收藏）


#### 2.3.2 training_plans - 训练计划

**表说明**: 用户的训练计划，支持周期化训练

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN |
| name | VARCHAR(255) | 计划名称 | - |
| name_zh | VARCHAR(255) | 计划名称（中文） | - |
| description | TEXT | 计划描述 | - |
| goal | ENUM | 训练目标：lose_weight/gain_muscle/maintain/improve_fitness | INDEX |
| difficulty | ENUM | 难度：novice/beginner/intermediate/advanced | - |
| duration_weeks | INT | 总周数 | - |
| workouts_per_week | INT | 每周训练次数 | - |
| is_active | BOOLEAN | 是否激活 | INDEX |
| started_at | DATETIME | 开始时间 | - |
| completed_at | DATETIME | 完成时间 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**复合索引**: (user_id, is_active)

**关联关系**:
- 多对一: users（用户）
- 一对多: training_plan_exercises（计划动作）
- 一对多: training_logs（训练日志）

#### 2.3.3 training_logs - 训练日志（闭环学习）

**表说明**: 记录每次训练的详细数据，用于闭环学习系统

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN |
| session_date | DATE | 训练日期 | INDEX |
| planned_exercises | JSON | 计划动作列表 | - |
| actual_exercises | JSON | 实际完成情况 | - |
| completion_rate | DECIMAL(3,2) | 完成率（0-1） | - |
| avg_rpe | DECIMAL(3,1) | 平均RPE（1-10） | - |
| week_number | INT | 周期内第几周 | - |
| mesocycle_id | VARCHAR(50) | 中周期ID | INDEX |
| notes | TEXT | 备注 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**复合索引**: (user_id, session_date)

**JSON字段结构示例**:

```json
{
  "planned_exercises": [
    {
      "exercise_id": "ex_001",
      "name": "深蹲",
      "sets": 4,
      "reps": 8,
      "weight": 100
    }
  ],
  "actual_exercises": [
    {
      "exercise_id": "ex_001",
      "name": "深蹲",
      "completed_sets": 4,
      "completed_reps": [8, 8, 7, 6],
      "actual_weight": 100,
      "rpe": 8
    }
  ]
}
```

**关联关系**:
- 多对一: users（用户）

#### 2.3.4 personal_bests - 个人最佳记录

**表说明**: 用户的个人最佳成绩记录

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN, INDEX |
| exercise_id | BIGINT UNSIGNED | 动作ID | FOREIGN, INDEX |
| record_type | VARCHAR(50) | 记录类型：max_weight/max_reps/max_volume | INDEX |
| value | DECIMAL(10,2) | 记录值 | - |
| unit | VARCHAR(20) | 单位：kg/reps/kg*reps | - |
| achieved_at | DATE | 达成日期 | - |
| notes | TEXT | 备注 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: users（用户）
- 多对一: exercises（动作）

#### 2.3.5 progress_records - 进度记录

**表说明**: 用户的身体数据和进度追踪

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN, INDEX |
| record_date | DATE | 记录日期 | INDEX |
| weight | DECIMAL(5,2) | 体重（kg） | - |
| body_fat_percentage | DECIMAL(4,2) | 体脂率（%） | - |
| muscle_mass | DECIMAL(5,2) | 肌肉量（kg） | - |
| measurements | JSON | 身体围度测量 | - |
| photos | JSON | 进度照片URL | - |
| notes | TEXT | 备注 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: users（用户）

#### 2.3.6 fitness_goals - 健身目标

**表说明**: 用户设定的健身目标

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN, INDEX |
| goal_type | VARCHAR(50) | 目标类型：weight/strength/endurance/body_composition | INDEX |
| target_value | DECIMAL(10,2) | 目标值 | - |
| current_value | DECIMAL(10,2) | 当前值 | - |
| unit | VARCHAR(20) | 单位 | - |
| deadline | DATE | 截止日期 | - |
| status | VARCHAR(20) | 状态：active/achieved/abandoned | INDEX |
| achieved_at | DATE | 达成日期 | - |
| notes | TEXT | 备注 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: users（用户）

---

### 2.4 AI聊天系统表

#### 2.4.1 chat_topics - 对话话题

**表说明**: 用户的对话话题分类，支持软删除

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN |
| name | VARCHAR(100) | 话题名称 | - |
| description | TEXT | 话题描述 | - |
| message_count | INT | 消息数量 | - |
| last_message | TEXT | 最后一条消息 | - |
| last_message_at | TIMESTAMP | 最后消息时间 | - |
| created_at | TIMESTAMP | 创建时间 | INDEX |
| updated_at | TIMESTAMP | 更新时间 | - |
| deleted_at | TIMESTAMP | 软删除时间 | - |

**复合索引**: (user_id, created_at)

**关联关系**:
- 多对一: users（用户）
- 一对多: chat_sessions（对话会话）

#### 2.4.2 chat_sessions - 对话会话

**表说明**: AI对话历史记录，混合存储架构（MySQL + Qdrant）

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| session_id | CHAR(36) | 会话UUID，支持多轮对话 | INDEX |
| user_id | BIGINT UNSIGNED | 用户ID（匿名用户为NULL） | FOREIGN, INDEX |
| user_query | TEXT | 用户问题 | - |
| llm_response | TEXT | AI回答 | - |
| model_used | VARCHAR(50) | 使用的模型 | INDEX |
| tools_used | JSON | 调用的工具列表 | - |
| metadata | JSON | 元数据：few_shot_count, orchestrator_used等 | - |
| user_rating | TINYINT | 用户评分（1-5星） | - |
| user_feedback | VARCHAR(500) | 用户反馈 | - |
| qdrant_point_id | CHAR(36) | Qdrant向量点ID | INDEX |
| created_at | TIMESTAMP | 创建时间 | INDEX |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: users（用户）
- 多对一: chat_topics（对话话题）

**重要说明**:
- 支持匿名用户对话（user_id可为NULL）
- 与Qdrant向量库同步，支持Few-Shot学习
- metadata字段记录DAG编排、工具调用等详细信息

#### 2.4.3 chat_messages - 消息记录

**表说明**: 详细的消息记录表

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| session_id | CHAR(36) | 会话ID | INDEX |
| role | VARCHAR(20) | 角色：user/assistant/system | - |
| content | TEXT | 消息内容 | - |
| metadata | JSON | 元数据 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: chat_sessions（对话会话）

---

### 2.5 食物库表

#### 2.5.1 foods - 食物数据

**表说明**: 中国食物成分表，1,880条数据

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| food_code | VARCHAR(20) | 食物编码 | UNIQUE |
| name | VARCHAR(100) | 食物名称 | INDEX |
| category | VARCHAR(50) | 大类 | INDEX |
| subcategory | VARCHAR(50) | 小类 | INDEX |
| edible | DECIMAL(5,1) | 可食部(%) | - |
| water | DECIMAL(5,1) | 水分(g) | - |
| energy_kcal | DECIMAL(6,1) | 能量(kcal) | INDEX |
| energy_kj | DECIMAL(7,1) | 能量(kJ) | - |
| protein | DECIMAL(5,2) | 蛋白质(g) | INDEX |
| fat | DECIMAL(5,2) | 脂肪(g) | - |
| carbohydrate | DECIMAL(5,2) | 碳水化合物(g) | - |
| dietary_fiber | DECIMAL(5,2) | 膳食纤维(g) | - |
| cholesterol | DECIMAL(6,1) | 胆固醇(mg) | - |
| ash | DECIMAL(5,2) | 灰分(g) | - |
| vitamin_a | DECIMAL(7,2) | 维生素A(μg) | - |
| carotene | DECIMAL(7,2) | 胡萝卜素(μg) | - |
| retinol | DECIMAL(7,2) | 视黄醇(μg) | - |
| thiamin | DECIMAL(6,3) | 硫胺素/维生素B1(mg) | - |
| riboflavin | DECIMAL(5,3) | 核黄素/维生素B2(mg) | - |
| niacin | DECIMAL(6,3) | 烟酸(mg) | - |
| vitamin_c | DECIMAL(6,2) | 维生素C(mg) | - |
| vitamin_e_total | DECIMAL(6,3) | 维生素E总量(mg) | - |
| calcium | DECIMAL(7,2) | 钙(mg) | - |
| phosphorus | DECIMAL(7,2) | 磷(mg) | - |
| potassium | DECIMAL(7,2) | 钾(mg) | - |
| sodium | DECIMAL(7,2) | 钠(mg) | - |
| magnesium | DECIMAL(7,2) | 镁(mg) | - |
| iron | DECIMAL(6,3) | 铁(mg) | - |
| zinc | DECIMAL(6,3) | 锌(mg) | - |
| selenium | DECIMAL(7,3) | 硒(μg) | - |
| copper | DECIMAL(6,3) | 铜(mg) | - |
| manganese | DECIMAL(6,3) | 锰(mg) | - |
| remark | VARCHAR(255) | 备注（产地等） | - |
| gi_value | INT | GI值 | - |
| price_level | VARCHAR(10) | 价格等级 | - |
| view_count | INT | 浏览次数 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 与Neo4j图数据库同步，支持营养素关系查询

---

### 2.6 反馈系统表

#### 2.6.1 feedbacks - 用户反馈

**表说明**: 用户提交的反馈和建议

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| user_id | BIGINT UNSIGNED | 用户ID | FOREIGN, INDEX |
| type | VARCHAR(50) | 类型：bug/feature/suggestion/complaint | INDEX |
| title | VARCHAR(255) | 标题 | - |
| content | TEXT | 内容 | - |
| status | VARCHAR(20) | 状态：pending/processing/resolved/closed | INDEX |
| priority | VARCHAR(20) | 优先级：low/medium/high/urgent | - |
| attachments | JSON | 附件URL | - |
| admin_reply | TEXT | 管理员回复 | - |
| replied_at | TIMESTAMP | 回复时间 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**关联关系**:
- 多对一: users（用户）

#### 2.6.2 faqs - 常见问题

**表说明**: 常见问题和答案

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| category | VARCHAR(50) | 分类 | INDEX |
| question | TEXT | 问题 | - |
| answer | TEXT | 答案 | - |
| sort_order | INT | 排序 | - |
| view_count | INT | 浏览次数 | - |
| is_active | BOOLEAN | 是否启用 | INDEX |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

---


### 2.7 系统表

#### 2.7.1 password_reset_tokens - 密码重置令牌

**表说明**: 密码重置功能的令牌存储

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| email | VARCHAR(255) | 邮箱 | PRIMARY |
| token | VARCHAR(255) | 重置令牌 | - |
| created_at | TIMESTAMP | 创建时间 | - |

#### 2.7.2 personal_access_tokens - API访问令牌

**表说明**: Laravel Sanctum的API令牌

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| tokenable_type | VARCHAR(255) | 令牌所属模型类型 | - |
| tokenable_id | BIGINT UNSIGNED | 令牌所属模型ID | INDEX |
| name | VARCHAR(255) | 令牌名称 | - |
| token | VARCHAR(64) | 令牌哈希 | UNIQUE |
| abilities | TEXT | 权限列表 | - |
| last_used_at | TIMESTAMP | 最后使用时间 | - |
| expires_at | TIMESTAMP | 过期时间 | - |
| created_at | TIMESTAMP | 创建时间 | - |
| updated_at | TIMESTAMP | 更新时间 | - |

**复合索引**: (tokenable_type, tokenable_id)

#### 2.7.3 failed_jobs - 失败任务

**表说明**: Laravel队列失败任务记录

**字段列表**:

| 字段名 | 类型 | 说明 | 索引 |
|--------|------|------|------|
| id | BIGINT UNSIGNED | 主键 | PRIMARY |
| uuid | VARCHAR(255) | 任务UUID | UNIQUE |
| connection | TEXT | 连接名称 | - |
| queue | TEXT | 队列名称 | - |
| payload | LONGTEXT | 任务载荷 | - |
| exception | LONGTEXT | 异常信息 | - |
| failed_at | TIMESTAMP | 失败时间 | - |

---

## 3. 字段统一标准

### 3.1 肌肉字段统一（v2.80.0重要变更）

**变更背景**: 
- 原始数据使用 `muscles_primary_zh`、`muscles_secondary_zh` 等复数形式
- 为了与前端类型定义一致，统一改为单数形式

**统一后的字段命名**:

| 旧字段名 | 新字段名 | 说明 |
|---------|---------|------|
| muscles_primary_zh | primary_muscle_zh | 主要肌肉中文 |
| muscles_primary_en | primary_muscle_en | 主要肌肉英文 |
| muscles_secondary_zh | all_muscles_zh | 所有肌肉中文（包含主要+次要） |

**迁移SQL**:
```sql
-- 2026_01_15_000001_migrate_muscle_fields_to_standard.php
ALTER TABLE exercises 
  CHANGE COLUMN muscles_primary_zh primary_muscle_zh VARCHAR(100),
  CHANGE COLUMN muscles_primary_en primary_muscle_en VARCHAR(100),
  CHANGE COLUMN muscles_secondary_zh all_muscles_zh JSON;
```

### 3.2 命名规范

#### 中英文字段后缀
- **中文字段**: `_zh` 后缀（如 `name_zh`、`description_zh`）
- **英文字段**: `_en` 后缀（如 `name_en`、`description_en`）
- **通用字段**: 无后缀（如 `id`、`created_at`）

#### 布尔字段前缀
- **is_**: 状态判断（如 `is_active`、`is_mcp_temp`）
- **has_**: 拥有判断（如 `has_equipment`）
- **can_**: 能力判断（如 `can_access`）

#### 时间字段后缀
- **_at**: 时间点（如 `created_at`、`started_at`、`expires_at`）
- **_date**: 日期（如 `session_date`、`record_date`）

### 3.3 数据类型规范

#### 字符串长度
- **短标识**: VARCHAR(50) - 如 status、type、tier
- **名称**: VARCHAR(100-255) - 如 name、title
- **描述**: TEXT - 如 description、content
- **长文本**: LONGTEXT - 如 payload、exception

#### 数值类型
- **主键**: BIGINT UNSIGNED
- **外键**: BIGINT UNSIGNED
- **计数器**: INT UNSIGNED
- **金额**: DECIMAL(10,2)
- **百分比**: DECIMAL(5,2) 或 DECIMAL(3,2)
- **评分**: TINYINT 或 DECIMAL(3,1)

#### JSON字段
- 用于存储复杂数据结构（如用户档案、训练参数）
- 用于存储数组（如工具列表、标签列表）
- 用于存储元数据（如配置、统计信息）

### 3.4 时间戳规范

**标准时间戳字段**:
```php
$table->timestamps(); // 自动创建 created_at 和 updated_at
```

**特殊时间字段**:
- `started_at`: 开始时间
- `expires_at`: 过期时间
- `completed_at`: 完成时间
- `verified_at`: 验证时间
- `deleted_at`: 软删除时间

---

## 4. 索引策略

### 4.1 主键索引
- 所有表都有自增主键 `id`
- 类型: BIGINT UNSIGNED AUTO_INCREMENT

### 4.2 外键索引
- 所有外键字段自动创建索引
- 命名规范: `表名_字段名_foreign`
- 级联删除: `onDelete('cascade')` 或 `onDelete('set null')`

### 4.3 唯一索引
- email（用户邮箱）
- slug（动作URL友好名称）
- food_code（食物编码）
- order_no（订单号）
- (provider, provider_id)（社交账号）
- (user_id, date)（用量统计）

### 4.4 查询优化索引

**单列索引**:
- 状态字段: status, is_active
- 分类字段: category, type, tier
- 时间字段: created_at, session_date
- 搜索字段: name, primary_muscle_zh

**复合索引**:
- (user_id, is_active) - 查询用户的激活记录
- (user_id, status) - 查询用户的订单状态
- (user_id, created_at) - 查询用户的时间序列数据
- (user_id, session_date) - 查询用户的训练日志

### 4.5 全文索引
- 暂未使用，考虑使用Elasticsearch或Qdrant进行全文搜索

---

## 5. 数据迁移历史

### 5.1 关键迁移记录

#### 2026-01-15: 肌肉字段统一（v2.80.0）
- **迁移文件**: `2026_01_15_000001_migrate_muscle_fields_to_standard.php`
- **变更内容**: 
  - `muscles_primary_zh` → `primary_muscle_zh`
  - `muscles_primary_en` → `primary_muscle_en`
  - `muscles_secondary_zh` → `all_muscles_zh`
- **影响范围**: exercises表，1,790条数据
- **向后兼容**: 前端已同步更新类型定义

#### 2026-01-11: 会员系统完善
- **迁移文件**: `2026_01_11_100001_create_usage_stats_table.php` 等8个文件
- **变更内容**:
  - 创建用量统计表（user_usage_stats）
  - 创建积分系统表（user_credits, credit_logs）
  - 完善会员表字段（memberships, user_memberships）
  - 创建订单表（orders）
- **影响范围**: 新增8个表

#### 2026-01-04: 动作库重建（v2.0.0）
- **迁移文件**: `2026_01_04_120000_recreate_exercises_table_v2.php`
- **变更内容**: 
  - 完全重建exercises表
  - 字段命名与源数据完全一致
  - 新增34个完整字段
- **影响范围**: exercises表结构重建
- **数据迁移**: 使用 `MigrateExercisesCommand` 从JSON导入

#### 2026-01-02: AI聊天系统
- **迁移文件**: `2026_01_02_050456_create_chat_topics_table.php` 等
- **变更内容**:
  - 创建对话话题表（chat_topics）
  - 为chat_sessions添加topic_id
  - 创建消息记录表（chat_messages）
- **影响范围**: 新增3个表/字段

#### 2025-12-26: 训练系统增强
- **迁移文件**: `2025_12_26_000002_create_training_logs_table.php` 等
- **变更内容**:
  - 创建训练日志表（training_logs）
  - 创建个人最佳记录表（personal_bests）
  - 为user_profiles添加训练反馈字段
- **影响范围**: 新增2个表，修改1个表

### 5.2 字段变更历史

#### user_profiles表演进
1. **2025-11-04**: 初始创建，7个JSON字段
2. **2025-12-19**: 添加 `preferred_rest_pattern`（休息模式）
3. **2025-12-19**: 添加 `strength_progress`（力量进度）
4. **2025-12-20**: 添加 `training_feedback`（训练反馈）

#### exercises表演进
1. **2025-11-04**: 初始创建，基础字段
2. **2025-12-26**: 添加安全字段（safety_level等）
3. **2025-12-31**: 添加数据来源字段（data_source等）
4. **2026-01-04**: 完全重建，34个完整字段
5. **2026-01-05**: 添加bodymap字段
6. **2026-01-15**: 肌肉字段统一

#### chat_sessions表演进
1. **2025-11-04**: 初始创建
2. **2025-12-31**: 添加三轨评分（three_track_rating）
3. **2025-12-31**: 添加训练效果（training_effect）
4. **2026-01-02**: 添加话题关联（topic_id）

### 5.3 数据修复记录

#### 2026-01-15: 肌肉字段数据迁移
```sql
-- 迁移主要肌肉数据
UPDATE exercises 
SET primary_muscle_zh = JSON_UNQUOTE(JSON_EXTRACT(muscles_primary_zh, '$[0]'))
WHERE muscles_primary_zh IS NOT NULL;

-- 迁移所有肌肉数据
UPDATE exercises 
SET all_muscles_zh = JSON_MERGE_PRESERVE(
  COALESCE(muscles_primary_zh, '[]'),
  COALESCE(muscles_secondary_zh, '[]')
);
```

---

## 6. 相关文档链接

### 数据库相关
- **Neo4j数据库结构**: `daml-rag-server/docs/02-核心架构/02-数据层/02-Neo4j数据库结构.md`
- **Qdrant向量库结构**: `daml-rag-server/docs/02-核心架构/02-数据层/03-Qdrant向量库结构.md`
- **表Model前端对照分析**: `yuzhen-backend/docs/02-核心架构/02-数据层/01-表Model前端对照分析.md`

### API文档
- **用户系统API**: `yuzhen-backend/docs/05-API文档/01-用户系统API.md`
- **会员系统API**: `yuzhen-backend/docs/05-API文档/02-会员系统API.md`
- **训练系统API**: `yuzhen-backend/docs/05-API文档/03-训练系统API.md`
- **AI聊天API**: `yuzhen-backend/docs/05-API文档/04-AI聊天API.md`

### 代码参考
- **用户系统实现**: `yuzhen-backend/docs/03-代码参考/02-用户系统/`
- **会员系统实现**: `yuzhen-backend/docs/03-代码参考/03-会员系统/`
- **训练系统实现**: `yuzhen-backend/docs/03-代码参考/04-训练系统/`
- **数据库层实现**: `yuzhen-backend/docs/03-代码参考/08-数据库层/`

### 部署运维
- **Zeabur部署指南**: `yuzhen-backend/docs/06-部署运维/Zeabur部署指南.md`
- **数据库备份恢复**: `yuzhen-backend/docs/06-部署运维/数据库备份恢复.md`

---

## 7. 附录

### 7.1 表数量统计

| 模块 | 表数量 | 说明 |
|------|--------|------|
| 用户系统 | 3 | users, user_profiles, social_accounts |
| 会员系统 | 6 | memberships, user_memberships, orders, user_usage_stats, user_credits, credit_logs |
| 训练系统 | 6 | exercises, training_plans, training_logs, personal_bests, progress_records, fitness_goals |
| AI聊天系统 | 3 | chat_topics, chat_sessions, chat_messages |
| 食物库 | 1 | foods |
| 反馈系统 | 2 | feedbacks, faqs |
| 系统表 | 3 | password_reset_tokens, personal_access_tokens, failed_jobs |
| **总计** | **24** | 核心业务表 |

### 7.2 数据规模统计

| 数据类型 | 数量 | 说明 |
|---------|------|------|
| Exercise动作 | 1,790 | 34字段完整，中英文对照 |
| Food食物 | 1,880 | 中国食物成分表 |
| Muscle肌肉 | 40 | 与Exercise.primary_muscle_zh一致 |
| 用户数 | 动态 | 生产环境实时数据 |
| 对话记录 | 动态 | 与Qdrant同步 |

### 7.3 字段类型统计

| 字段类型 | 使用频率 | 典型用途 |
|---------|---------|---------|
| BIGINT UNSIGNED | 高 | 主键、外键 |
| VARCHAR | 高 | 名称、标识、状态 |
| TEXT | 中 | 描述、内容 |
| JSON | 高 | 复杂数据结构、数组 |
| TIMESTAMP | 高 | 时间记录 |
| DECIMAL | 中 | 金额、百分比、评分 |
| BOOLEAN | 中 | 状态标志 |
| INT | 中 | 计数器、排序 |
| ENUM | 低 | 固定选项（已逐步替换为VARCHAR） |

---

**文档维护**: 薛小川
**联系方式**: 1336495069@qq.com
**最后更新**: 2026-01-17
**版本**: v1.0.0

