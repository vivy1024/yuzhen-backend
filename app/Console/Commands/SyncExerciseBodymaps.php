<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 同步动作人体图字段到数据库
 * 
 * @version 1.0.0
 * @date 2026-01-05
 */
class SyncExerciseBodymaps extends Command
{
    protected $signature = 'exercise:sync-bodymaps {--limit= : 限制处理数量}';
    protected $description = '从JSON文件同步body_map_images等字段到数据库';

    private $exercisesPath;
    private $stats = [
        'total' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    public function handle()
    {
        $this->exercisesPath = storage_path('app/public/exercises_v2');
        $limit = $this->option('limit');

        $this->info('🚀 开始同步人体图字段...');

        $files = $this->findAllExerciseFiles();
        $this->stats['total'] = count($files);

        if ($limit) {
            $files = array_slice($files, 0, (int)$limit);
            $this->info("📋 限制处理: {$limit} 个");
        }

        $this->info("📋 找到 {$this->stats['total']} 个文件");

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $filePath) {
            try {
                $this->syncSingleExercise($filePath);
            } catch (\Exception $e) {
                $this->stats['failed']++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== 完成统计 ===");
        $this->info("总数: {$this->stats['total']}");
        $this->info("更新: {$this->stats['updated']}");
        $this->info("跳过: {$this->stats['skipped']}");
        $this->info("失败: {$this->stats['failed']}");
    }

    private function findAllExerciseFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->exercisesPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'data.json') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    private function syncSingleExercise(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (!$data || !isset($data['id'])) {
            $this->stats['failed']++;
            return;
        }

        $exerciseId = $data['id'];
        $updateData = [];

        // 人体图字段
        if (!empty($data['body_map_images'])) {
            $updateData['body_map_images'] = json_encode($data['body_map_images']);
        }
        if (!empty($data['body_map_images_local'])) {
            $updateData['body_map_images_local'] = json_encode($data['body_map_images_local']);
        }

        // 变体和关节
        if (isset($data['variation_of'])) {
            $updateData['variation_of'] = $data['variation_of'];
        }
        if (!empty($data['variations'])) {
            $updateData['variations'] = json_encode($data['variations']);
        }
        if (!empty($data['joints'])) {
            $updateData['joints'] = json_encode($data['joints']);
        }

        // 肌肉详细信息
        if (!empty($data['muscles_primary_en'])) {
            $updateData['muscles_primary_en'] = json_encode($data['muscles_primary_en']);
        }
        if (!empty($data['muscles_primary_zh'])) {
            $updateData['muscles_primary_zh'] = json_encode($data['muscles_primary_zh']);
        }
        if (!empty($data['muscles_secondary_en'])) {
            $updateData['muscles_secondary_en'] = json_encode($data['muscles_secondary_en']);
        }
        if (!empty($data['muscles_secondary_zh'])) {
            $updateData['muscles_secondary_zh'] = json_encode($data['muscles_secondary_zh']);
        }

        if (empty($updateData)) {
            $this->stats['skipped']++;
            return;
        }

        $updateData['updated_at'] = now();

        $affected = DB::table('exercises')
            ->where('id', $exerciseId)
            ->update($updateData);

        if ($affected > 0) {
            $this->stats['updated']++;
        } else {
            $this->stats['skipped']++;
        }
    }
}
