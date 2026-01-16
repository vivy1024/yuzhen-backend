# 数据库表-Model-前端功能对照分析

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-16

---

## 📊 总览

| 类别 | 数量 |
|------|------|
| 数据库表 | 35 |
| 有效Model | 28 |
| 废弃Model | 4 |
| 前端页面模块 | 15 |

---

## ✅ 正常表（有Model + 有前端功能）

| 表名 | Model | 前端页面 | 状态 |
|------|-------|---------|------|
| `users` | `User.php` | auth/, user/ | ✅ 正常 |
| `user_profiles` | `UserProfile.php` | user/profile.vue | ✅ 正常 |
| `exercises` | `Exercise.php` | exercise/library.vue | ✅ 正常 |
| `exercise_v2_media` | `ExerciseMedia.php` | exercise/detail.vue | ✅ 正常 |
| `foods` | `Food.php` | food/library.vue | ✅ 正常 |
| `memberships` | `Membership.php` | membership/center.vue | ✅ 正常 |
| `user_memberships` | `UserMembership.php` | membership/center.vue | ✅ 正常 |
| `membership_orders` | `MembershipOrder.php` | admin/orders.vue | ✅ 正常 |
| `training_plans` | `TrainingPlan.php` | training/plans.vue | ✅ 正常 |
| `training_sessions` | `TrainingSession.php` | training/session.vue | ✅ 正常 |
| `training_records` | `TrainingRecord.php` | training/history.vue | ✅ 正常 |
| `training_logs` | `TrainingLog.php` | training/stats.vue | ✅ 正常 |
| `training_progress` | `TrainingProgress.php` | progress/dashboard.vue | ✅ 正常 |
| `chat_sessions` | `ChatSession.php` | ai/chat.vue | ✅ 正常 |
| `chat_topics` | `ChatTopic.php` | ai/chat.vue | ✅ 正常 |
| `faqs` | `Faq.php` | help/index.vue | ✅ 正常 |
| `feedbacks` | `Feedback.php` | feedback/index.vue | ✅ 正常 |
| `user_credits` | `UserCredit.php` | membership/center.vue | ✅ 正常 |
| `credit_logs` | `CreditLog.php` | membership/center.vue | ✅ 正常 |
| `referrals` | `Referral.php` | membership/center.vue | ✅ 正常 |
| `usage_stats` | `UsageStat.php` | admin/dashboards/ | ✅ 正常 |
| `expert_reviews` | `ExpertReview.php` | admin/expert-review.vue | ✅ 正常 |
| `personal_bests` | `PersonalBest.php` | progress/dashboard.vue | ✅ 正常 |
| `fitness_goals` | `FitnessGoal.php` | progress/dashboard.vue | ✅ 正常 |
| `progress_records` | `ProgressRecord.php` | progress/dashboard.vue | ✅ 正常 |
| `chinese_holidays` | `ChineseHoliday.php` | (后端使用) | ✅ 正常 |
| `social_accounts` | `SocialAccount.php` | (第三方登录) | ✅ 正常 |

---

## ⚠️ 系统表（无需Model）

| 表名 | 说明 | 状态 |
|------|------|------|
| `migrations` | Laravel迁移记录 | ✅ 系统表 |
| `failed_jobs` | 失败任务队列 | ✅ 系统表 |
| `password_reset_tokens` | 密码重置令牌 | ✅ 系统表 |
| `personal_access_tokens` | API令牌(Sanctum) | ✅ 系统表 |

---

## ⚠️ 关联表（通过主Model访问）

| 表名 | 主Model | 说明 | 状态 |
|------|---------|------|------|
| `training_plan_exercises` | TrainingPlan | 计划-动作关联 | ✅ 正常 |
| `user_favorite_exercises` | User | 用户收藏动作 | ✅ 正常 |
| `orders` | Order.php | 通用订单 | ✅ 正常 |
| `complaints` | Complaint.php | 投诉 | ✅ 正常 |

---

## ❌ 废弃Model（引用不存在的表）

| Model文件 | 引用表名 | 问题 | 建议 |
|-----------|---------|------|------|
| `ExerciseSecondaryMuscle.php` | `exercise_v2_secondary_muscles` | 表不存在 | 删除 |
| `ExerciseTag.php` | `exercise_v2_tags` | 表不存在 | 删除 |
| `ExerciseInstruction.php` | `exercise_v2_instructions` | 表不存在 | 删除 |
| `Payment.php` | `payments` | 表不存在 | 删除 |

**说明**: 这些是旧架构遗留，当时计划用分表存储动作的次要肌肉、标签、指导步骤，但后来改为在exercises表中用JSON字段存储（`all_muscles_zh`, `smart_tags`, `correct_steps_zh`等）。

---

## 📝 缺失的Model

| 表名 | 说明 | 是否需要Model |
|------|------|--------------|
| `chat_messages` | 聊天消息 | ⚠️ 有Model但表可能未创建 |

---

## 🔧 建议操作

### 1. 删除废弃Model（低优先级）
```bash
# 这些Model未被使用，可以安全删除
rm yuzhen-backend/app/Modules/Exercise/Models/ExerciseSecondaryMuscle.php
rm yuzhen-backend/app/Modules/Exercise/Models/ExerciseTag.php
rm yuzhen-backend/app/Modules/Exercise/Models/ExerciseInstruction.php
rm yuzhen-backend/app/Modules/Membership/Models/Payment.php
```

### 2. 检查chat_messages表
```bash
docker exec fitness_mysql mysql -u root -proot_password_2025 fitness_app -e "SHOW TABLES LIKE 'chat_messages';"
```

---

## 📊 前端功能覆盖

| 前端模块 | 对应表 | 功能状态 |
|---------|--------|---------|
| auth/ | users, password_reset_tokens | ✅ 完整 |
| user/ | users, user_profiles | ✅ 完整 |
| exercise/ | exercises, exercise_v2_media | ✅ 完整 |
| food/ | foods | ✅ 完整 |
| training/ | training_plans, training_sessions, training_records, training_logs | ✅ 完整 |
| ai/ | chat_sessions, chat_topics | ✅ 完整 |
| membership/ | memberships, user_memberships, user_credits, credit_logs | ✅ 完整 |
| progress/ | training_progress, personal_bests, fitness_goals, progress_records | ✅ 完整 |
| help/ | faqs | ✅ 完整 |
| feedback/ | feedbacks | ✅ 完整 |
| admin/ | usage_stats, expert_reviews, membership_orders | ✅ 完整 |

---

**维护者**: 薛小川
**最后更新**: 2026-01-16
