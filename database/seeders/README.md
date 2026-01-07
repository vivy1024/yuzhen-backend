# Seeders 使用指南

**版本**: v1.0.0  
**更新日期**: 2025-11-04  

---

## 📋 概述

本目录包含BUILD_BODY v2.0的所有数据种子文件（Seeders），用于初始化数据库数据。

---

## 📂 Seeder清单

| Seeder | 用途 | 环境 | 数据量 |
|--------|------|------|--------|
| `DatabaseSeeder.php` | **主Seeder**，调用其他所有seeders | 全部 | - |
| `MembershipSeeder.php` | 导入3个会员等级配置 | 全部 | 3条 |
| `OptimizedExercisesV2Importer.php` | **核心Seeder**，导入动作库数据 | 全部 | 1603+19236 |
| `UserSeeder.php` | 创建测试用户（开发专用） | 仅开发 | 3个用户 |

---

## 🚀 使用方法

### 1. 完整初始化（推荐）

初始化所有必要数据：

```bash
# Docker环境
docker exec fitness_php_v2 php artisan db:seed --force

# 本地环境
php artisan db:seed
```

**执行顺序**：
1. `MembershipSeeder` - 会员等级
2. `OptimizedExercisesV2Importer` - 动作库
3. ✅ 完成

---

### 2. 单独运行特定Seeder

```bash
# 仅导入会员等级
docker exec fitness_php_v2 php artisan db:seed --class=MembershipSeeder --force

# 仅导入动作库
docker exec fitness_php_v2 php artisan db:seed --class=OptimizedExercisesV2Importer --force

# 仅创建测试用户（开发环境）
docker exec fitness_php_v2 php artisan db:seed --class=UserSeeder --force
```

---

### 3. 完全重置并重新初始化

⚠️ **警告**：此操作会清空所有数据！

```bash
# Docker环境
docker exec fitness_php_v2 php artisan migrate:fresh --seed --force

# 本地环境
php artisan migrate:fresh --seed
```

---

## 📝 各Seeder详细说明

### DatabaseSeeder.php（主控制器）

**作用**：协调其他seeders的执行顺序

**特点**：
- 自动调用所有必要的seeders
- 控制执行顺序
- 显示整体进度

**修改指南**：
添加新的seeder时，在`$this->call([])`数组中添加类名。

---

### MembershipSeeder.php（会员等级）

**作用**：初始化3个会员等级配置

**数据结构**：
```php
[
    'name' => '免费版',
    'slug' => 'free',
    'tier' => 'free',           // 等级标识（新增）
    'price' => 0.00,
    'features' => json_encode([...]),  // 功能列表（新增）
    'limits' => json_encode([...]),    // 限制配置（新增）
    // ...其他字段
]
```

**导入的数据**：
1. **免费版** (free)
   - 价格：¥0/年
   - 特点：基础功能，永久免费
   - 限制：每日3次AI建议

2. **暖心会员** (warmheart)
   - 价格：¥3/月
   - 特点：数据分析，每日10次AI建议
   - 限制：每周3次AI计划

3. **能量会员** (energy)
   - 价格：¥8/月
   - 特点：无限AI建议，专属服务
   - 限制：无限制

**与新表结构对应**：
- ✅ 包含`tier`字段
- ✅ 包含`features` JSON字段
- ✅ 包含`limits` JSON字段

---

### OptimizedExercisesV2Importer.php（动作库导入）

**作用**：导入1603个动作 + 19236个媒体文件

**数据来源**：`storage/app/public/exercises_v2/`

**导入内容**：
- ✅ 动作基础信息（name, name_zh, slug）
- ✅ 动作描述（description, description_zh）
- ✅ 动作步骤（correct_steps, correct_steps_zh）
- ✅ 肌群信息（primary_muscle, secondary_muscles）
- ✅ 器械难度（equipment, difficulty）
- ✅ 分类标签（categories, smart_tags, grips）
- ✅ 媒体资源（图片、视频、缩略图）

**特性**：
1. **批量导入**：每50条一批，性能优化
2. **进度显示**：实时显示导入进度
3. **错误处理**：记录失败项，不中断整体流程
4. **JSON字段**：自动填充correct_steps_zh, secondary_muscles, smart_tags

**导入流程**：
```
1. 验证环境（数据库连接、表存在性）
   ↓
2. 准备数据库（清空exercises和exercise_v2_media表）
   ↓
3. 扫描JSON文件（1603个）
   ↓
4. 批量导入动作数据
   ↓
5. 导入媒体资源（19236个文件）
   ↓
6. 显示统计报告
```

**输出示例**：
```
╔════════════════════════════════════════════════════════════════╗
║  Exercise V2 优化导入器 - 开发环境版                           ║
╚════════════════════════════════════════════════════════════════╝

📋 验证运行环境...
✅ 数据库连接成功
✅ 环境验证完成

🗄️ 准备数据库表...
✅ 清空表: exercise_v2_media
✅ 清空表: exercises

🏋️ 开始导入exercises数据...
📊 发现 1603 个练习文件

✅ 进度: 50/1603
✅ 进度: 100/1603
...
✅ 进度: 1603/1603

✅ exercises数据导入完成

╔════════════════════════════════════════════════════════════════╗
║  导入完成统计                                                  ║
╚════════════════════════════════════════════════════════════════╝

📊 动作统计:
   - 总数: 1603
   - 成功: 1603
   - 失败: 0

📸 媒体文件: 19236 个
```

