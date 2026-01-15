<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 迁移肌肉字段到标准格式
 * 
 * 统一三端数据库字段：
 * - primary_muscle_zh (字符串) → muscles_primary_zh (数组)
 * - primary_muscle_en (字符串) → muscles_primary_en (数组)
 * 
 * @version 1.0.0
 * @date 2026-01-15
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. 将 primary_muscle_zh 转换为 muscles_primary_zh 数组
        DB::statement("
            UPDATE exercises 
            SET muscles_primary_zh = JSON_ARRAY(primary_muscle_zh)
            WHERE primary_muscle_zh IS NOT NULL 
            AND (muscles_primary_zh IS NULL OR JSON_LENGTH(muscles_primary_zh) = 0)
        ");
        
        // 2. 将 primary_muscle_en 转换为 muscles_primary_en 数组
        DB::statement("
            UPDATE exercises 
            SET muscles_primary_en = JSON_ARRAY(primary_muscle_en)
            WHERE primary_muscle_en IS NOT NULL 
            AND (muscles_primary_en IS NULL OR JSON_LENGTH(muscles_primary_en) = 0)
        ");
        
        // 3. 如果 all_muscles_zh 为空，从 primary_muscle_zh 填充
        DB::statement("
            UPDATE exercises 
            SET all_muscles_zh = JSON_ARRAY(primary_muscle_zh)
            WHERE primary_muscle_zh IS NOT NULL 
            AND (all_muscles_zh IS NULL OR JSON_LENGTH(all_muscles_zh) = 0)
        ");
        
        echo "✅ 肌肉字段迁移完成\n";
        echo "   - primary_muscle_zh → muscles_primary_zh (数组)\n";
        echo "   - primary_muscle_en → muscles_primary_en (数组)\n";
    }

    public function down(): void
    {
        // 回滚：从数组字段恢复到字符串字段
        DB::statement("
            UPDATE exercises 
            SET primary_muscle_zh = JSON_UNQUOTE(JSON_EXTRACT(muscles_primary_zh, '$[0]'))
            WHERE muscles_primary_zh IS NOT NULL 
            AND JSON_LENGTH(muscles_primary_zh) > 0
        ");
        
        DB::statement("
            UPDATE exercises 
            SET primary_muscle_en = JSON_UNQUOTE(JSON_EXTRACT(muscles_primary_en, '$[0]'))
            WHERE muscles_primary_en IS NOT NULL 
            AND JSON_LENGTH(muscles_primary_en) > 0
        ");
    }
};
