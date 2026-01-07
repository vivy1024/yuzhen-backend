<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AnalyzeCorrectSteps extends Command
{
    protected $signature = 'exercise:analyze-steps';
    protected $description = '分析 correct_steps 和 correct_steps_zh 的关系';

    public function handle(): int
    {
        $this->info('🔍 分析原始数据中 correct_steps 和 correct_steps_zh 的关系...\n');

        $dataPath = storage_path('app/public/exercises_v2');
        $stats = [
            'both' => 0,
            'steps_only' => 0,
            'steps_zh_only' => 0,
            'neither' => 0,
            'total' => 0,
        ];

        $stepsOnlyExamples = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dataPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'data.json' && $file->isFile()) {
                $content = file_get_contents($file->getRealPath());
                $data = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);

                if (!isset($data['id'])) continue;

                $stats['total']++;
                $steps = $data['correct_steps'] ?? null;
                $stepsZh = $data['correct_steps_zh'] ?? null;

                if ($steps && $stepsZh) {
                    $stats['both']++;
                } elseif ($steps && !$stepsZh) {
                    $stats['steps_only']++;
                    if (count($stepsOnlyExamples) < 5) {
                        $stepsOnlyExamples[] = [
                            'id' => $data['id'],
                            'steps_count' => is_array($steps) ? count($steps) : 0,
                        ];
                    }
                } elseif (!$steps && $stepsZh) {
                    $stats['steps_zh_only']++;
                } else {
                    $stats['neither']++;
                }
            }
        }

        $this->line("📊 原始数据统计:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("总数: {$stats['total']}");
        $this->info("✅ 两个都有 (correct_steps + correct_steps_zh): {$stats['both']}");
        $this->warn("⚠️  仅有英文 (correct_steps only): {$stats['steps_only']}");
        $this->warn("⚠️  仅有中文 (correct_steps_zh only): {$stats['steps_zh_only']}");
        $this->error("❌ 都没有: {$stats['neither']}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        $this->line("🔍 关键发现:");
        $this->line("如果 correct_steps_zh 为空，correct_steps 也应该为空");
        $this->line("现在有 {$stats['steps_only']} 条记录只有 correct_steps，这可能是:");
        $this->line("1. correct_steps 从 description 复制过来");
        $this->line("2. 原始数据的中文翻译缺失\n");

        $this->line("📝 仅有 correct_steps 的示例:");
        foreach ($stepsOnlyExamples as $example) {
            $this->line("   Exercise {$example['id']}: {$example['steps_count']} 步");
        }
        $this->line("");

        // 检查数据库
        $this->line("🔄 检查数据库中的重复情况...");
        $dbCount = DB::table('exercises')->whereNotNull('correct_steps')->count();
        $dbExamples = DB::table('exercises')
            ->whereNotNull('correct_steps')
            ->limit(3)
            ->get();

        $this->line("数据库中有 correct_steps 的记录: $dbCount\n");

        foreach ($dbExamples as $exercise) {
            $steps = json_decode($exercise->correct_steps, true);
            $stepsStr = json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $this->line("📌 Exercise {$exercise->id}:");
            $this->line("   description: " . substr($exercise->description, 0, 80) . "...");
            $this->line("   correct_steps (first): " . substr($stepsStr, 0, 150) . "...\n");
        }

        $this->info("✅ 分析完成！");
        $this->line("\n💡 建议: 清空所有 steps_only 的 correct_steps 字段（保留两个都有的数据）");

        return 0;
    }
}
