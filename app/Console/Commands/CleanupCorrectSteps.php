<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CleanupCorrectSteps extends Command
{
    protected $signature = 'exercise:cleanup-steps {--dry-run : 预演模式} {--force : 跳过确认}';
    protected $description = '清空污染的 correct_steps 数据（只保留有中文版本的）';

    public function handle(): int
    {
        $this->info('🧹 开始清理污染的 correct_steps 数据...\n');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $dataPath = storage_path('app/public/exercises_v2');

        // 构建 exercise_id => has_both 的映射
        $exerciseMap = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dataPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $this->line("📂 扫描原始数据...");
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'data.json' && $file->isFile()) {
                $content = file_get_contents($file->getRealPath());
                $data = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);

                if (!isset($data['id'])) continue;

                $exerciseId = $data['id'];
                $steps = $data['correct_steps'] ?? null;
                $stepsZh = $data['correct_steps_zh'] ?? null;

                // 标记是否有完整的中英文步骤
                $exerciseMap[$exerciseId] = [
                    'has_steps' => !empty($steps),
                    'has_steps_zh' => !empty($stepsZh),
                    'should_keep' => !empty($steps) && !empty($stepsZh), // 两个都有才保留
                ];
            }
        }

        $this->line("✅ 扫描完成，共找到 " . count($exerciseMap) . " 条记录\n");

        // 统计要删除的数据
        $toDelete = [];
        $toKeep = [];

        foreach ($exerciseMap as $id => $info) {
            if ($info['should_keep']) {
                $toKeep[] = $id;
            } else {
                $toDelete[] = $id;
            }
        }

        $this->line("📊 清理统计:");
        $this->info("✅ 保留（有中英文）: " . count($toKeep) . " 条");
        $this->warn("🗑️  删除（仅英文或无数据）: " . count($toDelete) . " 条");
        $this->line("");

        if (empty($toDelete)) {
            $this->info("✅ 没有需要清理的数据");
            return 0;
        }

        // 预演模式
        if ($dryRun) {
            $this->warn("⚠️  预演模式：不会实际删除数据");
            $this->line("示例（要删除的前 10 条 ID）: " . implode(', ', array_slice($toDelete, 0, 10)));
            $this->line("\n运行无 --dry-run 参数来执行实际删除");
            return 0;
        }

        // 实际删除
        $this->warn("⚠️  将清空 " . count($toDelete) . " 条记录的 correct_steps 和 correct_steps_zh");

        if (!$force && !$this->confirm('继续吗？')) {
            $this->error("❌ 已取消");
            return 1;
        }

        // 分批删除
        $batchSize = 100;
        $totalDeleted = 0;

        foreach (array_chunk($toDelete, $batchSize) as $batch) {
            DB::table('exercises')
                ->whereIn('id', $batch)
                ->update([
                    'correct_steps' => null,
                    'correct_steps_zh' => null,
                ]);

            $totalDeleted += count($batch);
            $this->info("✅ 已清空 $totalDeleted 条记录...");
        }

        $this->info("\n✅ 清理完成！");
        $this->info("保留: " . count($toKeep) . " 条有效数据");
        $this->info("删除: $totalDeleted 条污染数据");

        return 0;
    }
}
