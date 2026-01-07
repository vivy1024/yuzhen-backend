<?php
/**
 * 统计食物数据文件
 */

$dir = __DIR__ . '/../storage/app/public/nutrition/core/';
$total = 0;
$files = glob($dir . 'merged_*.json');

echo "Found " . count($files) . " merged files\n\n";

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    $count = is_array($data) ? count($data) : 0;
    $total += $count;
    echo basename($file) . ': ' . $count . "\n";
}

echo "\nTotal foods in merged files: $total\n";

// 检查原来的三个文件
echo "\n--- Original files ---\n";
$originalFiles = [
    'chinese_nutrition_db.json',
    'chinese_dietary_guidelines.json', 
    'glycemic_index_of_foods.json'
];

foreach ($originalFiles as $filename) {
    $filepath = $dir . $filename;
    if (file_exists($filepath)) {
        $data = json_decode(file_get_contents($filepath), true);
        $size = filesize($filepath);
        echo "$filename: " . round($size/1024, 1) . " KB\n";
        
        // 统计内容
        if ($filename === 'chinese_nutrition_db.json' && isset($data['chinese_foods_db'])) {
            $foodCount = 0;
            foreach ($data['chinese_foods_db'] as $category => $foods) {
                $foodCount += count($foods);
            }
            echo "  -> Contains $foodCount foods in " . count($data['chinese_foods_db']) . " categories\n";
        }
    }
}
