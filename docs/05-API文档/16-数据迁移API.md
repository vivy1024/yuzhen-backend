# 数据迁移API文档

**状态**: ⚠️ 临时文档（生产环境使用后应删除）
**版本**: v1.0.0
**更新日期**: 2026-01-17

---

## ⚠️ 重要警告

**本文档描述的API端点仅用于临时数据迁移，不应长期保留在生产环境中。**

- ❌ **不要在生产环境长期保留这些端点**
- ❌ **不要在没有备份的情况下执行迁移**
- ❌ **不要在高峰期执行数据迁移**
- ✅ **迁移完成后立即删除相关代码**
- ✅ **迁移前必须备份数据库**
- ✅ **在测试环境充分验证后再执行**

---

## 📋 概述

数据迁移API提供了一组临时端点，用于在数据库结构变更时迁移历史数据。当前主要用于肌肉字段的数据迁移，将单值字段（`primary_muscle_zh`）迁移到JSON数组字段（`muscles_primary_zh`）。

### 核心特性

- ✅ **预览模式** - 在执行前预览迁移影响范围
- ✅ **事务保护** - 使用数据库事务确保数据一致性
- ✅ **验证机制** - 迁移后自动验证数据完整性
- ✅ **抽样检查** - 提供样本数据供人工验证
- ✅ **统计报告** - 详细的迁移前后对比统计

### 迁移场景

**当前支持的迁移**:
1. **肌肉字段迁移** - `primary_muscle_zh` → `muscles_primary_zh`（JSON数组）
2. **肌肉字段迁移** - `primary_muscle_en` → `muscles_primary_en`（JSON数组）
3. **全部肌肉填充** - 填充 `all_muscles_zh` 字段

---

## 🔧 技术实现

### 1. 迁移流程设计

```
预览阶段（GET /api/migrate/muscles/preview）
    ↓
人工审核统计数据
    ↓
执行迁移（POST /api/migrate/muscles/execute）
    ↓
验证结果（GET /api/migrate/muscles/verify）
    ↓
确认无误后删除迁移代码
```

### 2. 数据库事务保护

```php
DB::beginTransaction();
try {
    // 执行迁移SQL
    DB::statement("UPDATE exercises SET ...");
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    return error_response($e->getMessage());
}
```

### 3. 迁移SQL逻辑

**主肌肉字段迁移**:
```sql
UPDATE exercises 
SET muscles_primary_zh = JSON_ARRAY(primary_muscle_zh)
WHERE primary_muscle_zh IS NOT NULL 
AND primary_muscle_zh != ''
AND (muscles_primary_zh IS NULL OR JSON_LENGTH(muscles_primary_zh) = 0)
```

**全部肌肉字段填充**:
```sql
UPDATE exercises 
SET all_muscles_zh = JSON_ARRAY(primary_muscle_zh)
WHERE primary_muscle_zh IS NOT NULL 
AND primary_muscle_zh != ''
AND (all_muscles_zh IS NULL OR JSON_LENGTH(all_muscles_zh) = 0)
```

---

## 📡 API端点

### 1. 预览肌肉字段迁移

**端点**: `GET /api/migrate/muscles/preview`

**描述**: 预览肌肉字段迁移的影响范围，不会修改任何数据。用于在执行迁移前评估迁移规模和验证迁移逻辑。

**请求参数**: 无

**成功响应** (200):
```json
{
  "code": 200,
  "msg": "预览成功",
  "data": {
    "mode": "preview",
    "stats": {
      "total": 1790,
      "has_primary_muscle_zh": 1790,
      "has_muscles_primary_zh": 0,
      "needs_migration": 1790
    },
    "msg": "这是预览模式，不会修改数据",
    "next_step": "如果统计信息正确，请调用 POST /api/migrate/muscles/execute 执行迁移"
  }
}
```

**响应字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| mode | string | 固定值 "preview"，表示预览模式 |
| stats.total | integer | 动作总数 |
| stats.has_primary_muscle_zh | integer | 有 primary_muscle_zh 数据的记录数 |
| stats.has_muscles_primary_zh | integer | 已有 muscles_primary_zh 数据的记录数 |
| stats.needs_migration | integer | 需要迁移的记录数 |
| message | string | 提示信息 |
| next_step | string | 下一步操作建议 |

**使用场景**:
- 迁移前评估影响范围
- 验证迁移逻辑是否正确
- 确认需要迁移的数据量
- 向团队展示迁移计划

**注意事项**:
- ✅ 可以多次调用，不会影响数据
- ✅ 建议在测试环境和生产环境都执行预览
- ✅ 如果 `needs_migration` 为 0，说明已经迁移完成

---

### 2. 执行肌肉字段迁移

**端点**: `POST /api/migrate/muscles/execute`

