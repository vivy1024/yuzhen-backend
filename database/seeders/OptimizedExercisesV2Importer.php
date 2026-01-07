<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Exception;

/**
 * 优化的Exercise V2数据导入器
 * 
 * 特点：
 * 1. 支持本地和CDN双模式
 * 2. 结构化存储媒体文件
 * 3. 分表存储指导、肌肉、标签
 * 4. 自动批量导入
 * 
 * 使用：
 * php artisan db:seed --class=OptimizedExercisesV2Importer
 * 
 * @version 1.0.0
 * @date 2025-11-01
 */
class OptimizedExercisesV2Importer extends Seeder 
{
    private $exercisesPath;
    private $storageDriver;
    private $cdnBaseUrl;
    private $stats = [
        'total_exercises' => 0,
        'imported_exercises' => 0,
        'failed_exercises' => 0,
        'media_files' => 0,
    ];
    
    public function __construct()
    {
        $this->exercisesPath = storage_path('app/public/exercises_v2');
        
        // 根据环境自动切换存储驱动
        $this->storageDriver = config('app.env') === 'production' ? 'oss' : 'local';
        $this->cdnBaseUrl = config('filesystems.disks.oss.cdn_url', '');
    }
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  Exercise V2 优化导入器 - 开发环境版                           ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        
        try {
            $this->validateEnvironment();
            $this->prepareDatabase();
            $this->importExercises();
            $this->printSummary();
            
        } catch (Exception $e) {
            echo "❌ 导入失败: " . $e->getMessage() . "\n";
            echo "错误详情: " . $e->getTraceAsString() . "\n";
            exit(1);
        }
    }
    
    /**
     * 验证环境
     */
    private function validateEnvironment(): void
    {
        echo "📋 验证运行环境...\n";
        
        // 检查exercises_v2目录
        if (!is_dir($this->exercisesPath)) {
            throw new Exception("exercises_v2目录不存在: {$this->exercisesPath}");
        }
        
        // 检查数据库连接
        try {
            DB::connection()->getPdo();
            echo "✅ 数据库连接成功\n";
        } catch (Exception $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
        
        // 检查必要的表是否存在（仅检查主表和媒体表）
        $requiredTables = ['exercises', 'exercise_v2_media'];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                throw new Exception("缺少必要的表: {$table}，请先运行迁移");
            }
        }
        
        echo "✅ 存储驱动: {$this->storageDriver}\n";
        echo "✅ 环境验证完成\n\n";
    }
    
    /**
     * 准备数据库
     */
    private function prepareDatabase(): void
    {
        echo "🗄️ 准备数据库表...\n";
        
        // 清空动作相关表
        $tables = [
            'exercise_v2_media',
            'exercises',
        ];
        
        DB::statement("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
                echo "✅ 清空表: {$table}\n";
            } catch (Exception $e) {
                echo "⚠️ 清空表失败 {$table}: " . $e->getMessage() . "\n";
            }
        }
        
        DB::statement("SET FOREIGN_KEY_CHECKS = 1");
        echo "✅ 数据库表准备完成\n\n";
    }
    
    /**
     * 导入所有动作
     */
    private function importExercises(): void
    {
        echo "🏋️ 开始导入exercises数据...\n";
        
        $exerciseFiles = $this->findAllExerciseFiles();
        $this->stats['total_exercises'] = count($exerciseFiles);
        
        echo "📊 发现 {$this->stats['total_exercises']} 个练习文件\n\n";
        
        // 批量导入
        $batchSize = 50;
        $batches = array_chunk($exerciseFiles, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            DB::transaction(function () use ($batch, $batchIndex, $batchSize) {
                foreach ($batch as $index => $filePath) {
                    try {
                        $this->importSingleExercise($filePath);
                        $this->stats['imported_exercises']++;
                        
                    } catch (Exception $e) {
                        $this->stats['failed_exercises']++;
                        echo "❌ 导入失败 {$filePath}: " . $e->getMessage() . "\n";
                    }
                }
                
                $currentCount = ($batchIndex + 1) * $batchSize;
                $currentCount = min($currentCount, $this->stats['total_exercises']);
                echo "✅ 进度: {$currentCount}/{$this->stats['total_exercises']}\n";
            });
        }
        
        echo "\n✅ exercises数据导入完成\n\n";
    }
    
    /**
     * 查找所有动作文件
     */
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
    
    /**
     * 导入单个动作
     */
    private function importSingleExercise(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("无法解析JSON文件: " . json_last_error_msg());
        }
        
        $exerciseId = $data['id'] ?? null;
        if (!$exerciseId) {
            throw new Exception("缺少动作ID");
        }
        
        // 插入exercises完整数据（包含JSON字段）
        $this->insertExerciseBase($exerciseId, $data);
        
        // 导入媒体文件
        $this->importMedia($exerciseId, $data);
    }
    
    /**
     * 插入exercises完整数据（包含JSON字段）
     */
    private function insertExerciseBase(int $exerciseId, array $data): void
    {
        // 生成唯一slug：使用ID确保唯一性
        $baseSlug = \Illuminate\Support\Str::slug($data['name'] ?? "exercise");
        $uniqueSlug = $baseSlug ? "{$baseSlug}-{$exerciseId}" : "exercise-{$exerciseId}";
        
        // 处理 correct_steps（正确步骤）
        $correctSteps = [];
        if (!empty($data['correct_steps'])) {
            foreach ($data['correct_steps'] as $step) {
                $correctSteps[] = is_array($step) ? ($step['text'] ?? $step['text_en_us'] ?? '') : $step;
            }
        }
        
        // 处理 secondary_muscles（次要肌肉）
        $secondaryMuscles = $data['secondary_muscles'] ?? [];
        
        // 处理 smart_tags（智能标签）
        $smartTags = $data['smart_tags'] ?? [];
        
        DB::table('exercises')->insertOrIgnore([
            'id' => $exerciseId,
            'name' => $data['name'] ?? "Exercise #{$exerciseId}",
            'name_zh' => $data['name_zh'] ?? null,
            'slug' => $uniqueSlug,
            'description' => $data['description'] ?? null,
            'description_zh' => $data['description_zh'] ?? null,
            
            // JSON字段
            'correct_steps' => !empty($correctSteps) ? json_encode($correctSteps) : null,
            'secondary_muscles' => !empty($secondaryMuscles) ? json_encode($secondaryMuscles) : null,
            'smart_tags' => !empty($smartTags) ? json_encode($smartTags) : null,
            'grips' => !empty($data['grips']) ? json_encode($data['grips']) : null,
            'categories' => !empty($data['categories']) ? json_encode($data['categories']) : null,
            
            // 基础字段
            'primary_muscle' => $data['primary_muscle'] ?? $data['primary_muscle_zh'] ?? null,
            'equipment' => $data['equipment'] ?? $data['equipment_zh'] ?? null,
            'difficulty' => $data['difficulty']['name'] ?? 'intermediate',
            'force_type' => $data['force']['name'] ?? null,
            'mechanic_type' => $data['mechanic']['name'] ?? null,
            'rating' => 0,
            'view_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * 导入媒体文件
     */
    private function importMedia(int $exerciseId, array $data): void
    {
        $mediaItems = [];
        $exerciseIdPadded = $this->getExerciseIdPath($exerciseId);
        
        // 处理所有变体的图片和视频
        $variants = ['male-1', 'male-2', 'female-1', 'female-2'];
        
        foreach ($variants as $index => $variant) {
            $isPrimary = ($index === 0); // male-1 为主要媒体
            
            // 缩略图
            $mediaItems[] = $this->createMediaItem(
                $exerciseId,
                'thumbnail',
                $variant,
                "{$exerciseIdPadded}/thumbnails/{$variant}-thumb.jpg",
                $isPrimary,
                $index * 3
            );
            
            // 高清图
            $mediaItems[] = $this->createMediaItem(
                $exerciseId,
                'image',
                $variant,
                "{$exerciseIdPadded}/images/{$variant}.jpg",
                $isPrimary,
                $index * 3 + 1
            );
            
            // 视频
            $mediaItems[] = $this->createMediaItem(
                $exerciseId,
                'video',
                $variant,
                "{$exerciseIdPadded}/videos/{$variant}.mp4",
                $isPrimary,
                $index * 3 + 2
            );
        }
        
        DB::table('exercise_v2_media')->insert($mediaItems);
        $this->stats['media_files'] += count($mediaItems);
    }
    
    /**
     * 创建媒体项
     */
    private function createMediaItem(
        int $exerciseId,
        string $mediaType,
        string $variant,
        string $relativePath,
        bool $isPrimary,
        int $displayOrder
    ): array {
        $fullPath = "exercises_v2/{$relativePath}";
        
        return [
            'exercise_id' => $exerciseId,
            'media_type' => $mediaType,
            'local_path' => $fullPath,  // ✅ 修改：file_path → local_path
            'cdn_url' => $this->getCdnUrl($fullPath),
            'file_size' => null,
            'duration' => null,
            'display_order' => $displayOrder,
        ];
    }
    
    
    /**
     * 获取动作ID路径（分组目录）
     */
    private function getExerciseIdPath(int $id): string
    {
        $groupStart = floor($id / 100) * 100;
        $groupEnd = $groupStart + 99;
        $subGroupStart = floor($id / 10) * 10;
        $subGroupEnd = $subGroupStart + 9;
        
        return sprintf(
            "%04d-%04d/%04d-%04d/%d",
            $groupStart,
            $groupEnd,
            $subGroupStart,
            $subGroupEnd,
            $id
        );
    }
    
    /**
     * 获取CDN URL
     */
    private function getCdnUrl(string $path): ?string
    {
        if ($this->storageDriver === 'local') {
            return null; // 本地开发不需要CDN URL
        }
        
        // 生产环境返回CDN URL
        if (empty($this->cdnBaseUrl)) {
            return null;
        }
        
        return rtrim($this->cdnBaseUrl, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * 获取MIME类型
     */
    private function getMimeType(string $mediaType): string
    {
        $mimeTypes = [
            'image' => 'image/jpeg',
            'thumbnail' => 'image/jpeg',
            'video' => 'video/mp4',
        ];
        
        return $mimeTypes[$mediaType] ?? 'application/octet-stream';
    }
    
    /**
     * 打印统计摘要
     */
    private function printSummary(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  导入完成统计                                                  ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "📊 动作统计:\n";
        echo "   - 总数: {$this->stats['total_exercises']}\n";
        echo "   - 成功: {$this->stats['imported_exercises']}\n";
        echo "   - 失败: {$this->stats['failed_exercises']}\n";
        echo "\n";
        echo "📸 媒体文件: {$this->stats['media_files']} 个\n";
        echo "📝 JSON字段已填充（correct_steps, secondary_muscles, smart_tags）\n";
        echo "\n";
        echo "✅ 导入成功！\n";
        echo "\n";
    }
}

