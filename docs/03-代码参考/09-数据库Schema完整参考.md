# 数据库 Schema 完整参考

**生成日期**: 2026-02-24
**数据来源**: 68 个 Migration 文件 + Model 定义合并后的最终状态
**维护者**: 薛小川 / Claude Code

> ⚠️ 本文档是所有 migration 合并后的"当前最终状态"，不是 migration 历史。
> 修改表结构后请同步更新本文档。

---

## 📊 表总览（共 35 张表）

| 分类 | 表名 | 说明 |
|------|------|------|
| **用户体系** | users | 用户主表 |
| | user_profiles | 用户健身档案 |
| | social_accounts | 第三方登录 |
| | memberships | 会员等级定义 |
| | user_memberships | 用户-会员关联 |
| **训练体系** | exercises | 动作库（v2重建） |
| | exercise_v2_media | 动作媒体资源 |
| | training_plans | 训练计划 |
| | training_plan_exercises | 计划-动作关联 |
| | training_sessions | 训练会话 |
| | training_records | 训练记录（组/次/重量） |
| | training_progress | 训练进度汇总 |
| | training_logs | 训练日志 |
| | personal_bests | 个人最佳记录 |
| **AI对话** | chat_topics | 对话话题 |
| | chat_sessions | 对话记录（含三轨评分） |
| | chat_messages | 对话消息 |
| | expert_reviews | 专家评审 |
| | complaints | 投诉记录 |
| **积分/会员** | user_credits | 用户积分配额 |
| | credit_transactions | 积分交易流水 |
| | credit_shares | 积分分享 |
| | credit_logs | 积分日志（旧） |
| | usage_stats | 用量统计 |
| | user_usage_stats | 用户每日用量 |
| | user_bonus_credits | 打赏额外次数 |
| | orders | 订单 |
| | membership_orders | 会员订单 |
| | referrals | 推荐关系 |
| **其他** | foods | 食物营养库 |
| | progress_records | 体测记录 |
| | fitness_goals | 健身目标 |
| | feedbacks | 用户反馈 |
| | faqs | 常见问题 |
| | knowledge_categories | 知识分类 |
| | knowledge_articles | 知识文章 |
| | knowledge_references | 知识引用 |
| | knowledge_ingestion_status | 知识导入状态 |
| | push_subscriptions | 推送订阅 |
| | plan_templates | 计划模板 |
| | user_nutrition_plans | 营养计划 |
| | chinese_holidays | 中国节假日 |
| **Laravel系统** | password_reset_tokens | 密码重置 |
| | failed_jobs | 失败队列 |
| | personal_access_tokens | Sanctum令牌 |
| | permissions/roles/model_has_* | Spatie权限（4张表） |

---

## 一、用户体系（5张表）

### 1.1 users — 用户主表

**Model**: `App\Modules\User\Models\User`
**Migration**: create_users + add_training_system_fields + add_membership_fields

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| name | varchar(255) | NO | - | - | 用户名 |
| email | varchar(255) | NO | - | UNIQUE | 邮箱 |
| phone | varchar(255) | YES | NULL | - | 电话 |
| avatar | varchar(255) | YES | NULL | - | 头像URL |
| role | varchar(255) | NO | 'user' | - | 角色 |
| status | varchar(1) | NO | '1' | - | 1正常/0禁用 |
| del_flag | varchar(1) | NO | '0' | - | 0未删/1已删 |
| is_active | boolean | NO | true | - | 是否激活 |
| last_login_at | timestamp | YES | NULL | - | 最后登录 |
| membership_tier | varchar(255) | NO | 'newbie' | - | 会员等级 |
| preferences | json | YES | NULL | - | 偏好设置 |
| favorites | json | YES | NULL | - | 收藏动作ID |
| exercise_reviews | json | YES | NULL | - | 动作评价 |
| email_verified_at | timestamp | YES | NULL | - | 邮箱验证时间 |
| onboarding_completed | boolean | NO | false | - | 完成引导 |
| profile_completed_at | timestamp | YES | NULL | - | 档案完成时间 |
| password | varchar(255) | NO | - | - | 密码hash |
| remember_token | varchar(100) | YES | NULL | - | 记住我 |
| preferred_training_time | varchar(50) | YES | NULL | - | evening/lunch/morning/between_classes |
| body_type | enum(thin,normal,overweight,muscular) | YES | NULL | - | 体型分类 |
| user_type | enum(student,worker,other) | NO | 'other' | - | 用户类型 |
| campus_name | varchar(100) | YES | NULL | - | 学校名称 |
| personal_volume_multiplier | decimal(3,2) | NO | 1.00 | - | 容量系数(0.7-1.5) |
| personal_recovery_factor | decimal(3,2) | NO | 1.00 | - | 恢复系数 |
| last_volume_adjusted_at | timestamp | YES | NULL | - | 上次容量调整 |
| consecutive_training_weeks | int | NO | 0 | - | 连续训练周数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: hasMany → UserProfile, UserMembership, TrainingPlan, ChatSession, ChatTopic, TrainingLog, PersonalBest, CreditTransaction, CreditShare, CreditLog, Complaint, ExpertReview, UserCredit, UsageStat, Order, FitnessGoal, Feedback, Faq, PushSubscription, Referral, MembershipOrder, SocialAccount, PersonalAccessToken

---

### 1.2 user_profiles — 用户健身档案

