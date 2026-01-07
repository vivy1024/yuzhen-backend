-- ===================================================================
-- FITNESS_APP 数据库清理脚本
-- 
-- 目的：删除不需要的监控和分析表，保留核心业务功能
-- 日期：2025-11-02
-- ===================================================================

USE fitness_app;

SET FOREIGN_KEY_CHECKS = 0;

-- ======================
-- 1. 删除性能监控相关表 (15个)
-- ======================
DROP TABLE IF EXISTS `performance_alerts`;
DROP TABLE IF EXISTS `performance_api_records`;
DROP TABLE IF EXISTS `performance_cache_records`;
DROP TABLE IF EXISTS `performance_database_records`;
DROP TABLE IF EXISTS `performance_metrics`;
DROP TABLE IF EXISTS `performance_metrics_history`;
DROP TABLE IF EXISTS `performance_optimizations`;
DROP TABLE IF EXISTS `performance_reports`;
DROP TABLE IF EXISTS `performance_system_snapshots`;
DROP TABLE IF EXISTS `cache_performance_stats`;
DROP TABLE IF EXISTS `database_performance_stats`;
DROP TABLE IF EXISTS `endpoint_performance_stats`;

-- ======================
-- 2. 删除Laravel Telescope监控表 (3个)
-- ======================
DROP TABLE IF EXISTS `telescope_entries`;
DROP TABLE IF EXISTS `telescope_entries_tags`;
DROP TABLE IF EXISTS `telescope_monitoring`;

-- ======================
-- 3. 删除系统资源监控表 (3个)
-- ======================
DROP TABLE IF EXISTS `system_alerts`;
DROP TABLE IF EXISTS `system_resource_history`;
DROP TABLE IF EXISTS `usage_statistics`;

-- ======================
-- 4. 删除用户行为分析表 (2个)
-- ======================
DROP TABLE IF EXISTS `user_behavior_analytics`;
DROP TABLE IF EXISTS `user_intent_profiles`;

-- ======================  
-- 5. 删除其他不需要的表 (根据业务决定)
-- ======================
-- DROP TABLE IF EXISTS `chat_sessions`;  -- 如果不需要聊天记录
-- DROP TABLE IF EXISTS `body_data_records`;  -- 如果不需要身体数据记录
-- DROP TABLE IF EXISTS `user_sync_logs`;  -- 同步日志

-- ======================
-- 6. 删除RBAC权限系统表 (如果使用Laravel自带权限)
-- ======================
DROP TABLE IF EXISTS `sys_user_role`;
DROP TABLE IF EXISTS `sys_role`;

SET FOREIGN_KEY_CHECKS = 1;

-- ======================
-- 清理完成统计
-- ======================
SELECT 
    COUNT(*) as remaining_tables,
    'fitness_app' as database_name
FROM information_schema.tables 
WHERE table_schema = 'fitness_app';

SELECT 
    table_name,
    table_rows,
    ROUND(data_length / 1024 / 1024, 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'fitness_app'
ORDER BY table_name;











