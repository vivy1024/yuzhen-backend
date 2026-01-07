<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 统一训练等级标准迁移
 * 
 * 将旧的5级标准（beginner/novice/intermediate/advanced/elite）
 * 统一为新的4级标准（novice/beginner/intermediate/advanced）
 * 
 * 映射规则：
 * - novice → novice（零基础，保持不变）
 * - beginner → beginner（初级，保持不变）
 * - intermediate → intermediate（中级，不变）
 * - advanced → advanced（高级，不变）
 * - elite → advanced（精英归入高级）
 * 
 * @version 1.1.0
 * @date 2026-01-05
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 更新 user_profiles 表中的 fitness_level
        DB::table('user_profiles')
            ->whereNotNull('basic_info')
            ->orderBy('id')
            ->chunk(100, function ($profiles) {
                foreach ($profiles as $profile) {
                    $basicInfo = json_decode($profile->basic_info, true);
                    
                    if (!$basicInfo || !isset($basicInfo['fitness_level'])) {
                        continue;
                    }
                    
                    $oldLevel = $basicInfo['fitness_level'];
                    $newLevel = $this->mapFitnessLevel($oldLevel);
                    
                    if ($oldLevel !== $newLevel) {
                        $basicInfo['fitness_level'] = $newLevel;
                        
                        DB::table('user_profiles')
                            ->where('id', $profile->id)
                            ->update([
                                'basic_info' => json_encode($basicInfo, JSON_UNESCAPED_UNICODE),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
        
        // 更新 training_plans 表中的 difficulty（如果存在）
        if (Schema::hasTable('training_plans') && Schema::hasColumn('training_plans', 'difficulty')) {
            // 先修改 enum 类型
            DB::statement("ALTER TABLE training_plans MODIFY COLUMN difficulty ENUM('novice', 'beginner', 'intermediate', 'advanced') NULL COMMENT '难度'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚 user_profiles 表中的 fitness_level
        DB::table('user_profiles')
            ->whereNotNull('basic_info')
            ->orderBy('id')
            ->chunk(100, function ($profiles) {
                foreach ($profiles as $profile) {
                    $basicInfo = json_decode($profile->basic_info, true);
                    
                    if (!$basicInfo || !isset($basicInfo['fitness_level'])) {
                        continue;
                    }
                    
                    $newLevel = $basicInfo['fitness_level'];
                    $oldLevel = $this->reverseMapFitnessLevel($newLevel);
                    
                    if ($oldLevel !== $newLevel) {
                        $basicInfo['fitness_level'] = $oldLevel;
                        
                        DB::table('user_profiles')
                            ->where('id', $profile->id)
                            ->update([
                                'basic_info' => json_encode($basicInfo, JSON_UNESCAPED_UNICODE),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
        
        // 回滚 training_plans 表中的 difficulty
        if (Schema::hasTable('training_plans') && Schema::hasColumn('training_plans', 'difficulty')) {
            DB::statement("ALTER TABLE training_plans MODIFY COLUMN difficulty ENUM('beginner', 'intermediate', 'advanced') NULL COMMENT '难度'");
        }
    }
    
    /**
     * 映射旧的训练等级到新标准
     */
    private function mapFitnessLevel(string $oldLevel): string
    {
        $mapping = [
            'novice' => 'novice',          // 零基础（保持不变）
            'beginner' => 'beginner',      // 初级（保持不变）
            'intermediate' => 'intermediate',
            'advanced' => 'advanced',
            'elite' => 'advanced',         // 精英 → 高级
        ];
        
        return $mapping[$oldLevel] ?? $oldLevel;
    }
    
    /**
     * 反向映射（用于回滚）
     */
    private function reverseMapFitnessLevel(string $newLevel): string
    {
        $mapping = [
            'novice' => 'novice',
            'beginner' => 'beginner',
            'intermediate' => 'intermediate',
            'advanced' => 'advanced',
        ];
        
        return $mapping[$newLevel] ?? $newLevel;
    }
};