**Model**: `App\Modules\User\Models\UserProfile`
**Migration**: create_user_profiles + add_preferred_rest_pattern + add_strength_progress + add_training_feedback + add_streak_fields

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | UNIQUE, FK | |
| basic_info | json | YES | NULL | - | age, gender, height, weight等 |
| fitness_goals | json | YES | NULL | - | primary_goal, target_weight等（见下方JSON结构） |
| training_preferences | json | YES | NULL | - | training_split, equipment等 |
| health_status | json | YES | NULL | - | injuries, chronic_diseases等 |
| nutrition_profile | json | YES | NULL | - | daily_calories, protein等 |
| strength_data | json | YES | NULL | - | bench/squat/deadlift等 |
| ffmi_assessment | json | YES | NULL | - | ffmi, bmi, assessment等 |
| version | int | NO | 1 | - | 数据版本号 |
| last_sync_at | timestamp | YES | NULL | - | 最后同步 |
| sync_status | varchar(50) | NO | 'synced' | IDX | 同步状态 |
| is_mcp_temp | boolean | NO | false | IDX | MCP临时数据 |
| mcp_session_id | varchar(100) | YES | NULL | - | MCP会话ID |
| sync_source | varchar(50) | YES | NULL | - | 同步来源 |
| preferred_rest_pattern | varchar(50) | YES | NULL | - | 练一休一/练二休一等 |
| strength_progress | json | YES | NULL | - | 力量进步追踪 |
| training_feedback | json | YES | NULL | - | 训练反馈数据 |
| current_streak | int unsigned | NO | 0 | - | 当前连续训练天数 |
| longest_streak | int unsigned | NO | 0 | - | 最长连续训练天数 |
| last_training_date | date | YES | NULL | - | 最后训练日期 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User

**fitness_goals JSON 结构**:
```json
{
  "primary_goal": "muscle_gain",     // 数据层枚举值（见下表）
  "secondary_goals": ["strength"],
  "target_weight": 75,
  "target_body_fat": 15
}
```

| 数据层枚举（MySQL存储） | DAML-RAG 工具层枚举 | 中文 | 说明 |
|------------------------|---------------------|------|------|
| `muscle_gain` | `hypertrophy` | 增肌 | task_executor `_GOAL_NORMALIZE` 自动转换 |
| `weight_loss` | `fat_loss` | 减脂 | 同上 |
| `fat_loss` | `fat_loss` | 减脂 | 新旧值均可 |
| `strength` | `strength` | 力量 | 无需转换 |
| `general_fitness` | `general_fitness` | 综合健身 | 无需转换 |
| `endurance` | `endurance` | 耐力 | 无需转换 |

> 详见 `daml-rag-server/docs/03-代码参考/12-枚举映射说明.md`

---

### 1.3 social_accounts — 第三方登录

**Model**: `App\Models\SocialAccount`
**Migration**: create_social_accounts

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| provider | varchar(50) | NO | - | - | 平台：wechat/qq/weibo |
| provider_id | varchar(255) | NO | - | - | 第三方用户ID |
| nickname | varchar(255) | YES | NULL | - | 昵称 |
| avatar | varchar(255) | YES | NULL | - | 头像 |
| access_token | text | YES | NULL | - | 访问令牌 |
| refresh_token | text | YES | NULL | - | 刷新令牌 |
| expires_at | timestamp | YES | NULL | - | 过期时间 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: UNIQUE(provider, provider_id)
**关联**: belongsTo → User

---

### 1.4 memberships — 会员等级定义

**Model**: `App\Modules\Membership\Models\Membership`
**Migration**: create_memberships + add_fields_to_memberships

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| name | varchar(50) | NO | - | - | 等级名称 |
| slug | varchar(50) | NO | - | UNIQUE | 唯一标识 |
| tier | varchar(50) | NO | 'free' | - | free/warmheart/energy |
| price | decimal(10,2) | NO | 0 | - | 价格（元） |
| duration_days | int | NO | - | - | 有效天数 |
| max_training_plans | int | NO | 3 | - | 最大计划数 |
| unlock_all_exercises | boolean | NO | false | - | 解锁所有动作 |
| ai_recommendation | boolean | NO | false | - | AI推荐 |
| data_analysis | boolean | NO | false | - | 数据分析 |
| coach_service | boolean | NO | false | - | 教练服务 |
| description | text | YES | NULL | - | 描述 |
| features | json | YES | NULL | - | 功能列表 |
| limits | json | YES | NULL | - | 限制说明 |
| sort_order | int | NO | 0 | - | 排序 |
| is_active | boolean | NO | true | IDX | 是否启用 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: hasMany → UserMembership, MembershipOrder; belongsToMany → User

---

### 1.5 user_memberships — 用户会员关联

**Model**: `App\Modules\Membership\Models\UserMembership`
**Migration**: create_user_memberships + add_fields_to_user_memberships

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| membership_id | bigint unsigned | NO | - | FK | |
| order_id | varchar(255) | YES | NULL | - | 订单ID |
| started_at | timestamp | YES | NULL | - | 生效时间 |
| expires_at | timestamp | YES | NULL | - | 过期时间 |
| auto_renew | boolean | NO | false | - | 自动续费 |
| status | varchar(50) | NO | 'active' | - | active/expired/cancelled |
| is_active | boolean | NO | true | IDX | 是否激活 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User, Membership

---

## 二、训练体系（9张表）

### 2.1 exercises — 动作库（v2重建）