---

### UserSeeder.php（测试用户）

**作用**：创建开发测试用户（⚠️ 仅开发环境）

**安全特性**：
- 生产环境自动跳过
- 统一测试密码：`password123`
- 包含完整的用户档案数据

**创建的用户**：

| 用户 | 邮箱 | 会员等级 | 特点 |
|------|------|----------|------|
| 测试管理员 | admin@buildxbody.com | energy | 管理员权限，完整档案 |
| 健身小白 | beginner@test.com | free | 初学者，未完成引导 |
| 健身达人 | advanced@test.com | warmheart | 中级用户，完整档案 |

**每个用户包含**：
- ✅ 基础用户信息（users表）
- ✅ 完整用户档案（user_profiles表，JSON结构）
- ✅ 会员等级配置
- ✅ 偏好设置和收藏

**档案数据示例**：
```json
{
  "basic_info": {
    "nickname": "健身小白",
    "age": 22,
    "gender": "male",
    "height": 172,
    "weight": 65,
    "body_fat_percentage": 20,
    "fitness_level": "beginner"
  },
  "fitness_goals": {
    "primary_goals": ["增肌", "减脂"],
    "target_weight": 70,
    "training_split": "全身训练"
  },
  "training_preferences": {
    "available_equipment": ["哑铃", "徒手"],
    "training_location": "家里"
  }
}
```

**使用场景**：
- 🧪 功能测试
- 🔍 API调试
- 🎨 前端UI展示
- 📝 文档示例

---

## 🔄 执行顺序建议

### 全新安装：
```bash
1. php artisan migrate --force        # 创建表结构
2. php artisan db:seed --force        # 导入数据
3. php artisan db:seed --class=UserSeeder --force  # 创建测试用户（可选）
```

### 仅更新动作库：
```bash
docker exec fitness_php_v2 php artisan db:seed --class=OptimizedExercisesV2Importer --force
```

### 仅更新会员等级：
```bash
docker exec fitness_php_v2 php artisan db:seed --class=MembershipSeeder --force
```

---

## 📊 数据统计

| 项目 | 数量 | 说明 |
|------|------|------|
| **会员等级** | 3个 | free, warmheart, energy |
| **动作数据** | 1,603条 | 完整的动作信息 |
| **媒体文件** | 19,236个 | 图片、视频、缩略图 |
| **测试用户** | 3个 | 管理员、小白、达人（仅开发） |
| **JSON字段** | 完整 | correct_steps_zh, secondary_muscles, smart_tags |

---

## ⚠️ 注意事项

### 1. 数据覆盖警告

运行seeders会**清空相关表**的数据！

- `MembershipSeeder`：清空`memberships`表
- `OptimizedExercisesV2Importer`：清空`exercises`和`exercise_v2_media`表
- `UserSeeder`：不清空，直接插入

### 2. 生产环境安全

- `UserSeeder`会自动跳过生产环境
- 其他seeders在生产环境需谨慎使用
- 建议生产环境仅运行`MembershipSeeder`

### 3. 性能考虑

- `OptimizedExercisesV2Importer`导入较慢（约1-2分钟）
- 使用批量插入优化性能
- 大量数据时建议使用队列

### 4. 依赖关系

seeders的执行顺序很重要：
1. **MembershipSeeder** 必须先运行（用户需要引用会员等级）
2. **OptimizedExercisesV2Importer** 可独立运行
3. **UserSeeder** 依赖MembershipSeeder

---

## 🔧 故障排除

### 问题1：找不到exercises_v2目录

```bash
错误: exercises_v2目录不存在
解决: 确保 storage/app/public/exercises_v2 目录存在
```

### 问题2：数据库连接失败

```bash
错误: 数据库连接失败
解决: 检查 .env 文件的数据库配置
```

### 问题3：表不存在

```bash
错误: 缺少必要的表: exercises
解决: 先运行迁移: php artisan migrate --force
```

### 问题4：UserSeeder在生产环境被跳过

```bash
警告: 生产环境不应运行此Seeder
说明: 这是安全特性，生产环境不应有测试用户
```

---

## 📝 开发指南

### 添加新的Seeder

1. 创建Seeder文件：
```bash
php artisan make:seeder YourSeeder
```

2. 实现`run()`方法

3. 在`DatabaseSeeder.php`中注册：
```php
$this->call([
    MembershipSeeder::class,
    OptimizedExercisesV2Importer::class,
    YourSeeder::class,  // 新增
]);
```

### 最佳实践

1. **事务处理**：大量插入使用事务
2. **错误处理**：捕获异常，记录日志
3. **进度显示**：长时间操作显示进度
4. **环境检查**：生产敏感操作检查环境
5. **数据验证**：插入前验证数据完整性

---

## 📚 相关文档

- [数据库结构总览](../../docs/03-代码参考/00-数据库结构总览.md)
- [迁移文件说明](../migrations/README.md)
- [用户档案类型定义](../../../yuzhen_fitness_v2/src/types/user-profile.ts)

---

**维护者**: BUILD_BODY Team  
**最后审查**: 2025-11-04

<div align="center">
<strong>📊 数据初始化 · 🧪 测试友好 · 🔒 生产安全</strong>
</div>

































