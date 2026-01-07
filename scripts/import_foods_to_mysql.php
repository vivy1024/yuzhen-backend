<?php
/**
 * 从分级文件结构导入食物数据到MySQL
 * 
 * 使用方式: docker exec fitness_php_v2 php /var/www/html/scripts/import_foods_to_mysql.php
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$foodsDir = __DIR__ . '/../storage/app/public/foods/';

echo "========================================\n";
echo "食物数据导入工具\n";
echo "========================================\n\n";

// 检查表是否存在
try {
    $tableExists = DB::select("SHOW TABLES LIKE 'foods'");
    if (empty($tableExists)) {
        echo "Error: foods 表不存在，请先运行迁移\n";
        echo "执行: docker exec fitness_php_v2 php artisan migrate\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "Error: 数据库连接失败 - " . $e->getMessage() . "\n";
    exit(1);
}

// 获取所有食物文件
$foods = [];
$directories = glob($foodsDir . '*-*', GLOB_ONLYDIR);

foreach ($directories as $level1Dir) {
    $level2Dirs = glob($level1Dir . '/*-*', GLOB_ONLYDIR);
    foreach ($level2Dirs as $level2Dir) {
        $foodDirs = glob($level2Dir . '/*', GLOB_ONLYDIR);
        foreach ($foodDirs as $foodDir) {
            $dataFile = $foodDir . '/data.json';
            if (file_exists($dataFile)) {
                $content = file_get_contents($dataFile);
                $food = json_decode($content, true);
                if ($food) {
                    $foods[] = $food;
                }
            }
        }
    }
}

echo "Found " . count($foods) . " foods to import\n\n";

if (empty($foods)) {
    echo "No foods found. Please run convert_foods_to_hierarchy.php first.\n";
    exit(1);
}

// 清空现有数据
echo "Clearing existing data...\n";
DB::table('foods')->truncate();

// 批量插入
$batchSize = 100;
$batches = array_chunk($foods, $batchSize);
$imported = 0;

echo "Importing foods...\n";

foreach ($batches as $index => $batch) {
    $insertData = [];
    
    foreach ($batch as $food) {
        $insertData[] = [
            'id' => $food['id'],
            'food_code' => $food['food_code'],
            'name' => $food['name'],
            'category' => $food['category'],
            'subcategory' => $food['subcategory'],
            'edible' => $food['edible'] ?? 100, // 默认100%可食用
            'water' => $food['water'],
            'energy_kcal' => $food['energy_kcal'],
            'energy_kj' => $food['energy_kj'],
            'protein' => $food['protein'],
            'fat' => $food['fat'],
            'carbohydrate' => $food['carbohydrate'],
            'dietary_fiber' => $food['dietary_fiber'],
            'cholesterol' => $food['cholesterol'],
            'ash' => $food['ash'],
            'vitamin_a' => $food['vitamin_a'],
            'carotene' => $food['carotene'],
            'retinol' => $food['retinol'],
            'thiamin' => $food['thiamin'],
            'riboflavin' => $food['riboflavin'],
            'niacin' => $food['niacin'],
            'vitamin_c' => $food['vitamin_c'],
            'vitamin_e_total' => $food['vitamin_e_total'],
            'calcium' => $food['calcium'],
            'phosphorus' => $food['phosphorus'],
            'potassium' => $food['potassium'],
            'sodium' => $food['sodium'],
            'magnesium' => $food['magnesium'],
            'iron' => $food['iron'],
            'zinc' => $food['zinc'],
            'selenium' => $food['selenium'],
            'copper' => $food['copper'],
            'manganese' => $food['manganese'],
            'remark' => $food['remark'] === '—' ? null : $food['remark'],
            'gi_value' => null,
            'price_level' => null,
            'view_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    
    DB::table('foods')->insert($insertData);
    $imported += count($batch);
    
    echo "  Batch " . ($index + 1) . "/" . count($batches) . " - Imported: $imported\n";
}

echo "\n========================================\n";
echo "Import completed!\n";
echo "Total imported: $imported foods\n";
echo "========================================\n";

// 验证
$count = DB::table('foods')->count();
echo "\nVerification: $count records in database\n";

// 显示分类统计
echo "\nCategory statistics:\n";
$categories = DB::table('foods')
    ->select('category', DB::raw('COUNT(*) as count'))
    ->groupBy('category')
    ->orderBy('count', 'desc')
    ->get();

foreach ($categories as $cat) {
    echo "  {$cat->category}: {$cat->count}\n";
}