**Model**: `App\Models\Exercise`（通过 recreate_exercises_table_v2 重建）
**Migration**: create_exercises + add_safety_fields + add_enhanced_fields + recreate_v2 + add_bodymap_fields + add_data_source_fields

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| name | varchar(255) | NO | - | FT | 动作名称（英文） |
| name_zh | varchar(255) | YES | NULL | FT | 动作名称（中文） |
| slug | varchar(255) | NO | - | UNIQUE | URL标识 |
| description | text | YES | NULL | FT | 描述（英文） |
| description_zh | text | YES | NULL | FT | 描述（中文） |
| correct_steps | json | YES | NULL | - | 正确步骤（英文） |
| correct_steps_zh | json | YES | NULL | - | 正确步骤（中文） |
| primary_muscle | varchar(255) | YES | NULL | IDX | 主要肌群 |
| secondary_muscles | json | YES | NULL | - | 次要肌群 |
| equipment | varchar(255) | YES | NULL | IDX | 所需器械 |
| difficulty | varchar(50) | YES | NULL | IDX | 难度等级 |
| force_type | varchar(50) | YES | NULL | - | push/pull/static |
| mechanic_type | varchar(50) | YES | NULL | - | compound/isolation |
| grips | json | YES | NULL | - | 握法列表 |
| categories | json | YES | NULL | - | 分类列表 |
| smart_tags | json | YES | NULL | - | 智能标签 |
| rating | int | NO | 0 | - | 评分 |
| view_count | int | NO | 0 | IDX | 浏览次数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: hasMany → ExerciseV2Media, TrainingRecord, TrainingProgress, TrainingPlanExercise, PersonalBest

---

### 2.2 exercise_v2_media — 动作媒体资源

**Model**: `App\Models\ExerciseV2Media`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| exercise_id | bigint unsigned | NO | - | FK, IDX | 动作ID |
| media_type | enum(image,video,thumbnail) | NO | - | IDX | 媒体类型 |
| cdn_url | varchar(255) | YES | NULL | - | CDN URL |
| local_path | varchar(255) | YES | NULL | - | 本地路径 |
| file_size | int | YES | NULL | - | 文件大小(字节) |
| duration | int | YES | NULL | - | 时长(秒，仅视频) |
| display_order | int | NO | 0 | - | 显示顺序 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → Exercise

---

### 2.3 training_plans — 训练计划

**Model**: `App\Modules\Training\Models\TrainingPlan`（支持软删除）

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| chat_session_id | bigint | YES | NULL | FK | 来源对话ID |
| name | varchar(255) | NO | - | - | 计划名称 |
| name_zh | varchar(255) | YES | NULL | - | 中文名称 |
| description | text | YES | NULL | - | 描述 |
| goal | enum(lose_weight,gain_muscle,maintain,improve_fitness) | YES | NULL | IDX | 训练目标 |
| difficulty | enum(novice,beginner,intermediate,advanced) | YES | NULL | - | 难度 |
| duration_weeks | int | NO | 4 | - | 总周数 |
| workouts_per_week | int | NO | 3 | - | 每周次数 |
| is_active | boolean | NO | true | - | 是否激活 |
| type | varchar(50) | NO | 'manual' | - | ai_generated/manual |
| started_at | timestamp | YES | NULL | - | 开始时间 |
| completed_at | timestamp | YES | NULL | - | 完成时间 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |
| deleted_at | timestamp | YES | NULL | - | 软删除 |

**Scope**: byUser, active, aIGenerated, manual, byDifficulty, byGoal
**关联**: belongsTo → User, ChatSession; hasMany → TrainingPlanExercise, UserNutritionPlan, TrainingLog

---

### 2.4 training_plan_exercises — 计划动作关联

**Model**: `App\Modules\Training\Models\TrainingPlanExercise`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| plan_id | bigint unsigned | NO | - | FK, IDX | 训练计划ID |
| exercise_id | bigint | YES | NULL | FK | 动作ID |
| exercise_name | varchar(255) | NO | - | - | 动作名称 |
| sets | int | NO | 3 | - | 组数 |
| reps | varchar(255) | NO | '8-12' | - | 次数范围 |
| weight | varchar(255) | YES | NULL | - | 建议重量 |
| rest_time | varchar(255) | NO | '60s' | - | 组间休息 |
| notes | text | YES | NULL | - | 备注 |
| order_index | int | NO | 0 | - | 排序 |
| day_of_week | tinyint | YES | NULL | - | 星期几(1-7) |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → TrainingPlan, Exercise

---

### 2.5 training_sessions — 训练会话

**Model**: `App\Models\TrainingSession`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| plan_id | bigint | YES | NULL | FK | 关联计划ID |
| session_name | varchar(255) | NO | - | - | 训练名称 |
| notes | text | YES | NULL | - | 笔记 |
| duration_minutes | int | YES | NULL | - | 时长(分钟) |
| status | enum(pending,in_progress,completed,skipped) | NO | 'pending' | IDX | 状态 |
| started_at | datetime | YES | NULL | - | 开始时间 |
| completed_at | datetime | YES | NULL | IDX | 完成时间 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User, TrainingPlan; hasMany → TrainingRecord

---

### 2.6 training_records — 训练记录（组/次/重量）

**Model**: `App\Models\TrainingRecord`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| session_id | bigint unsigned | NO | - | FK, IDX | 训练会话ID |
| exercise_id | bigint unsigned | NO | - | FK, IDX | 动作ID |
| set_number | int | NO | - | - | 第几组 |
| reps | int | NO | - | - | 次数 |
| weight | decimal(8,2) | YES | NULL | - | 重量(kg) |
| rpe | int | YES | NULL | - | RPE(1-10) |
| rest_seconds | int | YES | NULL | - | 组间休息(秒) |
| notes | text | YES | NULL | - | 备注 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → TrainingSession, Exercise

---

### 2.7 training_progress — 训练进度汇总

**Model**: `App\Models\TrainingProgress`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| exercise_id | bigint unsigned | NO | - | FK | |
| max_weight | decimal(8,2) | YES | NULL | - | 最大重量(kg) |
| max_reps | int | YES | NULL | - | 最大次数 |
| estimated_1rm | decimal(8,2) | YES | NULL | - | 估算1RM |
| total_volume | decimal(10,2) | YES | NULL | - | 总容量(kg) |
| total_sets | int | NO | 0 | - | 总组数 |
| total_reps | int | NO | 0 | - | 总次数 |
| last_trained_at | date | YES | NULL | IDX | 最后训练日期 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: UNIQUE(user_id, exercise_id)
**关联**: belongsTo → User, Exercise

