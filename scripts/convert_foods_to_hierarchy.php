<?php
/**
 * 将 merged_*.json 食物数据转换为分级文件结构
 * 
 * 输入: storage/app/public/nutrition/core/merged_*.json
 * 输出: storage/app/public/foods/{id}/data.json
 * 
 * 目录结构（类似动作库）:
 * foods/
 * ├── 0000-0099/
 * │   ├── 0000-0009/
 * │   │   ├── 1/data.json
 * │   │   └── ...
 * │   └── 0010-0019/
 * └── ...
 * 
 * 使用方式: docker exec fitness_php_v2 php /var/www/html/scripts/convert_foods_to_hierarchy.php
 */

$sourceDir = __DIR__ . '/../storage/app/public/nutrition/core/';
$targetDir = __DIR__ . '/../storage/app/public/foods/';

// 确保目标目录存在
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// 获取所有 merged_*.json 文件
$files = glob($sourceDir . 'merged_*.json');
echo "Found " . count($files) . " merged files\n\n";

$allFoods = [];
$id = 1;

// 解析文件名获取分类信息
// 格式: merged_大类-小类.json 或 merged_大类.json
function parseCategoryFromFilename(string $filename): array
{
    // 移除 merged_ 前缀和 .json 后缀
    $name = preg_replace('/^merged_/', '', basename($filename));
    $name = preg_replace('/\.json$/', '', $name);
    
    // 按 - 分割
    $parts = explode('-', $name);
    
    if (count($parts) >= 2) {
        return [
            'category' => $parts[0],
            'subcategory' => implode('-', array_slice($parts, 1))
        ];
    }
    
    return [
        'category' => $name,
        'subcategory' => null
    ];
}

// 解析数值，处理 "Tr"、"—" 等特殊值
function parseNumericValue($value): ?float
{
    if ($value === null || $value === '' || $value === '—' || $value === 'Tr' || $value === '…') {
        return null;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }
    return null;
}

// 获取分级目录路径
function getHierarchyPath(int $id): string
{
    $level1Start = floor($id / 100) * 100;
    $level1End = $level1Start + 99;
    $level1 = sprintf('%04d-%04d', $level1Start, $level1End);
    
    $level2Start = floor($id / 10) * 10;
    $level2End = $level2Start + 9;
    $level2 = sprintf('%04d-%04d', $level2Start, $level2End);
    
    return "{$level1}/{$level2}/{$id}";
}

// 处理每个文件
foreach ($files as $file) {
    $categoryInfo = parseCategoryFromFilename($file);
    $content = file_get_contents($file);
    $foods = json_decode($content, true);
    
    if (!is_array($foods)) {
        echo "Warning: Failed to parse {$file}\n";
        continue;
    }
    
    echo "Processing: " . basename($file) . " ({$categoryInfo['category']}/{$categoryInfo['subcategory']}) - " . count($foods) . " items\n";
    
    foreach ($foods as $food) {
        // 构建标准化的食物数据
        $foodData = [
            'id' => $id,
            'food_code' => $food['foodCode'] ?? null,
            'name' => $food['foodName'] ?? '未知',
            'category' => $categoryInfo['category'],
            'subcategory' => $categoryInfo['subcategory'],
            
            // 基础营养素
            'edible' => parseNumericValue($food['edible'] ?? 100),
            'water' => parseNumericValue($food['water'] ?? null),
            'energy_kcal' => parseNumericValue($food['energyKCal'] ?? null),
            'energy_kj' => parseNumericValue($food['energyKJ'] ?? null),
            'protein' => parseNumericValue($food['protein'] ?? null),
            'fat' => parseNumericValue($food['fat'] ?? null),
            'carbohydrate' => parseNumericValue($food['CHO'] ?? null),
            'dietary_fiber' => parseNumericValue($food['dietaryFiber'] ?? null),
            'cholesterol' => parseNumericValue($food['cholesterol'] ?? null),
            'ash' => parseNumericValue($food['ash'] ?? null),
            
            // 维生素
            'vitamin_a' => parseNumericValue($food['vitaminA'] ?? null),
            'carotene' => parseNumericValue($food['carotene'] ?? null),
            'retinol' => parseNumericValue($food['retinol'] ?? null),
            'thiamin' => parseNumericValue($food['thiamin'] ?? null),
            'riboflavin' => parseNumericValue($food['riboflavin'] ?? null),
            'niacin' => parseNumericValue($food['niacin'] ?? null),
            'vitamin_c' => parseNumericValue($food['vitaminC'] ?? null),
            'vitamin_e_total' => parseNumericValue($food['vitaminETotal'] ?? null),
            
            // 矿物质
            'calcium' => parseNumericValue($food['Ca'] ?? null),
            'phosphorus' => parseNumericValue($food['P'] ?? null),
            'potassium' => parseNumericValue($food['K'] ?? null),
            'sodium' => parseNumericValue($food['Na'] ?? null),
            'magnesium' => parseNumericValue($food['Mg'] ?? null),
            'iron' => parseNumericValue($food['Fe'] ?? null),
            'zinc' => parseNumericValue($food['Zn'] ?? null),
            'selenium' => parseNumericValue($food['Se'] ?? null),
            'copper' => parseNumericValue($food['Cu'] ?? null),
            'manganese' => parseNumericValue($food['Mn'] ?? null),
            
            // 扩展信息
            'remark' => $food['remark'] ?? null,
            
            // 元数据
            'metadata' => [
                'source' => '中国食物成分表',
                'version' => '1.0.0',
                'created_at' => date('Y-m-d H:i:s'),
                'storage_path' => getHierarchyPath($id),
                'original_file' => basename($file),
            ],
        ];
        
        // 创建目录
        $path = getHierarchyPath($id);
        $fullPath = $targetDir . $path;
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        
        // 写入文件
        $jsonContent = json_encode($foodData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($fullPath . '/data.json', $jsonContent);
        
        $allFoods[] = $foodData;
        $id++;
    }
}

echo "\n========================================\n";
echo "Total foods converted: " . count($allFoods) . "\n";
echo "Output directory: {$targetDir}\n";

// 生成汇总文件
$summaryFile = $targetDir . 'foods_summary.json';
$summary = [
    'total_count' => count($allFoods),
    'generated_at' => date('Y-m-d H:i:s'),
    'source' => 'merged_*.json from 中国食物成分表',
    'categories' => [],
];

// 统计分类
foreach ($allFoods as $food) {
    $cat = $food['category'];
    if (!isset($summary['categories'][$cat])) {
        $summary['categories'][$cat] = [
            'count' => 0,
            'subcategories' => []
        ];
    }
    $summary['categories'][$cat]['count']++;
    
    $subcat = $food['subcategory'];
    if ($subcat) {
        if (!isset($summary['categories'][$cat]['subcategories'][$subcat])) {
            $summary['categories'][$cat]['subcategories'][$subcat] = 0;
        }
        $summary['categories'][$cat]['subcategories'][$subcat]++;
    }
}

file_put_contents($summaryFile, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Summary file: {$summaryFile}\n";
