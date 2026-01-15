<?php

namespace App\Http\Controllers\Admin;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

/**
 * 数据迁移控制器（临时使用）
 * 
 * ⚠️ 仅用于生产环境数据迁移
 * ⚠️ 迁移完成后应删除此控制器
 * 
 * @version 1.0.0
 * @date 2026-01-15
 */
class DataMigrationController extends BaseController
{
    /**
     * 执行肌肉字段迁移（预览模式）
     */
    public function previewMuscleMigration(): JsonResponse
    {
        try {
            $stats = $this->getStats();
            
            return $this->success([
                'mode' => 'preview',
                'stats' => $stats,
                'message' => '这是预览模式，不会修改数据',
                'next_step' => '如果统计信息正确，请调用 /api/admin/migrate/muscles/execute 执行迁移'
            ], '预览成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '预览失败');
        }
    }

    /**
     * 执行肌肉字段迁移
     */
    public function executeMuscleMigration(): JsonResponse
    {
        try {
            // 获取迁移前统计
            $beforeStats = $this->getStats();
            
            // 执行迁移
            DB::beginTransaction();
            
            // 1. 迁移 primary_muscle_zh → muscles_primary_zh
            DB::statement("
                UPDATE exercises 
                SET muscles_primary_zh = JSON_ARRAY(primary_muscle_zh)
                WHERE primary_muscle_zh IS NOT NULL 
                AND primary_muscle_zh != ''
                AND (muscles_primary_zh IS NULL OR JSON_LENGTH(muscles_primary_zh) = 0)
            ");
            
            // 2. 迁移 primary_muscle_en → muscles_primary_en
            DB::statement("
                UPDATE exercises 
                SET muscles_primary_en = JSON_ARRAY(primary_muscle_en)
                WHERE primary_muscle_en IS NOT NULL 
                AND primary_muscle_en != ''
                AND (muscles_primary_en IS NULL OR JSON_LENGTH(muscles_primary_en) = 0)
            ");
            
            // 3. 填充 all_muscles_zh
            DB::statement("
                UPDATE exercises 
                SET all_muscles_zh = JSON_ARRAY(primary_muscle_zh)
                WHERE primary_muscle_zh IS NOT NULL 
                AND primary_muscle_zh != ''
                AND (all_muscles_zh IS NULL OR JSON_LENGTH(all_muscles_zh) = 0)
            ");
            
            DB::commit();
            
            // 获取迁移后统计
            $afterStats = $this->getStats();
            
            // 抽样检查
            $sample = DB::table('exercises')
                ->whereNotNull('muscles_primary_zh')
                ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                ->first(['id', 'name_zh', 'primary_muscle_zh', 'muscles_primary_zh']);
            
            return $this->success([
                'before' => $beforeStats,
                'after' => $afterStats,
                'sample' => $sample,
                'message' => '迁移成功完成'
            ], '✅ 肌肉字段迁移成功');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, '迁移失败');
        }
    }

    /**
     * 获取迁移统计信息
     */
    private function getStats(): array
    {
        return [
            'total' => DB::table('exercises')->count(),
            'has_primary_muscle_zh' => DB::table('exercises')
                ->whereNotNull('primary_muscle_zh')
                ->count(),
            'has_muscles_primary_zh' => DB::table('exercises')
                ->whereNotNull('muscles_primary_zh')
                ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                ->count(),
            'needs_migration' => DB::table('exercises')
                ->whereNotNull('primary_muscle_zh')
                ->where(function($q) {
                    $q->whereNull('muscles_primary_zh')
                      ->orWhereRaw('JSON_LENGTH(muscles_primary_zh) = 0');
                })
                ->count(),
        ];
    }

    /**
     * 验证迁移结果
     */
    public function verifyMigration(): JsonResponse
    {
        try {
            $stats = $this->getStats();
            
            // 抽样检查（5条记录）
            $samples = DB::table('exercises')
                ->whereNotNull('muscles_primary_zh')
                ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
                ->limit(5)
                ->get(['id', 'name_zh', 'primary_muscle_zh', 'muscles_primary_zh']);
            
            $success = $stats['needs_migration'] === 0;
            
            return $this->success([
                'success' => $success,
                'stats' => $stats,
                'samples' => $samples,
                'message' => $success ? '所有记录已成功迁移' : "仍有 {$stats['needs_migration']} 条记录未迁移"
            ], '验证完成');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '验证失败');
        }
    }
}
