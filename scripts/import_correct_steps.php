<?php
/**
 * 从 exercises_v2 原始数据导入 correct_steps 到数据库
 * 使用方式: php import_correct_steps.php
 */

require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;

class ExerciseStepsImporter
{
    private string $dataPath;
    private int $importedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;

    public function __construct()
    {
        $this->dataPath = storage_path('app/public/exercises_v2');
    }

    /**
     * 开始导入
     */
    public function import(): void
    {
        echo "🚀 开始导入 correct_steps 数据...\n";
        echo "📂 数据目录: {$this->dataPath}\n\n";

        $files = $this->findJsonFiles();
        echo "🔍 找到 " . count($files) . " 个 data.json 文件\n\n";

        foreach ($files as $jsonFile) {
            $this->processFile($jsonFile);
        }

        $this->printStatistics();
    }

    /**
     * 递归查找所有 data.json 文件
     */
    private function findJsonFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dataPath)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'data.json') {
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
                    echo "⚠️  Exercise {$exerciseId}: description 包含步骤，但 correct_steps 为空\n";
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
                echo "✅ 已导入 {$this->importedCount} 条数据...\n";
            }

        } catch (\Exception $e) {
            echo "❌ 处理失败 {$jsonFile}: {$e->getMessage()}\n";
            $this->errorCount++;
        }
    }

    /**
     * 输出统计结果
     */
    private function printStatistics(): void
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 导入统计结果:\n";
        echo "✅ 成功导入: {$this->importedCount} 条\n";
        echo "⏭️  跳过: {$this->skippedCount} 条\n";
        echo "❌ 错误: {$this->errorCount} 条\n";
        echo str_repeat("=", 50) . "\n";
    }
}

// 执行导入
$importer = new ExerciseStepsImporter();
$importer->import();
echo "✅ 导入完成！\n";
