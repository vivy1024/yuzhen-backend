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
 * Exercise V2 数据导入器 - 适配新表结构
 * 
 * 新表结构使用双语分离字段：name_en, name_zh, description_en, description_zh 等
 * 
 * 使用：
 * php artisan db:seed --class=ExercisesV2Importer
 * 
 * @version 2.0.0
 * @date 2026-01-16
 */
class ExercisesV2Importer extends Seeder 
{
    private $exercisesPath;
    private $stats = [
        'total_exercises' => 0,
        'imported_exercises' => 0,
        'failed_exercises' => 0,
        'media_files' => 0,
    ];
    
    public function __construct()
    {
        $this->exercisesPath = storage_path('app/public/exercises_v2');
    }
    
    public function run(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║  Exercise V2 导入器 - 适配新表结构 (name_en/name_zh)           ║\n";
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
    
    private function validateEnvironment(): void
    {
        echo "📋 验证运行环境...\n";
        
        if (!is_dir($this->exercisesPath)) {
            throw new Exception("exercises_v2目录不存在: {$this->exercisesPath}");
        }
        
        try {
            DB::connection()->getPdo();
            echo "✅ 数据库连接成功\n";
        } catch (Exception $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
        
        $requiredTables = ['exercises', 'exercise_v2_media'];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                throw new Exception("缺少必要的表: {$table}，请先运行迁移");
            }
        }
        
        echo "✅ 环境验证完成\n\n";
    }

    
    private function prepareDatabase(): void
    {
        echo "🗄️ 准备数据库表...\n";
        
        $tables = ['exercise_v2_media', 'exercises'];
        
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
    
    private function importExercises(): void
    {
        echo "🏋️ 开始导入exercises数据...\n";
        
        $exerciseFiles = $this->findAllExerciseFiles();
        $this->stats['total_exercises'] = count($exerciseFiles);
        
        echo "📊 发现 {$this->stats['total_exercises']} 个练习文件\n\n";
        
        $batchSize = 50;
        $batches = array_chunk($exerciseFiles, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            DB::transaction(function () use ($batch, $batchIndex, $batchSize) {
                foreach ($batch as $filePath) {
                    try {
                        $this->importSingleExercise($filePath);
                        $this->stats['imported_exercises']++;
                    } catch (Exception $e) {
                        $this->stats['failed_exercises']++;
                        echo "❌ 导入失败 {$filePath}: " . $e->getMessage() . "\n";
                    }
                }
                
                $currentCount = min(($batchIndex + 1) * $batchSize, $this->stats['total_exercises']);
                echo "✅ 进度: {$currentCount}/{$this->stats['total_exercises']}\n";
            });
        }
        
        echo "\n✅ exercises数据导入完成\n\n";
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
        
        $this->insertExercise($exerciseId, $data);
        $this->importMedia($exerciseId, $data);
    }

    
    /**
     * 插入exercises数据 - 适配新表结构 (name_en/name_zh)
     */
    private function insertExercise(int $exerciseId, array $data): void
    {
        $slug = $data['slug'] ?? Str::slug($data['name_en'] ?? "exercise-{$exerciseId}");
        $uniqueSlug = "{$slug}-{$exerciseId}";
        
        DB::table('exercises')->insertOrIgnore([
            'id' => $exerciseId,
            
            // 基础信息（双语）
            'name_en' => $data['name_en'] ?? "Exercise #{$exerciseId}",
            'name_zh' => $data['name_zh'] ?? null,
            'slug' => $uniqueSlug,
            'description_en' => $data['description_en'] ?? null,
            'description_zh' => $data['description_zh'] ?? null,
            
            // 肌肉信息
            'primary_muscle_en' => $data['muscles_primary_en'][0] ?? null,
            'primary_muscle_zh' => $data['muscles_primary_zh'][0] ?? null,
            'all_muscles_zh' => !empty($data['all_muscles_zh']) ? json_encode($data['all_muscles_zh']) : null,
            
            // 器械信息
            'equipment_en' => $data['equipment_en'] ?? null,
            'equipment_zh' => $data['equipment_zh'] ?? null,
            
            // 难度信息
            'difficulty_en' => $data['difficulty_en'] ?? null,
            'difficulty_zh' => $data['difficulty_zh'] ?? null,
            
            // 力量类型
            'force_en' => $data['force_en'] ?? null,
            'force_zh' => $data['force_zh'] ?? null,
            
            // 动作类型
            'mechanic_en' => $data['mechanic_en'] ?? null,
            'mechanic_zh' => $data['mechanic_zh'] ?? null,
            
            // 握法
            'grips_en' => !empty($data['grips_en']) ? json_encode($data['grips_en']) : null,
            'grips_zh' => !empty($data['grips_zh']) ? json_encode($data['grips_zh']) : null,
            
            // 正确步骤
            'correct_steps_en' => !empty($data['correct_steps_en']) ? json_encode($data['correct_steps_en']) : null,
            'correct_steps_zh' => !empty($data['correct_steps_zh']) ? json_encode($data['correct_steps_zh']) : null,
            
            // 智能标签
            'smart_tags' => !empty($data['smart_tags']) ? json_encode($data['smart_tags']) : null,
            
            // 训练参数
            'rep_range' => $data['rep_range'] ?? null,
            'set_range' => $data['set_range'] ?? null,
            'rest_period' => $data['rest_period'] ?? null,
            'intensity_percentage' => $data['intensity_percentage'] ?? null,
            
            // 安全信息
            'safety_level' => $data['safety_level'] ?? null,
            'safety_pre_check' => !empty($data['safety_pre_check']) ? json_encode($data['safety_pre_check']) : null,
            'equipment_risks' => !empty($data['equipment_risks']) ? json_encode($data['equipment_risks']) : null,
            
            // 技术细节
            'kinetic_chain_type' => $data['kinetic_chain_type'] ?? null,
            'technique_checkpoints' => !empty($data['technique_checkpoints']) ? json_encode($data['technique_checkpoints']) : null,
            'rom_requirements' => !empty($data['rom_requirements']) ? json_encode($data['rom_requirements']) : null,
            
            // 营养建议
            'key_nutrients' => !empty($data['key_nutrients']) ? json_encode($data['key_nutrients']) : null,
            'recommended_foods' => !empty($data['recommended_foods']) ? json_encode($data['recommended_foods']) : null,
            'nutrition_timing' => $data['nutrition_timing'] ?? null,
            
            // 进阶选项
            'progression_options' => !empty($data['progression_options']) ? json_encode($data['progression_options']) : null,
            'regression_options' => !empty($data['regression_options']) ? json_encode($data['regression_options']) : null,
            
            // 分类
            'categories' => !empty($data['categories']) ? json_encode($data['categories']) : null,
            
            // 数据来源
            'data_source' => $data['metadata']['source'] ?? 'musclewiki',
            'source_reference' => null,
            'license_type' => null,
            'original_source' => $data['metadata']['source'] ?? null,
            'last_verified_at' => now(),
            'verified_by' => 'system',
            
            // 变体和关节
            'variation_of' => $data['variation_of'] ?? null,
            'variations' => !empty($data['variations']) ? json_encode($data['variations']) : null,
            'joints' => !empty($data['joints']) ? json_encode($data['joints']) : null,
            
            // 身体图
            'body_map_images' => !empty($data['body_map_images']) ? json_encode($data['body_map_images']) : null,
            'body_map_images_local' => !empty($data['body_map_images_local']) ? json_encode($data['body_map_images_local']) : null,
            
            // 肌肉数组
            'muscles_primary_en' => !empty($data['muscles_primary_en']) ? json_encode($data['muscles_primary_en']) : null,
            'muscles_primary_zh' => !empty($data['muscles_primary_zh']) ? json_encode($data['muscles_primary_zh']) : null,
            'muscles_secondary_en' => !empty($data['muscles_secondary_en']) ? json_encode($data['muscles_secondary_en']) : null,
            'muscles_secondary_zh' => !empty($data['muscles_secondary_zh']) ? json_encode($data['muscles_secondary_zh']) : null,
            
            // 统计信息
            'rating' => 0,
            'view_count' => 0,
            
            // 时间戳
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    
    private function importMedia(int $exerciseId, array $data): void
    {
        $mediaItems = [];
        $exerciseIdPadded = $this->getExerciseIdPath($exerciseId);
        
        $variants = ['male-1', 'male-2', 'female-1', 'female-2'];
        
        foreach ($variants as $index => $variant) {
            $isPrimary = ($index === 0);
            
            $mediaItems[] = [
                'exercise_id' => $exerciseId,
                'media_type' => 'thumbnail',
                'local_path' => "exercises_v2/{$exerciseIdPadded}/thumbnails/{$variant}-thumb.jpg",
                'cdn_url' => null,
                'file_size' => null,
                'duration' => null,
                'display_order' => $index * 3,
            ];
            
            $mediaItems[] = [
                'exercise_id' => $exerciseId,
                'media_type' => 'image',
                'local_path' => "exercises_v2/{$exerciseIdPadded}/images/{$variant}.jpg",
                'cdn_url' => null,
                'file_size' => null,
                'duration' => null,
                'display_order' => $index * 3 + 1,
            ];
            
            $mediaItems[] = [
                'exercise_id' => $exerciseId,
                'media_type' => 'video',
                'local_path' => "exercises_v2/{$exerciseIdPadded}/videos/{$variant}.mp4",
                'cdn_url' => null,
                'file_size' => null,
                'duration' => null,
                'display_order' => $index * 3 + 2,
            ];
        }
        
        DB::table('exercise_v2_media')->insert($mediaItems);
        $this->stats['media_files'] += count($mediaItems);
    }
    
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
        echo "\n";
        echo "✅ 导入成功！\n";
        echo "\n";
    }
}