---

### 2.8 training_logs — 训练日志

**Model**: `App\Modules\Training\Models\TrainingLog`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| training_plan_id | bigint | YES | NULL | - | 计划ID |
| plan_week | int | YES | NULL | - | 计划周数 |
| plan_day | int | YES | NULL | - | 计划日期 |
| session_date | date | NO | - | IDX | 训练日期 |
| planned_exercises | json | YES | NULL | - | 计划动作 |
| actual_exercises | json | YES | NULL | - | 实际完成 |
| completion_rate | decimal(3,2) | YES | NULL | - | 完成率(0-1) |
| avg_rpe | decimal(3,1) | YES | NULL | - | 平均RPE |
| week_number | int | YES | NULL | - | 周期内第几周 |
| mesocycle_id | varchar(50) | YES | NULL | IDX | 中周期ID |
| notes | text | YES | NULL | - | 备注 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**Scope**: forUser, dateRange, forMesocycle
**关联**: belongsTo → User, TrainingPlan

---

### 2.9 personal_bests — 个人最佳记录

**Model**: `App\Modules\Training\Models\PersonalBest`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| exercise_id | varchar(50) | NO | - | IDX | Neo4j Exercise节点ID |
| exercise_name | varchar(100) | YES | NULL | - | 动作名称(冗余) |
| best_weight | decimal(5,2) | YES | NULL | - | 最佳重量(kg) |
| best_reps | int | YES | NULL | - | 最佳次数 |
| estimated_1rm | decimal(5,2) | YES | NULL | - | 估算1RM(kg) |
| achieved_date | date | YES | NULL | - | 达成日期 |
| last_used_weight | decimal(5,2) | YES | NULL | - | 上次使用重量 |
| last_used_date | date | YES | NULL | - | 上次使用日期 |
| usage_count | int | NO | 0 | - | 使用次数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: UNIQUE(user_id, exercise_id)
**关联**: belongsTo → User

---

## 三、AI对话体系（5张表）

### 3.1 chat_topics — 对话话题

**Model**: `App\Modules\Chat\Models\ChatTopic`（支持软删除）

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| name | varchar(100) | NO | - | - | 话题名称 |
| description | text | YES | NULL | - | 描述 |
| message_count | int | NO | 0 | - | 消息数量 |
| last_message | text | YES | NULL | - | 最后一条消息 |
| last_message_at | timestamp | YES | NULL | - | 最后消息时间 |
| created_at | timestamp | NO | - | IDX | |
| updated_at | timestamp | NO | - | - | |
| deleted_at | timestamp | YES | NULL | - | 软删除 |

**关联**: belongsTo → User; hasMany → ChatSession, ChatMessage

---

### 3.2 chat_sessions — 对话记录（含三轨评分）

**Model**: `App\Modules\Chat\Models\ChatSession`
**Migration**: create_chat_sessions + add_three_track_rating + add_training_effect + add_topic_id + add_performance_fields

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| session_id | char(36) | NO | - | IDX | 会话UUID |
| user_id | bigint | YES | NULL | FK, IDX | 匿名用户为NULL |
| topic_id | bigint | YES | NULL | FK | 话题ID |
| user_query | text | NO | - | - | 用户问题 |
| llm_response | text | NO | - | - | AI回答 |
| model_used | varchar(50) | NO | - | IDX | 使用的模型 |
| tools_used | json | YES | NULL | - | 调用的工具列表 |
| metadata | json | YES | NULL | - | 元数据 |
| user_rating | tinyint | YES | NULL | - | 用户评分(1-5) |
| user_feedback | varchar(500) | YES | NULL | - | 用户反馈 |
| qdrant_point_id | char(36) | YES | NULL | IDX | Qdrant向量点ID |
| **三轨评分 — UX维度** | | | | | |
| ux_clarity | tinyint | YES | NULL | - | 易懂性(1-5) |
| ux_practicality | tinyint | YES | NULL | - | 实用性(1-5) |
| ux_detail | tinyint | YES | NULL | - | 详细程度(1-5) |
| ux_friendliness | tinyint | YES | NULL | - | 友好度(1-5) |
| ux_satisfaction | tinyint | YES | NULL | - | 满意度(1-5) |
| **三轨评分 — 个性化维度** | | | | | |
| profile_utilization_rate | decimal(5,2) | YES | NULL | - | 档案利用率(0-100%) |
| goal_alignment | decimal(5,2) | YES | NULL | - | 目标对齐度(0-100%) |
| uniqueness | decimal(5,2) | YES | NULL | - | 独特性(0-100%) |
| dynamic_adjustment | decimal(5,2) | YES | NULL | - | 动态调整(0-100%) |
| **三轨评分 — 综合** | | | | | |
| personalization_grade | enum(S,A,B,C,D) | YES | NULL | IDX | 个性化等级 |
| fewshot_eligible | boolean | NO | false | IDX | Few-Shot条件 |
| overall_score | decimal(3,2) | YES | NULL | IDX | 综合评分(0-5) |
| training_effect | varchar(50) | YES | NULL | - | 训练效果标签 |
| **性能监控字段** | | | | | |
| ttfb_ms | int unsigned | YES | NULL | - | 首字节时间(毫秒) |
| duration_ms | int unsigned | YES | NULL | - | 总耗时(毫秒) |
| tokens_per_sec | decimal(6,2) | YES | NULL | - | 令牌生成速率 |
| backend_used | varchar(50) | YES | NULL | IDX | 实际后端(anthropic/deepseek/glm/siliconflow) |
| execution_mode | enum(dag,agent) | NO | dag | IDX | 执行模式 |
| template_name | varchar(100) | YES | NULL | - | 模板名称 |
| input_tokens | int unsigned | NO | 0 | - | 输入Token数 |
| output_tokens | int unsigned | NO | 0 | - | 输出Token数 |
| estimated_cost | decimal(8,4) | YES | NULL | - | 估算费用(美元) |
| credits_consumed | int unsigned | NO | 0 | - | 消耗积分 |
| fallback_count | tinyint unsigned | NO | 0 | - | 降级次数 |
| error_type | varchar(50) | YES | NULL | - | 错误类型 |
| created_at | timestamp | NO | - | IDX | |
| updated_at | timestamp | NO | - | - | |

