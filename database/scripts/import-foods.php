<?php
/**
 * 食物库数据导入脚本
 * 从 storage/app/public/foods/ 目录的 JSON 文件批量导入到 foods 表
 * 
 * 使用方式: docker exec fitness_php_v2 php artisan tinker < database/scripts/import-foods.php
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$basePath = storage_path('app/public/foods');
$imported = 0;
$errors = 0;

// 获取 foods_summary.json 确认总数
$summaryPath = $basePath . '/foods_summary.json';
if (file_exists($summaryPath)) {
    $summary = json_decode(file_get_contents($summaryPath), true);
    echo "食物库总数据: " . ($summary['total_count'] ?? '未知') . " 条\n";
}

// 遍历所有批次目录
$batchDirs = glob($basePath . '/[0-9]*');
sort($batchDirs);

foreach ($batchDirs as $batchDir) {
    $subDirs = glob($batchDir . '/[0-9]*');
    sort($subDirs);
    
    foreach ($subDirs as $subDir) {
        $itemDirs = glob($subDir . '/[0-9]*');
        sort($itemDirs, SORT_NUMERIC);
        
        foreach ($itemDirs as $itemDir) {
            $jsonFile = $itemDir . '/data.json';
            if (!file_exists($jsonFile)) continue;
            
            $data = json_decode(file_get_contents($jsonFile), true);
            if (!$data || !isset($data['name'])) {
                $errors++;
                continue;
            }
            
            try {
                DB::table('foods')->updateOrInsert(
                    ['food_code' => $data['food_code']],
                    [
                        'name' => $data['name'],
                        'category' => $data['category'] ?? null,
                        'subcategory' => $data['subcategory'] ?? null,
                        'edible' => $data['edible'] ?? 100,
                        'water' => $data['water'] ?? null,
                        'energy_kcal' => $data['energy_kcal'] ?? null,
                        'energy_kj' => $data['energy_kj'] ?? null,
                        'protein' => $data['protein'] ?? null,
                        'fat' => $data['fat'] ?? null,
                        'carbohydrate' => $data['carbohydrate'] ?? null,
                        'dietary_fiber' => $data['dietary_fiber'] ?? null,
                        'cholesterol' => $data['cholesterol'] ?? null,
                        'ash' => $data['ash'] ?? null,
                        'vitamin_a' => $data['vitamin_a'] ?? null,
                        'carotene' => $data['carotene'] ?? null,
                        'retinol' => $data['retinol'] ?? null,
                        'thiamin' => $data['thiamin'] ?? null,
                        'riboflavin' => $data['riboflavin'] ?? null,
                        'niacin' => $data['niacin'] ?? null,
                        'vitamin_c' => $data['vitamin_c'] ?? null,
                        'vitamin_e_total' => $data['vitamin_e_total'] ?? null,
                        'calcium' => $data['calcium'] ?? null,
                        'phosphorus' => $data['phosphorus'] ?? null,
                        'potassium' => $data['potassium'] ?? null,
                        'sodium' => $data['sodium'] ?? null,
                        'magnesium' => $data['magnesium'] ?? null,
                        'iron' => $data['iron'] ?? null,
                        'zinc' => $data['zinc'] ?? null,
                        'selenium' => $data['selenium'] ?? null,
                        'copper' => $data['copper'] ?? null,
                        'manganese' => $data['manganese'] ?? null,
                        'remark' => $data['remark'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    echo "错误 [{$data['name']}]: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    // 每个大批次输出进度
    $batchName = basename($batchDir);
    echo "批次 {$batchName} 完成，累计导入: {$imported}\n";
}

echo "\n=== 导入完成 ===\n";
echo "成功: {$imported} 条\n";
echo "失败: {$errors} 条\n";
echo "数据库当前总数: " . DB::table('foods')->count() . " 条\n";
