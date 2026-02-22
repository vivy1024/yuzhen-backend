# yuzhen-backend 构建日志

> 内部构建号，不对外发布。产品版本见根仓库 `CHANGELOG.md`。
> 历史版本（v2.127.0 及之前）已归档至 `CHANGELOG-legacy.md`。

---

## #12 (refactor) CreditService.php Trait 拆分 — 2026-02-23

对应产品版本：v1.1.0（内部重构，产品版本不动）

- 777 行 → 3 Trait + 1 facade 拆分（`Credit/` 子目录）
- `CreditCalculatorTrait`：积分计算 + 会员等级查询 + 升级提示
- `CreditQueryTrait`：余额查询 + 充足性检查 + 历史记录 + 系统统计
- `CreditMutationTrait`：记录消耗 + 配额重置 + 添加/扣减额度
- CreditService 保留常量定义 + `use` 三个 Trait
- 验证：203 tests passed / 737 assertions / 0 failed

---

## #11 (refactor) InternalChatController.php 拆分 — 2026-02-23

对应产品版本：v1.1.0（内部重构，产品版本不动）

- 916 行 → 3 Controller 拆分
- `ChatSessionController`：会话管理（save/feedback/history/personalization/count/fewshot）
- `ChatMessageController`：消息/话题管理（saveTopic/saveMessage/clearTopic）
- `ChatSearchController`：搜索（searchSimilarConversations + 关键词提取）
- 更新 `routes/internal.php` 路由指向新 Controller
- URL 路径不变，API 完全向后兼容
- 验证：203 tests passed / 0 failed

---

## #10 (feat) Web Push 推送通知系统 — 2026-02-22

- 安装 `minishlink/web-push` v10 PHP 库
- 创建 `push_subscriptions` 迁移（endpoint/p256dh/auth/reminder_time/is_active）
- 新增 `PushSubscription` Model
- 新增 `PushController`：subscribe / unsubscribe / updateReminderTime 三个端点
- 新增 `PushNotificationService`：sendToUser + sendTrainingReminders（按提醒时间匹配）
- 生成 VAPID 密钥对，配置到 `.env` + `config/services.php`
- PHPUnit `PushControllerTest` 5用例/12断言全通过
- 对应产品版本：v1.1.0

---

## #9 (docs) 用户自建计划文档 — 2026-02-22

- 新增 `docs/04-开发指南/07-用户自建计划设计.md`
- 新增 `docs/05-API文档/08-用户自建计划API.md`
- 对应产品版本：v1.1.0

---

## #8 (fix) Docker验证批次 — 迁移+测试修复 — 2026-02-22

- 运行6个Pending迁移：permission_tables标记已执行 + credit_transactions唯一索引 + 4个user-custom-plans迁移
- 修复 `TrainingPlanExercise` Exercise模型引用：`App\Models\Exercise` → `App\Modules\Exercise\Models\Exercise`
- 修复 `CreditServiceTest` / `InternalChatControllerTest` 中间件引用：`InternalApiMiddleware` → `InternalApiAuth`
- 修复 `InternalChatControllerTest` save_topic/save_message 数据类型：user_id 转字符串 + 使用真实topic_id
- PlanTemplateSeeder 验证：12个官方模板全部入库
- PHPUnit 全量验证：71用例/252断言全通过（UserPlan 8 + CreditUnit 45 + CreditFeature 5 + InternalChat 6 + AdminKpi 5 + Prometheus 2）
- 对应产品版本：v1.1.0（内部测试修复，产品版本不动）

---

## #7 (feat) 连续训练统计 + 成就徽章 — 2026-02-22

- 新增迁移：user_profiles 添加 streak_days / total_training_days / last_training_date 字段
- 更新 `UserProfile` Model：updateTrainingStreak() 连续天数计算 + getAchievements() 成就徽章
- 更新 `TrainingLogController.recordSession()`：训练记录提交后自动更新连续天数
- 更新 `UserProfileResource`：返回 streak_days / total_training_days / achievements 数据
- 成就定义：连续7/30/100/365天 + 累计10/50/200/500天（8枚徽章）
- 对应产品版本：v1.1.0

---

## #6 (feat) 用户自建训练/饮食计划 + 模板库 — 2026-02-22

- 扩展 `TrainingPlanController`：新增 `store`（手动创建）、`copy`（复制计划）方法
- 更新 `update` 方法支持 exercises sync（删除旧的+插入新的）
- 新增 `TrainingPlanExercise` Model + `day_of_week` 迁移（周视图排列）
- 新增 `UserNutritionPlan` Model + 迁移（饮食计划，关联 foods 表营养数据）
- 新增 `PlanTemplate` Model + 迁移 + `PlanTemplateSeeder`（12 个官方模板）
- 新增 `PlanTemplateController`：模板列表 + 从模板创建个人计划
- 新增 `UserPlanRequest` FormRequest（exercises + nutrition 嵌套验证）
- 新增 `UserPlanControllerTest.php`（8 个用例）
- 路由：`POST /plans`、`POST /plans/{id}/copy`、`GET /templates`、`POST /templates/{id}/use`
- 对应产品版本：v1.1.0

---

## #5 (feat) Prometheus /metrics 端点 + Feature 测试 — 2026-02-22

- 新增 `PrometheusMetricsController`：`/metrics` 端点暴露 HTTP 请求计数、MySQL 连接数、PHP 内存、应用查询量
- 新增 3 个 Feature 测试：CreditServiceTest(5)、InternalChatControllerTest(6)、AdminKpiControllerTest(5)
- prometheus.yml 添加 Laravel scrape target（job_name=laravel）
- 对应产品版本：v1.1.0

## #4 (chore) 路由双重加载修复 + 测试路由清理 — 2026-02-22

- 修复 4 个 ServiceProvider 路由双重注册（Exercise/Food/Training/User）
  - `loadRoutesFrom()` 与 `routes/api.php` 的 `require` 重复加载，导致 43 条无 `api/` 前缀的路由暴露
  - 路由总数 291 → 248，消除所有非 api 前缀的重复路由
- 删除 `routes/api_test.php`（遗留调试文件，暴露数据库配置信息）
- 对应产品版本：v1.1.0

## #3 (feat) 知识库 API + FormRequest 安全加固 — 2026-02-21

- 新增 KnowledgeController：5 个 API 端点（列表/详情/分类树/卡片/搜索）
- 新增 KnowledgeSearchRequest FormRequest 验证
- 新增 ChatTopicRequest / ChatHistoryRequest / SyncMessagesRequest FormRequest 类
- ChatTopicController 5 个方法从内联 validate 迁移到 FormRequest 类型注入
- 移除 update() 中冗余的 ValidationException catch（FormRequest 自动处理）
- 新增 `routes/modules/knowledge.php` 路由模块
- 对应产品版本：v1.1.0

## #2 (feat) 专业知识库数据模型 — 2026-02-21

- 新增 `knowledge_categories` 表 + Seeder（3 顶级分类 × 5 子分类 = 18 条）
- 新增 `knowledge_articles` 表（全文索引、JSON tags、难度等级）
- 新增 `knowledge_references` 表（书籍/论文/指南引用）
- 3 个 Eloquent Model：KnowledgeArticle / KnowledgeCategory / KnowledgeReference
- Docker 容器内迁移验证通过
- 对应产品版本：v1.1.0

## #1 (chore) MVP 基线 — 2026-02-21

- 从 legacy v2.127.0 冻结归档后的新起点
- Laravel PHP 后端，含会员体系、积分系统、Internal JWT 认证
- 对应产品版本：v1.0.0