**描述**: 执行肌肉字段的数据迁移，将单值字段转换为JSON数组字段。使用数据库事务保护，失败时自动回滚。

**请求参数**: 无

**成功响应** (200):
```json
{
  "code": 200,
  "msg": "✅ 肌肉字段迁移成功",
  "data": {
    "before": {
      "total": 1790,
      "has_primary_muscle_zh": 1790,
      "has_muscles_primary_zh": 0
    },
    "after": {
      "total": 1790,
      "has_primary_muscle_zh": 1790,
      "has_muscles_primary_zh": 1790
    },
    "sample": {
      "id": 1,
      "name_zh": "杠铃卧推",
      "primary_muscle_zh": "胸大肌",
      "muscles_primary_zh": "[\"胸大肌\"]"
    },
    "msg": "迁移成功完成"
  }
}
```

**失败响应** (500):
```json
{
  "code": 500,
  "msg": "迁移失败",
  "error": "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'primary_muscle_zh'"
}
```

**响应字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| before | object | 迁移前的统计数据 |
| after | object | 迁移后的统计数据 |
| sample | object | 抽样检查的一条记录 |
| message | string | 迁移结果描述 |

**迁移内容**:
1. **primary_muscle_zh → muscles_primary_zh**
   - 将单值转换为JSON数组
   - 仅迁移非空且未迁移的记录

2. **primary_muscle_en → muscles_primary_en**
   - 将单值转换为JSON数组
   - 仅迁移非空且未迁移的记录

3. **填充 all_muscles_zh**
   - 使用 primary_muscle_zh 填充
   - 仅填充空值记录

**使用场景**:
- 数据库结构变更后的数据迁移
- 字段类型转换（单值 → JSON数组）
- 历史数据补全

**注意事项**:
- ⚠️ **执行前必须备份数据库**
- ⚠️ **建议在低峰期执行**
- ⚠️ **执行后立即验证结果**
- ⚠️ **失败时会自动回滚，但仍需检查数据**
- ⚠️ **只能执行一次，重复执行不会重复迁移**

---

### 3. 验证迁移结果

**端点**: `GET /api/migrate/muscles/verify`

**描述**: 验证肌肉字段迁移是否成功完成，检查是否还有未迁移的记录，并提供抽样数据供人工验证。

**请求参数**: 无

**成功响应** (200):
```json
{
  "code": 200,
  "msg": "验证完成",
  "data": {
    "code": 200,
    "stats": {
      "total": 1790,
      "has_primary_muscle_zh": 1790,
      "has_muscles_primary_zh": 1790,
      "needs_migration": 0
    },
    "samples": [
      {
        "id": 1,
        "name_zh": "杠铃卧推",
        "primary_muscle_zh": "胸大肌",
        "muscles_primary_zh": "[\"胸大肌\"]"
      },
      {
        "id": 2,
        "name_zh": "哑铃卧推",
        "primary_muscle_zh": "胸大肌",
        "muscles_primary_zh": "[\"胸大肌\"]"
      },
      {
        "id": 3,
        "name_zh": "杠铃深蹲",
        "primary_muscle_zh": "股四头肌",
        "muscles_primary_zh": "[\"股四头肌\"]"
      },
      {
        "id": 4,
        "name_zh": "硬拉",
        "primary_muscle_zh": "竖脊肌",
        "muscles_primary_zh": "[\"竖脊肌\"]"
      },
      {
        "id": 5,
        "name_zh": "引体向上",
        "primary_muscle_zh": "背阔肌",
        "muscles_primary_zh": "[\"背阔肌\"]"
      }
    ],
    "msg": "所有记录已成功迁移"
  }
}
```

**部分迁移失败响应** (200):
```json
{
  "code": 200,
  "msg": "验证完成",
  "data": {
    "code": 400,
    "stats": {
      "total": 1790,
      "has_primary_muscle_zh": 1790,
      "has_muscles_primary_zh": 1500,
      "needs_migration": 290
    },
    "samples": [...],
    "msg": "仍有 290 条记录未迁移"
  }
}
```

**响应字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| success | boolean | 是否全部迁移成功 |
| stats | object | 迁移统计数据 |
| samples | array | 5条抽样记录 |
| message | string | 验证结果描述 |

**使用场景**:
- 迁移后验证数据完整性
- 人工抽查迁移质量
- 确认是否需要重新迁移
- 生成迁移报告

**注意事项**:
- ✅ 可以多次调用，不会影响数据
- ✅ 建议迁移后立即验证
- ✅ 如果 `success` 为 false，需要排查原因
- ✅ 抽样数据应人工检查格式是否正确

---

## 🔄 完整迁移流程

### 步骤1：备份数据库