**索引**: idx_cs_backend(backend_used), idx_cs_mode(execution_mode), idx_cs_date_backend(created_at, backend_used), idx_cs_date_mode(created_at, execution_mode)
**Scope**: byUser, bySession, highQuality, fewShotEligible, byPersonalizationGrade, highPersonalization, byModel, recent, byBackend, byMode, withPerformance
**关联**: belongsTo → User, ChatTopic; hasMany → ChatMessage, ExpertReview, TrainingPlan

---

### 3.3 chat_messages — 对话消息

**Model**: `App\Modules\Chat\Models\ChatMessage`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| topic_id | bigint unsigned | NO | - | FK, IDX | 话题ID |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| role | enum(user,assistant,system) | NO | - | - | 消息角色 |
| content | text | NO | - | - | 消息内容 |
| metadata | json | YES | NULL | - | 元数据(工具调用等) |
| client_id | varchar(64) | YES | NULL | UNIQUE | 客户端消息ID(去重) |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: INDEX(topic_id, created_at)
**关联**: belongsTo → ChatTopic, User

---

### 3.4 expert_reviews — 专家评审

**Model**: `App\Modules\Chat\Models\ExpertReview`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| chat_session_id | bigint unsigned | NO | - | FK, IDX | 对话会话ID |
| expert_id | bigint unsigned | NO | - | FK, IDX | 评审专家ID |
| **6维度评分(1-5)** | | | | | |
| accuracy | tinyint | NO | - | - | 专业准确性 |
| scientific | tinyint | NO | - | - | 科学合理性 |
| safety | tinyint | NO | - | IDX | 安全性(<3一票否决) |
| completeness | tinyint | NO | - | - | 完整性 |
| practicality | tinyint | NO | - | - | 实用性 |
| personalization | tinyint | NO | - | - | 个性化适配度 |
| comments | text | YES | NULL | - | 评审意见 |
| improvement_suggestions | json | YES | NULL | - | 改进建议 |
| reviewed_at | timestamp | NO | CURRENT | IDX | 评审时间 |
| updated_at | timestamp | NO | CURRENT | - | |

**索引**: UNIQUE(chat_session_id, expert_id)
**关联**: belongsTo → ChatSession, User(expert)

---

### 3.5 complaints — 投诉记录

**Model**: `App\Models\Complaint`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| chat_session_id | bigint | YES | NULL | FK | 关联对话 |
| type | enum(content_quality,content_safety,technical_error,inappropriate,other) | NO | 'other' | IDX | 投诉类型 |
| content | text | NO | - | - | 投诉内容 |
| screenshot_url | varchar(255) | YES | NULL | - | 截图URL |
| status | enum(pending,processing,resolved,rejected,closed) | NO | 'pending' | IDX | 状态 |
| handler_id | varchar(255) | YES | NULL | - | 处理人ID |
| handler_response | text | YES | NULL | - | 处理回复 |
| handled_at | timestamp | YES | NULL | - | 处理时间 |
| created_at | timestamp | NO | - | IDX | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User, ChatSession

---

## 四、积分/会员/订单体系（8张表）

### 4.1 user_credits — 用户积分配额

**Model**: `App\Modules\Credit\Models\UserCredit`
**Migration**: recreate_user_credits_for_credit_system（v2重建，旧表已drop）

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | UNIQUE, FK, IDX | 每用户一条 |
| daily_quota | int unsigned | NO | 10 | - | 每日配额(免费10/暖心50/能量200) |
| daily_consumed | int unsigned | NO | 0 | - | 今日已消耗 |
| total_consumed | bigint unsigned | NO | 0 | - | 历史总消耗 |
| last_reset_date | date | NO | - | IDX | 上次配额重置日期 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User

---

### 4.2 credit_transactions — 积分交易流水

**Model**: `App\Modules\Credit\Models\CreditTransaction`
**Migration**: create_credit_transactions + add_unique_conversation_id

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| credits | int | NO | - | - | 消耗积分(正=消耗,负=充值) |
| tokens | int | NO | - | - | Token总数 |
| mode | enum(dag,agent) | NO | - | - | dag=1.0x, agent=1.5x |
| template_name | varchar(100) | YES | NULL | - | DAG模板名称 |
| conversation_id | varchar(100) | YES | NULL | - | 会话ID(幂等) |
| input_tokens | int | NO | 0 | - | 输入Token |
| output_tokens | int | NO | 0 | - | 输出Token |
| description | varchar(255) | YES | NULL | - | 交易描述 |
| created_at | timestamp | NO | CURRENT | IDX | 只有created_at |

**索引**: UNIQUE(user_id, conversation_id), INDEX(user_id, created_at)
**关联**: belongsTo → User

---

### 4.3 credit_shares — 积分分享记录

**Model**: `App\Modules\Credit\Models\CreditShare`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| sender_id | bigint unsigned | NO | - | FK, IDX | 发送者(能量会员) |
| receiver_id | bigint unsigned | NO | - | FK, IDX | 接收者 |
| credits | int | NO | - | - | 分享积分数 |
| message | varchar(255) | YES | NULL | - | 分享留言 |
| created_at | timestamp | NO | CURRENT | IDX | 只有created_at |

