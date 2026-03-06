# yuzhen-backend 构建日志

> 内部构建号，不对外发布。产品版本见根仓库 `CHANGELOG.md`。
> 历史版本（v2.127.0 及之前）已归档至 `CHANGELOG-legacy.md`。

---

## #26 (feat) stats 接口扩展 — 今日/本周/本月/连续打卡 — 2026-03-06

- `TrainingLogController::stats()` 重构：返回 `today_count`/`week_count`/`month_count`/`streak_days`/`total_sessions`/`total_volume_kg`
- 新增 `calculateStreakDays()` 私有方法：从今天/昨天往前逐日检查连续完成记录
- 新增 `calculateTotalVolume()` 私有方法：遍历 `actual_exercises` JSON 计算总容量

---

## #25 (fix) 训练日历数据源统一 — 2026-03-06

- **数据源不一致修复**: 训练日历 `getTrainingCalendarData()` 从 `training_sessions` 改为 `training_logs`
  - `ProgressController` 的日历和统计方法全部改为查询 `training_logs` 表
  - 移除对 `TrainingSession` 和 `TrainingRecord` 模型的依赖
- **新增 migration**: `training_logs` 表添加 `status`(draft/in_progress/completed) + `completed_at` 列
  - 修复 `TrainingLogController::complete()` 的 status/completed_at 更新无效问题
- **TrainingLog 模型更新**: `$fillable` 和 `$casts` 添加 status + completed_at

---

## #24 (security) 安全审计修复 R2 — 2026-03-05

对应产品版本：v1.6.8

**P0 修复**：
- config/cors.php: `env('APP_ENV')` → `config('app.env')`（config:cache 后 CORS 白名单失效）
- JwtService.php: `config('auth.jwt_secret') ?: env('JWT_SECRET', '')` → `config('auth.jwt_secret', '')`（移除 env() 回退）

**P1 修复**：
- UserSettingsController::deleteAccount(): 软删除前清理 20 张关联表数据（个保法合规）
- training-record.php: 添加 `throttle:30,1` 速率限制
- admin.php: 路由组添加 `throttle:60,1` 速率限制

**P2 修复**：
- SecurityHeaders.php: CSP `script-src` 移除 `'unsafe-eval'`
- admin.php: 注释已完成的数据迁移路由（移除攻击面）

---

## #23 (feat) 协议同意记录 API — 2026-03-01

- 新增 `user_consent_records` 表（migration）：记录用户同意协议的时间、版本、IP、UA
- 新增 `UserConsentRecord` Model
- 新增 `ConsentController`：`POST /api/consent/record` + `GET /api/consent/latest`
- 新增 `routes/modules/consent.php`，注册到 `api.php`

## #22 (fix) 生产日志问题修复 R2 — 内部API补全 — 2026-03-01

对应产品版本：v1.6.6

- 新增 `InternalUsageController::check()` — `POST /api/internal/usage/check`（internal.api 认证），供 DAML-RAG 用量预检查
- 新增 `ChatMessageController::getTopic()` — `GET /api/internal/chat/topic/{topicId}`，供 DAML-RAG 对话历史加载
- `routes/internal.php`: 注册上述 2 个新路由

---

## #21 (fix) P2 端点安全加固 + env() 清理 — 2026-02-28

对应产品版本：v1.6.3

**未认证端点加固**：
- routes/modules/food.php: `/clear-cache` 添加 `jwt.auth` + `role:admin` 中间件
- routes/modules/help.php: `/faqs/{id}/feedback` 添加 `throttle:5,1` 限流

**env() 直接调用清理**：
- MCPToolsController: `env('MCO_BASE_URL')` → `config('services.mco.url')`
- MetricsProxyController: `env('PROMETHEUS_URL')` / `env('DAML_RAG_URL')` → `config('services.prometheus.url')` / `config('services.daml_rag.url')`
- MetricsProxyController: `env('LOKI_URL')` ×2 → `config('services.loki.url')`
- HealthCheckController::checkNeo4j(): `env('NEO4J_*')` fallback → `config('services.neo4j.*')`
- config/services.php: 新增 `mco` / `prometheus` / `loki` / `qdrant` / `neo4j` 配置块

---

## #20 (fix) 核心功能审计修复 — AI对话+训练计划 — 2026-02-28

对应产品版本：v1.6.2

**P0 安全漏洞（4个）**：
- training-record.php: 添加 jwt.auth 中间件，移除 URL 中 user_id 参数
- TrainingRecordController: 所有方法从 JWT 获取 user_id（不再信任请求体）
- routes/api.php: 注释旧版 Modules 训练路由（消除 IDOR 漏洞）
- AiProxyController::warmupStatus(): 添加 user_id 归属校验（403）
- ChatTopicController: client_id 去重查询限定 user_id 范围

**P1 功能修复（6个）**：
- AiProxyController: env() → config('services.daml_rag.url')（config:cache 兼容）
- config/services.php: 新增 daml_rag.url 配置项
- UserPlanRequest: goal 枚举扩展（新增 hypertrophy/fat_loss/strength 等8个值）
- TrainingPlanController::import(): 字段名双向兼容 duration_weeks/weeks + workouts_per_week/frequency
- TrainingLogController::recordSession(): 兼容前端 exercises 格式自动转换
- TrainingLogController::createFromPlan(): 优先从 planExercises 关联获取动作

**P2 功能缺陷（3个）**：
- TrainingRecord 模型: fillable/casts 对齐数据库 schema（session_id/rpe/rest_seconds）
- TrainingSession 模型: 状态值统一 in-progress → in_progress
- ChatTopicController::syncMessages(): 添加 DB::transaction 事务保护

