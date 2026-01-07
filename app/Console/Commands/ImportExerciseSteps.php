<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ImportExerciseSteps extends Command
{
    /**
     * 命令的名称和签名
     *
     * @var string
     */
    protected $signature = 'exercise:import-steps';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '从 exercises_v2 原始数据导入 correct_steps 字段';

    private int $importedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $this->info('🚀 开始导入 correct_steps 数据...');

        $dataPath = storage_path('app/public/exercises_v2');
        $this->line("📂 数据目录: {$dataPath}");

        if (!is_dir($dataPath)) {
            $this->error("❌ 数据目录不存在: {$dataPath}");
            return 1;
        }

        $files = $this->findJsonFiles($dataPath);
        $this->line("🔍 找到 " . count($files) . " 个 data.json 文件\n");

        foreach ($files as $jsonFile) {
            $this->processFile($jsonFile);
        }

        $this->printStatistics();

        return 0;
    }

    /**
     * 递归查找所有 data.json 文件
     */
    private function findJsonFiles(string $dataPath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dataPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'data.json' && $file->isFile()) {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    /**
     * 处理单个 JSON 文件
     */
    private function processFile(string $jsonFile): void
    {
        try {
            $content = file_get_contents($jsonFile);
            $data = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);

            if (!$data || !isset($data['id'])) {
                $this->skippedCount++;
                return;
            }

            $exerciseId = $data['id'];
            $correctSteps = $data['correct_steps'] ?? null;
            $correctStepsZh = $data['correct_steps_zh'] ?? null;

            // 如果两个字段都为空，检查是否复制了 description
            if (!$correctSteps && !$correctStepsZh) {
                $description = $data['description'] ?? '';
                if (str_contains($description, 'Correct Steps') || str_contains(strtolower($description), 'correct')) {
                    $this->warn("⚠️  Exercise {$exerciseId}: description 包含步骤，但 correct_steps 为空");
                }
                $this->skippedCount++;
                return;
            }

            // 更新数据库
            DB::table('exercises')
                ->where('id', $exerciseId)
                ->update([
                    'correct_steps' => $correctSteps ? json_encode($correctSteps, JSON_UNESCAPED_UNICODE) : null,
                    'correct_steps_zh' => $correctStepsZh ? json_encode($correctStepsZh, JSON_UNESCAPED_UNICODE) : null,
                ]);

            $this->importedCount++;

            if ($this->importedCount % 10 === 0) {
                $this->info("✅ 已导入 {$this->importedCount} 条数据...");
            }

        } catch (\Exception $e) {
            $this->error("❌ 处理失败 {$jsonFile}: {$e->getMessage()}");
            $this->errorCount++;
        }
    }

    /**
     * 输出统计结果
     */
    private function printStatistics(): void
    {
        $this->line("\n" . str_repeat("=", 50));
        $this->line("📊 导入统计结果:");
        $this->info("✅ 成功导入: {$this->importedCount} 条");
        $this->line("⏭️  跳过: {$this->skippedCount} 条");
        if ($this->errorCount > 0) {
            $this->error("❌ 错误: {$this->errorCount} 条");
        }
        $this->line(str_repeat("=", 50));
    }
}
