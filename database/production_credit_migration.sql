-- ============================================
-- 玉珍健身 - 积分体系生产数据库迁移
-- 生成日期: 2026-02-17
-- 对应 Laravel 迁移: 7个文件
-- 执行方式: phpMyAdmin SQL 面板
-- ============================================

-- 获取下一个 batch 号
SET @next_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`);

-- ============================================
-- 1/7: 2026_01_11_100002_create_user_credits_table (旧版，会被第3步覆盖)
-- ============================================
CREATE TABLE IF NOT EXISTS `user_credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `dag_credits` int unsigned NOT NULL DEFAULT 0 COMMENT 'DAG额外次数',
  `agent_credits` int unsigned NOT NULL DEFAULT 0 COMMENT 'Agent额外次数',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_credits_user_id_unique` (`user_id`),
  CONSTRAINT `user_credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2/7: 2026_01_11_100003_create_credit_logs_table
-- ============================================
CREATE TABLE IF NOT EXISTS `credit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `dag_amount` int NOT NULL DEFAULT 0 COMMENT 'DAG额度变更量',
  `agent_amount` int NOT NULL DEFAULT 0 COMMENT 'Agent额度变更量',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '变更原因',
  `admin_id` bigint unsigned DEFAULT NULL COMMENT '操作管理员ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `credit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_logs_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3/7: 2026_02_05_100001_recreate_user_credits_for_credit_system
-- 删除旧表，创建新的积分体系表
-- ============================================
DROP TABLE IF EXISTS `user_credits`;

CREATE TABLE `user_credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `daily_quota` int unsigned NOT NULL DEFAULT 10 COMMENT '每日积分配额（免费10/暖心50/能量200）',
  `daily_consumed` int unsigned NOT NULL DEFAULT 0 COMMENT '今日已消耗积分',
  `total_consumed` bigint unsigned NOT NULL DEFAULT 0 COMMENT '历史总消耗积分',
  `last_reset_date` date NOT NULL COMMENT '上次配额重置日期',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_credits_user_id_unique` (`user_id`),
  KEY `idx_user_credits_user_id` (`user_id`),
  KEY `idx_user_credits_last_reset` (`last_reset_date`),
  CONSTRAINT `user_credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4/7: 2026_02_05_100002_create_credit_transactions_table
-- ============================================
CREATE TABLE `credit_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `credits` int NOT NULL COMMENT '消耗积分数（正数为消耗，负数为充值/分享获得）',
  `tokens` int NOT NULL COMMENT '消耗Token总数',
  `mode` enum('dag','agent') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '查询模式：dag=DAG模式(1.0x), agent=Agent模式(1.5x)',
  `template_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'DAG模板名称',
  `conversation_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会话ID，用于关联具体对话',
  `input_tokens` int NOT NULL DEFAULT 0 COMMENT '输入Token数',
  `output_tokens` int NOT NULL DEFAULT 0 COMMENT '输出Token数',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '交易描述',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_credit_trans_user_id` (`user_id`),
  KEY `idx_credit_trans_created_at` (`created_at`),
  KEY `idx_credit_trans_conversation` (`conversation_id`),
  KEY `idx_credit_trans_user_date` (`user_id`, `created_at`),
  CONSTRAINT `credit_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5/7: 2026_02_05_100003_create_credit_shares_table
-- ============================================
CREATE TABLE `credit_shares` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned NOT NULL COMMENT '发送者用户ID（能量会员）',
  `receiver_id` bigint unsigned NOT NULL COMMENT '接收者用户ID',
  `credits` int NOT NULL COMMENT '分享的积分数量（正整数）',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分享留言',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_credit_shares_sender` (`sender_id`),
  KEY `idx_credit_shares_receiver` (`receiver_id`),
  KEY `idx_credit_shares_created_at` (`created_at`),
  CONSTRAINT `credit_shares_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_shares_receiver_id_foreign` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6/7: 2026_02_16_181236_add_unique_conversation_id_to_credit_transactions
-- 将 conversation_id 普通索引升级为 user_id+conversation_id 复合唯一索引
-- ============================================
ALTER TABLE `credit_transactions` DROP INDEX `idx_credit_trans_conversation`;
ALTER TABLE `credit_transactions` ADD UNIQUE INDEX `idx_credit_trans_user_conversation_unique` (`user_id`, `conversation_id`);

-- ============================================
-- 7/7: 2026_02_16_200000_create_knowledge_ingestion_status_table
-- ============================================
CREATE TABLE `knowledge_ingestion_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文档唯一标识 MD5(filepath)',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `filepath` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '完整路径',
  `source_type` enum('bilibili_subtitle','pdf_textbook','markdown_note') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '来源类型',
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `chunk_count` int unsigned NOT NULL DEFAULT 0 COMMENT '分割后的chunk数',
  `vector_count` int unsigned NOT NULL DEFAULT 0 COMMENT '成功入库的向量数',
  `total_chars` int unsigned NOT NULL DEFAULT 0 COMMENT '总字符数',
  `error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '失败时的错误信息',
  `qdrant_collection` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'training_knowledge',
  `embedding_model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'thenlper/gte-large-zh',
  `processing_time_ms` int unsigned NOT NULL DEFAULT 0 COMMENT '处理耗时毫秒',
  `metadata` json DEFAULT NULL COMMENT '额外元数据',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `knowledge_ingestion_status_document_id_unique` (`document_id`),
  KEY `idx_status` (`status`),
  KEY `idx_source_type` (`source_type`),
  KEY `idx_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 注册迁移记录到 migrations 表（让 Laravel 知道这些已执行）
-- ============================================
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_01_11_100002_create_user_credits_table', @next_batch),
('2026_01_11_100003_create_credit_logs_table', @next_batch),
('2026_02_05_100001_recreate_user_credits_for_credit_system', @next_batch),
('2026_02_05_100002_create_credit_transactions_table', @next_batch),
('2026_02_05_100003_create_credit_shares_table', @next_batch),
('2026_02_16_181236_add_unique_conversation_id_to_credit_transactions', @next_batch),
('2026_02_16_200000_create_knowledge_ingestion_status_table', @next_batch);

-- ============================================
-- 验证：查看新创建的表
-- ============================================
SHOW TABLES LIKE '%credit%';
SHOW TABLES LIKE 'knowledge_%';
SELECT * FROM `migrations` WHERE `batch` = @next_batch;
