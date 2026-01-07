<?php
/**
 * 同步 body_map_images_local 字段到数据库
 * 从 exercises_v2 JSON文件读取并更新到MySQL
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$exercisesDir = storage_path('app/public/exercises_v2');
$updated = 0;
$skipped = 0;
$errors = 0;

echo "开始同步 body_map_images_local 字段...\n\n";

// 遍历所有范围目录
$ranges = glob($exercisesDir . '/*', GLOB_ONLYDIR);

foreach ($ranges as $rangeDir) {
    $subRanges = glob($rangeDir . '/*', GLOB_ONLYDIR);
    
    foreach ($subRanges as $subRangeDir) {
        $exerciseDirs = glob($subRangeDir . '/*', GLOB_ONLYDIR);
        
        foreach ($exerciseDirs as $exerciseDir) {
            $dataFile = $exerciseDir . '/data.json';
            
            if (!file_exists($dataFile)) {
                continue;
            }
            
            $data = json_decode(file_get_contents($dataFile), true);
            
            if (!$data || !isset($data['id'])) {
                continue;
            }
            
            $id = $data['id'];
            $bodyMapLocal = $data['body_map_images_local'] ?? null;
            
            // 检查是否有有效的本地bodymap数据
            if (empty($bodyMapLocal)) {
                $skipped++;
                continue;
            }
            
            // 检查是否有实际的图片路径
            $hasValidImages = false;
            foreach (['male', 'female'] as $gender) {
                if (isset($bodyMapLocal[$gender])) {
                    foreach (['front', 'back'] as $view) {
                        if (!empty($bodyMapLocal[$gender][$view])) {
                            $hasValidImages = true;
                            break 2;
                        }
                    }
                }
            }
            
            if (!$hasValidImages) {
                $skipped++;
                continue;
            }
            
            try {
                DB::table('exercises')
                    ->where('id', $id)
                    ->update([
                        'body_map_images_local' => json_encode($bodyMapLocal)
                    ]);
                $updated++;
                
                if ($updated % 100 == 0) {
                    echo "已更新 {$updated} 条记录...\n";
                }
            } catch (\Exception $e) {
                $errors++;
                echo "错误 ID {$id}: " . $e->getMessage() . "\n";
            }
        }
    }
}

echo "\n同步完成!\n";
echo "- 更新: {$updated}\n";
echo "- 跳过(无bodymap): {$skipped}\n";
echo "- 错误: {$errors}\n";