**关联**: belongsTo → User(sender), User(receiver)

---

### 4.4 credit_logs — 额度变更日志（旧体系）

**Model**: `App\Models\CreditLog`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| dag_amount | int | NO | 0 | - | DAG额度变更量 |
| agent_amount | int | NO | 0 | - | Agent额度变更量 |
| reason | varchar(255) | NO | - | - | 变更原因 |
| admin_id | bigint | YES | NULL | FK | 操作管理员ID |
| created_at | timestamp | NO | CURRENT | IDX | 只有created_at |

**关联**: belongsTo → User, User(admin)

---

### 4.5 usage_stats — 用量统计（新版）

**Model**: `App\Modules\Membership\Models\UsageStat`

> ⚠️ 旧的 `user_usage_stats` 和 `user_bonus_credits` 表已被此 migration drop，不再存在。

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| date | date | NO | - | IDX | 统计日期 |
| dag_queries | int unsigned | NO | 0 | - | DAG查询次数 |
| agent_queries | int unsigned | NO | 0 | - | Agent查询次数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: UNIQUE(user_id, date)
**关联**: belongsTo → User

---

### 4.6 orders — 订单（打赏支付）

**Model**: `App\Modules\Order\Models\Order`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| order_no | varchar(32) | NO | - | UNIQUE | 订单号 |
| user_id | bigint unsigned | NO | - | FK | |
| membership_id | bigint unsigned | NO | - | FK | |
| amount | decimal(10,2) | NO | - | - | 订单金额 |
| actual_amount | decimal(10,2) | YES | NULL | - | 实付金额 |
| discount_amount | decimal(10,2) | NO | 0 | - | 优惠金额 |
| status | varchar(20) | NO | 'pending' | - | pending/paid/cancelled/refunded/reviewing |
| pay_method | varchar(20) | YES | NULL | - | wechat/alipay |
| pay_trade_no | varchar(64) | YES | NULL | - | 支付交易号 |
| paid_at | timestamp | YES | NULL | - | 支付时间 |
| payment_proof_url | varchar(255) | YES | NULL | - | 支付截图URL |
| proof_uploaded_at | timestamp | YES | NULL | - | 截图上传时间 |
| reviewer_id | bigint | YES | NULL | - | 审核人ID |
| reviewed_at | timestamp | YES | NULL | - | 审核时间 |
| review_note | text | YES | NULL | - | 审核备注 |
| remark | text | YES | NULL | - | 订单备注 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: INDEX(user_id, status), INDEX(status, created_at)
**关联**: belongsTo → User, Membership

---

### 4.7 membership_orders — 会员订单

**Model**: `App\Modules\Membership\Models\MembershipOrder`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| order_no | varchar(32) | NO | - | UNIQUE | 订单号 |
| user_id | bigint unsigned | NO | - | FK, IDX | |
| membership_id | bigint unsigned | NO | - | FK | |
| amount | decimal(10,2) | NO | - | - | 实付金额 |
| discount_amount | decimal(10,2) | NO | 0 | - | 优惠金额 |
| pay_method | varchar(20) | YES | NULL | - | wechat/alipay/manual |
| status | enum(pending,paid,failed,refunded,cancelled) | NO | 'pending' | IDX | 状态 |
| paid_at | timestamp | YES | NULL | - | 支付时间 |
| created_at | timestamp | NO | CURRENT | IDX | |

**关联**: belongsTo → User, Membership

---

### 4.8 referrals — 推荐关系

**Model**: `App\Modules\Membership\Models\Referral`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| referrer_id | bigint unsigned | NO | - | FK, IDX | 推荐人ID |
| referee_id | bigint unsigned | NO | - | FK, UNIQUE | 被推荐人ID |
| status | enum(registered,paid) | NO | 'registered' | - | 状态 |
| reward_granted | boolean | NO | false | - | 奖励已发放 |
| cashback_amount | decimal(10,2) | NO | 0 | - | 返现金额 |
| created_at | timestamp | NO | CURRENT | - | |

**关联**: belongsTo → User(referrer), User(referee)

---

## 五、其他功能表（11张表）

### 5.1 foods — 食物营养库

**Model**: `App\Models\Food`
**数据来源**: 《中国食物成分表》(1,851条)

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| food_code | varchar(20) | NO | - | UNIQUE | 食物编码 |
| name | varchar(100) | NO | - | IDX | 食物名称 |
| category | varchar(50) | NO | - | IDX | 大类 |
| subcategory | varchar(50) | YES | NULL | IDX | 小类 |
| edible | decimal(5,1) | NO | 100 | - | 可食部(%) |
| water | decimal(5,1) | YES | NULL | - | 水分(g) |
| energy_kcal | decimal(6,1) | YES | NULL | IDX | 能量(kcal) |
| energy_kj | decimal(7,1) | YES | NULL | - | 能量(kJ) |
| protein | decimal(5,2) | YES | NULL | IDX | 蛋白质(g) |
| fat | decimal(5,2) | YES | NULL | - | 脂肪(g) |
| carbohydrate | decimal(5,2) | YES | NULL | - | 碳水(g) |
| dietary_fiber | decimal(5,2) | YES | NULL | - | 膳食纤维(g) |
| cholesterol | decimal(6,1) | YES | NULL | - | 胆固醇(mg) |
| ash | decimal(5,2) | YES | NULL | - | 灰分(g) |
| vitamin_a | decimal(7,2) | YES | NULL | - | 维A(μg) |
| carotene | decimal(7,2) | YES | NULL | - | 胡萝卜素(μg) |
| retinol | decimal(7,2) | YES | NULL | - | 视黄醇(μg) |
| thiamin | decimal(6,3) | YES | NULL | - | B1(mg) |
| riboflavin | decimal(5,3) | YES | NULL | - | B2(mg) |
| niacin | decimal(6,3) | YES | NULL | - | 烟酸(mg) |
| vitamin_c | decimal(6,2) | YES | NULL | - | 维C(mg) |
| vitamin_e_total | decimal(6,3) | YES | NULL | - | 维E(mg) |
| calcium | decimal(7,2) | YES | NULL | - | 钙(mg) |
| phosphorus | decimal(7,2) | YES | NULL | - | 磷(mg) |
| potassium | decimal(7,2) | YES | NULL | - | 钾(mg) |
| sodium | decimal(7,2) | YES | NULL | - | 钠(mg) |
| magnesium | decimal(7,2) | YES | NULL | - | 镁(mg) |
| iron | decimal(6,3) | YES | NULL | - | 铁(mg) |
| zinc | decimal(6,3) | YES | NULL | - | 锌(mg) |
| selenium | decimal(7,3) | YES | NULL | - | 硒(μg) |
| copper | decimal(6,3) | YES | NULL | - | 铜(mg) |
| manganese | decimal(6,3) | YES | NULL | - | 锰(mg) |
| remark | varchar(255) | YES | NULL | - | 备注 |
| gi_value | int | YES | NULL | - | GI值 |
| price_level | varchar(10) | YES | NULL | - | 价格等级 |
| view_count | int | NO | 0 | - | 浏览次数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