```bash
# 使用项目提供的备份脚本
cd yuzhen-backend
scripts\backup_mysql.bat

# 或手动备份
docker exec fitness_mysql mysqldump -u root -p fitness_app > backup_before_migration.sql
```

### 步骤2：在测试环境预览

```bash
# 调用预览接口
curl http://localhost:8000/api/migrate/muscles/preview

# 检查返回的统计数据
# - total: 总记录数
# - needs_migration: 需要迁移的记录数
```

### 步骤3：在测试环境执行迁移

```bash
# 执行迁移
curl -X POST http://localhost:8000/api/migrate/muscles/execute

# 检查返回结果
# - before/after: 迁移前后对比
# - sample: 抽样数据
```

### 步骤4：验证迁移结果

```bash
# 验证迁移
curl http://localhost:8000/api/migrate/muscles/verify

# 检查验证结果
# - success: 是否全部成功
# - needs_migration: 是否还有未迁移记录
# - samples: 人工检查抽样数据
```

### 步骤5：在生产环境执行

```bash
# 1. 备份生产数据库
scripts\backup_mysql.bat

# 2. 预览生产环境迁移
curl https://api.yuzhen-fitness.cn/api/migrate/muscles/preview

# 3. 确认无误后执行迁移
curl -X POST https://api.yuzhen-fitness.cn/api/migrate/muscles/execute

# 4. 验证迁移结果
curl https://api.yuzhen-fitness.cn/api/migrate/muscles/verify

# 5. 人工抽查数据
# 登录phpMyAdmin检查几条记录
```

### 步骤6：删除迁移代码

```php
// 从 routes/api.php 中删除以下代码块
Route::prefix('migrate')->group(function () {
    // ... 删除所有迁移路由
});
```

```bash
# 提交代码变更
git add routes/api.php
git commit -m "chore: 删除临时数据迁移API"
git push origin main
```

---

## 🔐 安全机制

### 1. 事务保护

所有迁移操作都使用数据库事务，失败时自动回滚：

```php
DB::beginTransaction();
try {
    // 执行迁移
    DB::statement("UPDATE ...");
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### 2. 幂等性保护

迁移SQL包含条件判断，避免重复迁移：

```sql
WHERE (muscles_primary_zh IS NULL OR JSON_LENGTH(muscles_primary_zh) = 0)
```

这确保：
- ✅ 已迁移的记录不会被重复处理
- ✅ 可以安全地多次执行迁移
- ✅ 部分失败后可以重新执行

### 3. 数据验证

迁移前后都进行数据统计，确保数据完整性：

```php
$beforeStats = [
    'total' => DB::table('exercises')->count(),
    'has_primary_muscle_zh' => ...,
    'has_muscles_primary_zh' => ...,
];

// 执行迁移

$afterStats = [...]; // 再次统计

// 对比 before 和 after
```

### 4. 抽样检查

提供样本数据供人工验证：

```php
$sample = DB::table('exercises')
    ->whereNotNull('muscles_primary_zh')
    ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
    ->first(['id', 'name_zh', 'primary_muscle_zh', 'muscles_primary_zh']);
```

---

## 🧪 测试验证

### 1. 本地测试环境验证

```bash
# 1. 预览迁移
curl http://localhost:8000/api/migrate/muscles/preview

# 预期响应
{
  "code": 200,
  "msg": "预览成功",
  "data": {
    "stats": {
      "total": 1790,
      "needs_migration": 1790
    }
  }
}

# 2. 执行迁移
curl -X POST http://localhost:8000/api/migrate/muscles/execute

# 预期响应
{
  "code": 200,
  "msg": "✅ 肌肉字段迁移成功",
  "data": {
    "before": { "has_muscles_primary_zh": 0 },
    "after": { "has_muscles_primary_zh": 1790 }
  }
}

# 3. 验证结果
curl http://localhost:8000/api/migrate/muscles/verify

# 预期响应
{
  "code": 200,
  "msg": "验证完成",
  "data": {
    "code": 200,
    "stats": { "needs_migration": 0 }
  }
}
```

### 2. 数据库直接验证

```sql
-- 检查迁移前后的数据
SELECT 
    id,
    name_zh,
    primary_muscle_zh,
    muscles_primary_zh,
    JSON_LENGTH(muscles_primary_zh) as array_length
FROM exercises
WHERE primary_muscle_zh IS NOT NULL
LIMIT 10;

-- 预期结果
-- muscles_primary_zh 应该是 JSON 数组格式
-- array_length 应该 >= 1
```

### 3. 回滚测试

```bash
# 1. 备份数据
scripts\backup_mysql.bat

# 2. 执行迁移
curl -X POST http://localhost:8000/api/migrate/muscles/execute

# 3. 模拟失败场景（修改代码抛出异常）
# 验证是否自动回滚

