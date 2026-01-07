<?php

namespace App\Console\Commands;

use App\Modules\Exercise\Models\Exercise;
use Illuminate\Console\Command;

/**
 * 同步动作源文件数据到数据库 - 字段命名与源数据完全一致
 * 
 * @version 2.0.0
 * @date 2026-01-04
 */
class SyncExerciseDataCommand extends Command
{
    protected $signature = 'exercise:sync-data 
                            {--dry-run : 预览模式}
                            {--id= : 只同步指定ID}';

    protected $description = '从源文件同步动作数据到数据库';

    private string $sourceDir;
    private array $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

    public function handle(): int
    {
        $this->sourceDir = storage_path('app/public/exercises_v2');
        $dryRun = $this->option('dry-run');
        $targetId = $this->option('id');

        $this->info('=== 动作数据同步 v2.0 ===');
        $this->info('模式: ' . ($dryRun ? '预览' : '执行'));

        if ($targetId) {
            $this->syncSingle($targetId, $dryRun);
        } else {
            $this->syncAll($dryRun);
        }

        $this->printStats();
        return Command::SUCCESS;
    }

    private function syncAll(bool $dryRun): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() !== 'data.json') continue;
            
            $this->stats['total']++;
            $this->syncFromFile($file->getPathname(), $dryRun);

            if ($this->stats['total'] % 100 === 0) {
                $this->info("已处理: {$this->stats['total']}");
            }
        }
    }

    private function syncSingle(int $id, bool $dryRun): void
    {
        $path = $this->getStoragePath($id);
        $filePath = "{$this->sourceDir}/{$path}/data.json";

        if (!file_exists($filePath)) {
            $this->error("源文件不存在: {$filePath}");
            return;
        }

        $this->stats['total'] = 1;
        $this->syncFromFile($filePath, $dryRun);
    }

    private function syncFromFile(string $filePath, bool $dryRun): void
    {
        try {
            $source = json_decode(file_get_contents($filePath), true);
            if (!$source || !isset($source['id'])) return;

            $data = $this->mapFields($source);
            
            if (!$dryRun) {
                $exercise = Exercise::find($source['id']);
                if ($exercise) {
                    $exercise->update($data);
                    $this->stats['updated']++;
                } else {
                    $data['id'] = $source['id'];
                    Exercise::create($data);
                    $this->stats['created']++;
                }
            }
        } catch (\Exception $e) {
            $this->stats['errors'][] = ['file' => $filePath, 'error' => $e->getMessage()];
        }
    }

    private function mapFields(array $source): array
    {
        // 直接映射 - 源文件字段名与数据库字段名完全一致
        $directFields = [
            'name_en', 'name_zh', 'description_en', 'description_zh',
            'primary_muscle_en', 'primary_muscle_zh',
            'equipment_en', 'equipment_zh',
            'difficulty_en', 'difficulty_zh',
            'force_en', 'force_zh',
            'mechanic_en', 'mechanic_zh',
            'rep_range', 'set_range', 'rest_period', 'intensity_percentage',
            'safety_level', 'kinetic_chain_type', 'nutrition_timing',
        ];

        $data = [];
        foreach ($directFields as $field) {
            if (isset($source[$field]) && $source[$field] !== null && $source[$field] !== '') {
                $data[$field] = $source[$field];
            }
        }

        // 数组字段
        $arrayFields = [
            'grips_en', 'grips_zh', 'correct_steps_en', 'correct_steps_zh',
            'all_muscles_zh', 'smart_tags', 'safety_pre_check', 'equipment_risks',
            'key_nutrients', 'recommended_foods',
        ];

        foreach ($arrayFields as $field) {
            if (isset($source[$field]) && is_array($source[$field]) && !empty($source[$field])) {
                $data[$field] = $source[$field];
            }
        }

        // 生成 slug（加上ID避免重复）
        if (isset($source['name_en'])) {
            $data['slug'] = \Str::slug($source['name_en']) . '-' . $source['id'];
        }

        return $data;
    }

    private function getStoragePath(int $id): string
    {
        $rangeStart = floor(($id - 1) / 100) * 100;
        $rangeEnd = $rangeStart + 99;
        $subRangeStart = floor(($id - 1) / 10) * 10;
        $subRangeEnd = $subRangeStart + 9;

        return sprintf('%04d-%04d/%04d-%04d/%d', $rangeStart, $rangeEnd, $subRangeStart, $subRangeEnd, $id);
    }

    private function printStats(): void
    {
        $this->newLine();
        $this->info('=== 完成 ===');
        $this->info("总数: {$this->stats['total']}");
        $this->info("创建: {$this->stats['created']}");
        $this->info("更新: {$this->stats['updated']}");
        $this->info("错误: " . count($this->stats['errors']));
        
        if (!empty($this->stats['errors'])) {
            $this->newLine();
            $this->error('--- 错误详情 ---');
            foreach ($this->stats['errors'] as $err) {
                $this->error("  {$err['file']}: {$err['error']}");
            }
        }
    }
}