---

### 5.2 progress_records — 体测记录

**Model**: `App\Models\ProgressRecord`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| date | date | NO | - | IDX | 记录日期 |
| weight | decimal(5,2) | NO | - | - | 体重(kg) |
| body_fat | decimal(4,1) | YES | NULL | - | 体脂率(%) |
| ffmi | decimal(4,2) | YES | NULL | - | FFMI指数 |
| lean_body_mass | decimal(5,2) | YES | NULL | - | 瘦体重(kg) |
| measurements | json | YES | NULL | - | 围度:chest,waist,hips,arms,thighs |
| photos | json | YES | NULL | - | 进度照片URL数组 |
| notes | text | YES | NULL | - | 备注 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: UNIQUE(user_id, date)
**关联**: belongsTo → User

---

### 5.3 fitness_goals — 健身目标

**Model**: `App\Models\FitnessGoal`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| type | enum(weight,body_fat,muscle_mass,strength,custom) | NO | - | IDX | 目标类型 |
| name | varchar(100) | NO | - | - | 目标名称 |
| target_value | decimal(8,2) | NO | - | - | 目标值 |
| current_value | decimal(8,2) | NO | - | - | 当前值 |
| start_value | decimal(8,2) | NO | - | - | 起始值 |
| unit | varchar(20) | NO | - | - | 单位(kg/%/次) |
| start_date | date | NO | - | - | 开始日期 |
| target_date | date | YES | NULL | - | 目标日期 |
| completed_at | date | YES | NULL | - | 完成日期 |
| status | enum(active,completed,abandoned) | NO | 'active' | - | 状态 |
| notes | text | YES | NULL | - | 备注 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: INDEX(user_id, status)
**关联**: belongsTo → User

---

### 5.4 feedbacks — 用户反馈

**Model**: `App\Models\Feedback`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| type | varchar(50) | NO | - | - | 反馈类型 |
| content | text | NO | - | - | 反馈内容 |
| status | varchar(20) | NO | 'pending' | - | 状态 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User

---

### 5.5 faqs — 常见问题

**Model**: `App\Models\Faq`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | YES | NULL | FK | 提问用户 |
| question | text | NO | - | - | 问题 |
| answer | text | YES | NULL | - | 回答 |
| category | varchar(50) | YES | NULL | - | 分类 |
| is_published | boolean | NO | false | - | 是否发布 |
| sort_order | int | NO | 0 | - | 排序 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: belongsTo → User

---

### 5.6 knowledge_categories — 知识分类

**Model**: 无独立Model

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| name | varchar(50) | NO | - | - | 分类名称 |
| slug | varchar(50) | NO | - | UNIQUE | URL标识 |
| parent_id | bigint unsigned | YES | NULL | FK, IDX | 父分类(NULL=顶级) |
| icon | varchar(20) | YES | NULL | - | 图标emoji |
| sort_order | int unsigned | NO | 0 | IDX | 排序权重 |
| description | text | YES | NULL | - | 描述 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**关联**: self-referencing → parent_id

---

### 5.7 knowledge_articles — 知识文章

**Model**: 无独立Model

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| title | varchar(200) | NO | - | FT | 标题 |
| summary | text | NO | - | FT | 摘要(200字内) |
| content | longText | NO | - | FT | 正文Markdown |
| category_id | bigint unsigned | NO | - | FK, IDX | 所属分类 |
| source_book | varchar(200) | YES | NULL | - | 来源书名 |
| source_chapter | varchar(100) | YES | NULL | - | 来源章节 |
| source_page | varchar(20) | YES | NULL | - | 来源页码 |
| tags | json | YES | NULL | - | 标签数组 |
| status | enum(draft,published,archived) | NO | 'draft' | IDX | 状态 |
| difficulty | enum(beginner,intermediate,advanced) | NO | 'intermediate' | IDX | 难度 |
| view_count | int unsigned | NO | 0 | - | 浏览次数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**全文索引**: FULLTEXT(title, summary, content)
**关联**: belongsTo → KnowledgeCategory

---

### 5.8 knowledge_ingestion_status — 知识导入状态