# 4. 恢复备份
scripts\restore_mysql.bat backups\mysql\fitness_app_xxx.sql
```

---

## 📊 迁移统计示例

### 迁移前统计

```json
{
  "total": 1790,
  "has_primary_muscle_zh": 1790,
  "has_muscles_primary_zh": 0,
  "needs_migration": 1790
}
```

**解读**:
- 总共1790条动作记录
- 所有记录都有 `primary_muscle_zh` 数据
- 没有记录有 `muscles_primary_zh` 数据
- 需要迁移1790条记录

### 迁移后统计

```json
{
  "total": 1790,
  "has_primary_muscle_zh": 1790,
  "has_muscles_primary_zh": 1790,
  "needs_migration": 0
}
```

**解读**:
- 总记录数不变
- `primary_muscle_zh` 数据保留
- `muscles_primary_zh` 已全部填充
- 没有需要迁移的记录

### 抽样数据示例

```json
{
  "id": 1,
  "name_zh": "杠铃卧推",
  "primary_muscle_zh": "胸大肌",
  "muscles_primary_zh": "[\"胸大肌\"]"
}
```

**验证要点**:
- ✅ `muscles_primary_zh` 是有效的JSON数组
- ✅ 数组包含 `primary_muscle_zh` 的值
- ✅ 中文字符正确编码
- ✅ 没有多余的空格或特殊字符

---

## ⚠️ 常见问题

### Q1: 迁移失败如何处理？

**A**: 迁移使用事务保护，失败会自动回滚。如果失败：

1. 检查错误信息
2. 确认数据库连接正常
3. 验证字段是否存在
4. 修复问题后重新执行

### Q2: 可以重复执行迁移吗？

**A**: 可以。迁移SQL包含幂等性保护，已迁移的记录不会被重复处理。

### Q3: 迁移会影响原有数据吗？

**A**: 不会。迁移只是将 `primary_muscle_zh` 的值复制到 `muscles_primary_zh`，不会修改或删除原有数据。

### Q4: 迁移需要多长时间？

**A**: 对于1790条记录，通常在1-2秒内完成。具体时间取决于数据库性能。

### Q5: 如何回滚迁移？

**A**: 
1. 使用备份恢复：`scripts\restore_mysql.bat backup.sql`
2. 或手动清空字段：`UPDATE exercises SET muscles_primary_zh = NULL`

### Q6: 生产环境迁移需要停机吗？

**A**: 不需要。迁移使用事务，执行时间很短，不会影响正常服务。但建议在低峰期执行。

### Q7: 迁移后如何删除迁移代码？

**A**: 从 `routes/api.php` 中删除 `Route::prefix('migrate')` 代码块，然后提交到Git。

---

## 📝 迁移检查清单

### 迁移前检查

- [ ] 已备份数据库
- [ ] 已在测试环境验证
- [ ] 已确认迁移逻辑正确
- [ ] 已通知相关人员
- [ ] 已选择低峰期时间

### 迁移中检查

- [ ] 预览统计数据正确
- [ ] 执行迁移成功
- [ ] 验证结果通过
- [ ] 抽样数据格式正确
- [ ] 没有错误日志

### 迁移后检查

- [ ] 数据库记录数不变
- [ ] 所有记录已迁移
- [ ] 前端功能正常
- [ ] API响应正常
- [ ] 已删除迁移代码
- [ ] 已更新文档

---

## 🎯 后续清理

### 1. 删除迁移路由

```php
// 从 routes/api.php 删除
Route::prefix('migrate')->group(function () {
    // ... 删除所有内容
});
```

### 2. 删除本文档

```bash
# 迁移完成后删除本文档
rm yuzhen-backend/docs/05-API文档/06-数据迁移API.md
```

### 3. 更新CHANGELOG

```markdown
## [2.1.0] - 2026-01-17

### Changed
- 完成肌肉字段数据迁移（primary_muscle_zh → muscles_primary_zh）
- 删除临时数据迁移API端点
```

### 4. Git提交

```bash
git add routes/api.php
git add yuzhen-backend/docs/05-API文档/
git add CHANGELOG.md
git commit -m "chore: 完成数据迁移并清理临时代码"
git push origin main
```

---

## 📝 相关文档

- **API接口规范总览**: `yuzhen-backend/docs/05-API文档/01-API接口规范总览.md`
- **MySQL数据库结构**: `yuzhen-backend/docs/02-核心架构/02-数据层/03-MySQL数据库完整结构文档.md`
- **Zeabur部署指南**: `yuzhen-backend/docs/06-部署运维/Zeabur部署指南.md`

---

**维护者**: 薛小川
**最后更新**: 2026-01-17
**文档状态**: ⚠️ 临时文档，迁移完成后应删除