**P3 性能优化（1个）**：
- ChatTopicController::sessions(): N+1 查询优化（批量预加载首条 user_query）

**测试更新**：
- TrainingRecordTest: 适配 JWT 认证 + 新路由路径 + 新增 401 测试

## #19 (fix) 认证系统审计修复 — 2026-02-28

对应产品版本：v1.6.1

- EmailService::login(): 修复不存在的 generateTokens() 调用（REQ-C1）
- EmailService::login(): 添加用户禁用状态检查（REQ-H4）
- AuthService::logout(): JWT黑名单机制替代Sanctum方式（REQ-C3）
- AuthService::login(): 登录失败添加审计日志（REQ-H7）
- JwtAuthenticate中间件: 添加JWT黑名单检查（REQ-C3）
- SmsService::loginWithSms(): 添加UserLoggedIn事件触发（REQ-H5）
- auth.php路由: 登录接口添加throttle:5,1限流（REQ-H1）

## #18 (fix) DYPNS短信验证码发送修复 — 2026-02-27

对应产品版本：v1.5.2

- AliyunDypnsClient.php: 修复 DYPNS API 响应解析
  - `requestId`/`bizId` 从 `$body->model` 获取（非顶层）
  - 添加 `templateParam` 参数（免资质通用模板仍需传递 code/min）
  - 新增 `biz.FREQUENCY` 等业务错误码友好提示
- checkSmsVerifyCode: 验证成功需 `$model->verifyResult === 'PASS'`

---

## #17 (fix) AI对话请求验证补全 — AiChatRequest 添加缺失字段 — 2026-02-26

对应产品版本：v1.5.0

- AiChatRequest.php: 添加 domain/template_id/persona_id/attachments 验证规则
- 修复前端发送的这4个字段被 Laravel FormRequest 静默丢弃的问题

---

## #16 (feat) 计算器卡片 — 7个PHP Calculator Service + API端点 + 单元测试 — 2026-02-25

对应产品版本：v1.5.0

- 新增7个纯静态Calculator Service（`app/Services/Calculator/`）：
  - TDEECalculator: Mifflin-St Jeor BMR + 活动系数 + 目标热量调整 + 三大营养素分配
  - FFMICalculator: BMI(中国标准) + FFMI + 标准化FFMI + 评级 + 自然潜力评估
  - OneRMCalculator: Epley + Brzycki 公式，支持批量计算
  - IntensityConverter: RPE ↔ RIR ↔ %1RM 三向转换
  - WeightRecommender: 基于1RM + 训练目标 + RPE → 推荐重量(2.5kg取整)
  - CarbCyclingCalculator: 高/中/低碳日分配，蛋白质恒定，脂肪补齐
  - MacroCalculator: balanced/body_weight/ratio 三种方法分配宏量营养素
- 新增 CalculatorController（7个POST端点）+ `Route::prefix('calculators')` 路由组
- 新增 rate limiting: 计算器端点 60次/分钟/IP
- ProgressRecord.calculateFFMI() 迁移为调用 FFMICalculator::calculate()
- 新增45个PHP单元测试（`tests/Unit/Services/Calculator/`），111个断言全部通过

---

## #15 (fix) 后端数据一致性修复 — 响应格式+废弃路由+SSE心跳 — 2026-02-25

对应产品版本：v1.5.0

- ChatTopicController: 6处 response()->json() 统一为 $this->success()（show/update/messages/storeMessage×2/syncMessages）
- routes/modules/training.php: @deprecated 注释 + DeprecatedRouteLogger 中间件记录旧路由调用
- DeprecatedRouteLogger（新建）: 通用废弃路由日志中间件，支持参数化替代路由
- Kernel.php: 注册 'deprecated' 中间件别名
- AiProxyController: cURL 新增 CURLOPT_LOW_SPEED_LIMIT/TIME 实现5分钟无数据超时断开

---

## #14 (feat) 前端 API 缺口修复 — 后端路由补全 — 2026-02-24

对应产品版本：v1.4.0

- training-plan.php: 新增 5 条路由（activate/start/export/progress-stats/training-logs）
- Api\TrainingPlanController: 新增 activate/start/export/progressStats/trainingLogs 方法
- training-log.php: 新增 2 条路由（complete/from-plan）
- TrainingLogController: 新增 complete/createFromPlan 方法
- UserSettingsController（新建）: getSettings/updateSettings/changePassword/deleteAccount/getVersion
- AvatarController（新建）: upload 头像上传（jpg/png/webp，max 2MB）
- user-settings.php（新建）: 6 条路由（settings/change-password/account/version/avatar）
- Migration: user_profiles 新增 preferences JSON 字段
- Migration: users 新增 soft deletes（deleted_at）
- User Model: 添加 SoftDeletes trait
- UserProfile Model: $fillable/$casts 添加 preferences

---

## #13 (feat) 统一可观测性仪表盘 — 2026-02-24

对应产品版本：v1.3.0

- Migration: chat_sessions 新增 12 个性能监控字段 + 4 个索引
- ChatSession Model: $fillable/$casts 扩展 + 3 个新 Scope + updatePerformanceMetrics()
- InternalCreditController: 接收性能字段 → 写入 chat_sessions + estimateCost() + 缓存清除
- AdminMetricsController: 6 个聚合 API（system-overview/model-comparison/mode-comparison/tool-usage/user-consumption/quality-trend）
- Redis 缓存层: TTL 10 分钟，key 格式 admin:metrics:{type}:{days}，写入时自动清除
- 路由: /api/admin/metrics/dashboard/* 6 个端点
- PHPUnit: AdminMetricsControllerTest 14 用例 / 48 断言全部通过

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