**Model**: 无独立Model

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| document_id | varchar(64) | NO | - | UNIQUE | MD5(filepath) |
| filename | varchar(255) | NO | - | IDX | 原始文件名 |
| filepath | varchar(512) | YES | NULL | - | 完整路径 |
| source_type | enum(bilibili_subtitle,pdf_textbook,markdown_note) | NO | - | IDX | 来源类型 |
| status | enum(pending,processing,completed,failed) | NO | 'pending' | IDX | 状态 |
| chunk_count | int unsigned | NO | 0 | - | chunk数 |
| vector_count | int unsigned | NO | 0 | - | 入库向量数 |
| total_chars | int unsigned | NO | 0 | - | 总字符数 |
| error_message | text | YES | NULL | - | 错误信息 |
| qdrant_collection | varchar(100) | NO | 'training_knowledge' | - | Qdrant集合 |
| embedding_model | varchar(100) | NO | 'thenlper/gte-large-zh' | - | 嵌入模型 |
| processing_time_ms | int unsigned | NO | 0 | - | 处理耗时(ms) |
| metadata | json | YES | NULL | - | 额外元数据 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

---

### 5.9 user_nutrition_plans — 用户营养计划

**Model**: `App\Modules\Training\Models\UserNutritionPlan`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| plan_id | bigint unsigned | NO | - | FK | 训练计划ID |
| food_id | bigint unsigned | YES | NULL | FK | 食物ID |
| food_name | varchar(100) | NO | - | - | 食物名称 |
| meal_type | enum(breakfast,lunch,dinner,snack) | NO | - | - | 餐次 |
| portion_grams | decimal(6,1) | NO | 100 | - | 份量(克) |
| day_of_week | tinyint | YES | NULL | - | 星期几(1-7) |
| notes | text | YES | NULL | - | 备注 |
| order_index | int | NO | 0 | - | 排序 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: INDEX(plan_id, day_of_week, meal_type)
**关联**: belongsTo → TrainingPlan, Food

---

### 5.10 plan_templates — 训练计划模板

**Model**: `App\Modules\Training\Models\PlanTemplate`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| name | varchar(100) | NO | - | - | 模板名称 |
| description | text | YES | NULL | - | 描述 |
| goal | enum(lose_weight,gain_muscle,maintain,improve_fitness) | NO | - | - | 训练目标 |
| level | enum(novice,beginner,intermediate,advanced) | NO | - | - | 适合等级 |
| duration_weeks | int | NO | 4 | - | 周期(周) |
| workouts_per_week | int | NO | 3 | - | 每周次数 |
| exercises | json | NO | - | - | 动作列表JSON |
| tags | json | YES | NULL | - | 标签 |
| is_active | boolean | NO | true | - | 是否启用 |
| use_count | int | NO | 0 | - | 使用次数 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: INDEX(goal, level)

---

### 5.11 push_subscriptions — 推送订阅

**Model**: `App\Models\PushSubscription`

| 字段名 | 类型 | Nullable | Default | 索引 | 说明 |
|--------|------|----------|---------|------|------|
| id | bigint unsigned | NO | AI | PK | |
| user_id | bigint unsigned | NO | - | FK | |
| endpoint | text | NO | - | - | 推送端点 |
| p256dh | varchar(255) | YES | NULL | - | 公钥 |
| auth | varchar(255) | YES | NULL | - | 认证密钥 |
| reminder_time | varchar(5) | NO | '09:00' | - | 提醒时间 |
| is_active | boolean | NO | true | - | 是否激活 |
| created_at | timestamp | NO | - | - | |
| updated_at | timestamp | NO | - | - | |

**索引**: INDEX(user_id, is_active)
**关联**: belongsTo → User

---

## 六、Laravel 系统表 + Spatie 权限表（7张表）

### 6.1 password_reset_tokens

| 字段名 | 类型 | 说明 |
|--------|------|------|
| email | varchar(255) | PK |
| token | varchar(255) | 重置令牌 |
| created_at | timestamp | |

### 6.2 failed_jobs

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint unsigned | PK |
| uuid | varchar(255) | UNIQUE |
| connection | text | 连接 |
| queue | text | 队列 |
| payload | longText | 负载 |
| exception | longText | 异常 |
| failed_at | timestamp | 失败时间 |

### 6.3 personal_access_tokens (Sanctum)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint unsigned | PK |
| tokenable_type/id | morphs | 多态关联 |
| name | varchar(255) | 令牌名称 |
| token | varchar(64) | UNIQUE, hash |
| abilities | text | 权限 |
| last_used_at | timestamp | 最后使用 |
| expires_at | timestamp | 过期时间 |

### 6.4 chinese_holidays — 中国节假日

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint unsigned | PK |
| date | date | UNIQUE, 日期 |
| name | varchar(50) | 节日名称 |
| type | enum(holiday,workday) | 类型 |
| year | int | 年份, IDX |

### 6.5 user_favorite_exercises — 用户收藏动作

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint unsigned | PK |
| user_id | bigint unsigned | FK |
| exercise_id | bigint unsigned | FK |
| created_at | timestamp | |

**索引**: UNIQUE(user_id, exercise_id)

### 6.6 knowledge_references — 知识引用

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | bigint unsigned | PK |
| article_id | bigint unsigned | FK → knowledge_articles |
| referenced_article_id | bigint unsigned | FK → knowledge_articles |
| context | varchar(500) | 引用上下文 |

**索引**: UNIQUE(article_id, referenced_article_id)

### 6.7 Spatie 权限表（4张）

`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`
— 标准 Spatie Permission 结构，详见 `spatie/laravel-permission` 文档。

---

## 📝 已废弃的表（被 migration drop）

| 表名 | 废弃原因 | 替代 |
|------|---------|------|
| user_usage_stats | 被 usage_stats migration drop | usage_stats |
| user_bonus_credits | 被 usage_stats migration drop | credit_transactions |
| user_credits (旧版) | 被 recreate_user_credits drop重建 | user_credits (新版) |

---

**最后更新**: 2026-02-24 | **维护者**: 薛小川 / Claude Code
