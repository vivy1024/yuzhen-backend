<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 迁移肌肉字段到标准格式
 * 
 * 使用方法：
 * php artisan exercises:migrate-muscle-fields
 * 
 * @version 1.0.0
 * @date 2026-01-15
 */
class MigrateMuscleFieldsCommand extends Command
{
    protected $signature = 'exercises:migrate-muscle-fields 
                            {--dry-run : 仅显示将要执行的操作，不实际修改数据}
                            {--force : 强制执行，跳过确认}';

    protected $description = '迁移肌肉字段到标准格式（三端统一）';

    public function handle()
    {
        $this->info('🔄 开始迁移肌肉字段到标准格式...');
        $this->newLine();

        // 1. 检查字段是否存在
        if (!$this->checkFields()) {
            return 1;
        }

        // 2. 统计需要迁移的数据
        $stats = $this->getStats();
        $this->displayStats($stats);

        // 3. 确认执行
        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm('确认执行迁移？')) {
                $this->warn('❌ 迁移已取消');
                return 0;
            }
        }

        // 4. 执行迁移
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN 模式：不会实际修改数据');
            return 0;
        }

        $this->migrate();

        // 5. 验证结果
        $this->verify();

        $this->newLine();
        $this->info('✅ 肌肉字段迁移完成！');

        return 0;
    }

    /**
     * 检查必要字段是否存在
     */
    private function checkFields(): bool
    {
        $this->info('📋 检查数据库字段...');

        $columns = DB::select("SHOW COLUMNS FROM exercises");
        $columnNames = array_column($columns, 'Field');

        $requiredFields = [
            'primary_muscle_zh',
            'primary_muscle_en',
            'muscles_primary_zh',
            'muscles_primary_en',
        ];

        $missing = [];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $columnNames)) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->error('❌ 缺少必要字段：' . implode(', ', $missing));
            $this->warn('请先运行迁移：2026_01_05_000001_add_bodymap_fields_to_exercises.php');
            return false;
        }

        $this->info('✅ 所有必要字段都存在');
        return true;
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
     * 显示统计信息
     */
    private function displayStats(array $stats): void
    {
        $this->info('📊 数据统计：');
        $this->table(
            ['项目', '数量'],
            [
                ['总动作数', $stats['total']],
                ['有 primary_muscle_zh 的', $stats['has_primary_muscle_zh']],
                ['有 muscles_primary_zh 的', $stats['has_muscles_primary_zh']],
                ['需要迁移的', $stats['needs_migration']],
            ]
        );
        $this->newLine();
    }

    /**
     * 执行迁移
     */
    private function migrate(): void
    {
        $this->info('🔄 执行迁移...');

        DB::beginTransaction();

        try {
            // 1. 迁移 primary_muscle_zh → muscles_primary_zh
            $updated1 = DB::statement("
                UPDATE exercises 
                SET muscles_primary_zh = JSON_ARRAY(primary_muscle_zh)
                WHERE primary_muscle_zh IS NOT NULL 
                AND primary_muscle_zh != ''
                AND (muscles_primary_zh IS NULL OR JSON_LENGTH(muscles_primary_zh) = 0)
            ");
            $this->info("  ✅ primary_muscle_zh → muscles_primary_zh");

            // 2. 迁移 primary_muscle_en → muscles_primary_en
            $updated2 = DB::statement("
                UPDATE exercises 
                SET muscles_primary_en = JSON_ARRAY(primary_muscle_en)
                WHERE primary_muscle_en IS NOT NULL 
                AND primary_muscle_en != ''
                AND (muscles_primary_en IS NULL OR JSON_LENGTH(muscles_primary_en) = 0)
            ");
            $this->info("  ✅ primary_muscle_en → muscles_primary_en");

            // 3. 如果 all_muscles_zh 为空，从 primary_muscle_zh 填充
            $updated3 = DB::statement("
                UPDATE exercises 
                SET all_muscles_zh = JSON_ARRAY(primary_muscle_zh)
                WHERE primary_muscle_zh IS NOT NULL 
                AND primary_muscle_zh != ''
                AND (all_muscles_zh IS NULL OR JSON_LENGTH(all_muscles_zh) = 0)
            ");
            $this->info("  ✅ 填充 all_muscles_zh");

            DB::commit();
            $this->info('✅ 数据迁移成功');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ 迁移失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 验证迁移结果
     */
    private function verify(): void
    {
        $this->info('🔍 验证迁移结果...');

        $stats = $this->getStats();

        if ($stats['needs_migration'] > 0) {
            $this->warn("⚠️  仍有 {$stats['needs_migration']} 条记录未迁移");
        } else {
            $this->info('✅ 所有记录已成功迁移');
        }

        // 抽样检查
        $sample = DB::table('exercises')
            ->whereNotNull('muscles_primary_zh')
            ->whereRaw('JSON_LENGTH(muscles_primary_zh) > 0')
            ->first(['id', 'name_zh', 'primary_muscle_zh', 'muscles_primary_zh']);

        if ($sample) {
            $this->newLine();
            $this->info('📝 抽样检查：');
            $this->line("  ID: {$sample->id}");
            $this->line("  名称: {$sample->name_zh}");
            $this->line("  旧字段 (primary_muscle_zh): {$sample->primary_muscle_zh}");
            $this->line("  新字段 (muscles_primary_zh): {$sample->muscles_primary_zh}");
        }
    }
}
