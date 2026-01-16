# MySQL数据库结构 - 生产稳定版本 v1.0

**状态**: ✅ 已完成
**版本**: v1.0.0
**更新日期**: 2026-01-16
**基线备份**: `backups/mysql/fitness_app_baseline_v1_20260116.sql`

---

## 📊 数据库概览

| 指标 | 数值 |
|------|------|
| 总表数 | 35 |
| 核心数据表 | 6 |
| 用户数据表 | 12 |
| 业务数据表 | 10 |
| 系统表 | 7 |
| 基线备份大小 | 11.4 MB |

---

## 🗂️ 表分类

### 1. 核心数据表（有默认数据）

| 表名 | 记录数 | 说明 | Seeder |
|------|--------|------|--------|
| `exercises` | 1,790 | 动作库 | ExercisesV2Importer |
| `exercise_v2_media` | 21,480 | 动作媒体文件 | ExercisesV2Importer |
| `foods` | 1,851 | 食物库 | import_foods_to_mysql.php |
| `memberships` | 9 | 会员套餐配置 | MembershipSeeder |
| `faqs` | 15 | 常见问题 | FaqSeeder |
| `chinese_holidays` | 9 | 中国节假日 | 迁移文件内置 |

### 2. 用户数据表（运行时生成）

| 表名 | 说明 | 关联 |
|------|------|------|
| `users` | 用户账号 | 核心用户表 |
| `user_profiles` | 用户档案 | users.id |
| `user_memberships` | 用户会员关系 | users.id, memberships.id |
| `user_credits` | 用户积分 | users.id |
| `user_favorite_exercises` | 收藏动作 | users.id, exercises.id |
| `social_accounts` | 第三方登录 | users.id |
| `personal_access_tokens` | API令牌 | users.id |
| `password_reset_tokens` | 密码重置 | users.email |

### 3. 训练数据表（运行时生成）

| 表名 | 说明 | 关联 |
|------|------|------|
| `training_plans` | 训练计划 | users.id |
| `training_plan_exercises` | 计划动作关联 | training_plans.id |
| `training_sessions` | 训练会话 | training_plans.id |
| `training_records` | 训练记录 | training_sessions.id |
| `training_logs` | 训练日志 | users.id |
| `training_progress` | 训练进度 | users.id |
| `personal_bests` | 个人最佳 | users.id |
| `fitness_goals` | 健身目标 | users.id |
| `progress_records` | 进度记录 | users.id |

### 4. AI对话数据表（运行时生成）

| 表名 | 说明 | 关联 |
|------|------|------|
| `chat_sessions` | 对话会话 | users.id |
| `chat_topics` | 对话主题 | - |

### 5. 业务数据表（运行时生成）

| 表名 | 说明 | 关联 |
|------|------|------|
| `orders` | 订单 | users.id |
| `membership_orders` | 会员订单 | users.id |
| `credit_logs` | 积分日志 | users.id |
| `referrals` | 推荐关系 | users.id |
| `feedbacks` | 用户反馈 | users.id |
| `complaints` | 投诉 | users.id |
| `expert_reviews` | 专家评审 | - |
| `usage_stats` | 使用统计 | - |

### 6. 系统表（Laravel框架）

| 表名 | 说明 |
|------|------|
| `migrations` | 迁移记录 |
| `failed_jobs` | 失败任务 |

---

## 🔑 核心表结构

### exercises 表（动作库）

```sql
-- 54个字段，双语支持
id, name_en, name_zh, slug, description_en, description_zh,
primary_muscle_en, primary_muscle_zh, all_muscles_zh,
equipment_en, equipment_zh, difficulty_en, difficulty_zh,
force_en, force_zh, mechanic_en, mechanic_zh,
grips_en, grips_zh, correct_steps_en, correct_steps_zh,
smart_tags, rep_range, set_range, rest_period, intensity_percentage,
safety_level, safety_pre_check, equipment_risks,
kinetic_chain_type, technique_checkpoints, rom_requirements,
key_nutrients, recommended_foods, nutrition_timing,
progression_options, regression_options, categories,
data_source, source_reference, license_type, original_source,
last_verified_at, verified_by, rating, view_count,
variation_of, variations, joints,
body_map_images, body_map_images_local,
muscles_primary_en, muscles_primary_zh,
muscles_secondary_en, muscles_secondary_zh,
created_at, updated_at
```

### foods 表（食物库）

```sql
-- 39个字段，营养成分完整
id, food_code, name, category, subcategory, edible,
water, energy_kcal, energy_kj, protein, fat, carbohydrate,
dietary_fiber, cholesterol, ash,
vitamin_a, carotene, retinol, thiamin, riboflavin, niacin,
vitamin_c, vitamin_e_total,
calcium, phosphorus, potassium, sodium, magnesium,
iron, zinc, selenium, copper, manganese,
remark, gi_value, price_level, view_count,
created_at, updated_at
```

### memberships 表（会员套餐）

```sql
-- 19个字段
id, name, slug, tier, price, original_price, duration_days,
max_training_plans, unlock_all_exercises, ai_recommendation,
data_analysis, coach_service, description, features, limits,
is_first_purchase, sort_order, is_active,
created_at, updated_at
```

---

## 🔄 数据恢复流程

### 完整恢复（从基线备份）

```bash
# 1. 停止应用服务
docker stop fitness_php_v2

# 2. 恢复数据库
docker exec -i fitness_mysql mysql -u root -proot_password_2025 fitness_app < backups/mysql/fitness_app_baseline_v1_20260116.sql

# 3. 重启服务
docker start fitness_php_v2
```

### 仅恢复核心数据

```bash
# 恢复动作库
docker exec fitness_php_v2 php artisan db:seed --class=ExercisesV2Importer --force

# 恢复食物库
docker exec fitness_php_v2 php /var/www/html/scripts/import_foods_to_mysql.php

# 恢复会员配置
docker exec fitness_php_v2 php artisan db:seed --class=MembershipSeeder --force

# 恢复FAQ
docker exec fitness_php_v2 php artisan db:seed --class=FaqSeeder --force
```

---

## ⚠️ 注意事项

1. **禁止使用的命令**：
   - `migrate:fresh` - 会删除所有表和数据
   - `migrate:refresh` - 会回滚并重新运行所有迁移
   - `db:wipe` - 清空整个数据库

2. **表结构变更**：
   - exercises表在2026-01-04重建为双语结构
   - 旧Seeder `OptimizedExercisesV2Importer` 已不兼容
   - 使用新Seeder `ExercisesV2Importer`

3. **数据同步到生产**：
   - 先在本地测试
   - 备份生产数据
   - 使用mysqldump导出/导入

---

**维护者**: 薛小川
**最后更新**: 2026-01-16
