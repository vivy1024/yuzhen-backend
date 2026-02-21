# yuzhen-backend 构建日志

> 内部构建号，不对外发布。产品版本见根仓库 `CHANGELOG.md`。
> 历史版本（v2.127.0 及之前）已归档至 `CHANGELOG-legacy.md`。

---

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
