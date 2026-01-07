<?php
/**
 * 导入翻译结果到源文件
 * 
 * 读取 translation_result.json，更新源文件中的中文字段
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/import_translations.php
 */

$inputFile = __DIR__ . '/../storage/app/translation_result.json';
$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "=" . str_repeat("=", 79) . "\n";
echo "导入翻译结果到源文件\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 检查输入文件
if (!file_exists($inputFile)) {
    echo "❌ 翻译结果文件不存在: $inputFile\n";
    echo "请先运行 translate_with_ollama.php 或手动创建翻译结果文件\n";
    exit(1);
}

// 读取翻译结果
$translations = json_decode(file_get_contents($inputFile), true);
if (!$translations) {
    echo "❌ 无法解析翻译结果文件\n";
    exit(1);
}

echo "📖 读取到 " . count($translations) . " 条翻译结果\n\n";

// 按 ID 分组翻译结果
$translationsByID = [];
foreach ($translations as $item) {
    $id = $item['id'];
    if (!isset($translationsByID[$id])) {
        $translationsByID[$id] = [];
    }
    $translationsByID[$id][] = $item;
}

echo "📊 涉及 " . count($translationsByID) . " 个动作\n\n";

// 获取动作文件路径
function getExerciseFilePath($id, $exercisesDir) {
    $folder1 = sprintf('%04d-%04d', floor($id / 100) * 100, floor($id / 100) * 100 + 99);
    $folder2 = sprintf('%04d-%04d', floor($id / 10) * 10, floor($id / 10) * 10 + 9);
    return "$exercisesDir/$folder1/$folder2/$id/data.json";
}

// 统计
$stats = [
    'files_updated' => 0,
    'fields_updated' => 0,
    'files_not_found' => 0,
    'translation_missing' => 0,
];

// 处理每个动作
foreach ($translationsByID as $id => $items) {
    $filePath = getExerciseFilePath($id, $exercisesDir);
    
    if (!file_exists($filePath)) {
        echo "⚠️ ID={$id}: 文件不存在\n";
        $stats['files_not_found']++;
        continue;
    }
    
    // 读取源文件
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data) {
        echo "⚠️ ID={$id}: 无法解析文件\n";
        continue;
    }
    
    $updated = false;
    
    foreach ($items as $item) {
        $field = $item['field'];
        $index = $item['index'];
        $translatedZh = $item['translated_zh'] ?? null;
        
        if (!$translatedZh) {
            $stats['translation_missing']++;
            continue;
        }
        
        // 更新字段
        if ($index !== null) {
            // 数组字段（如 correct_steps_zh）
            if (isset($data[$field]) && is_array($data[$field]) && isset($data[$field][$index])) {
                $data[$field][$index] = $translatedZh;
                $updated = true;
                $stats['fields_updated']++;
            }
        } else {
            // 普通字段
            $data[$field] = $translatedZh;
            $updated = true;
            $stats['fields_updated']++;
        }
    }
    
    // 保存文件
    if ($updated) {
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $stats['files_updated']++;
        echo "✅ ID={$id}: 已更新\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "导入完成\n";
echo str_repeat("=", 80) . "\n\n";

echo "更新文件数: {$stats['files_updated']}\n";
echo "更新字段数: {$stats['fields_updated']}\n";
echo "文件未找到: {$stats['files_not_found']}\n";
echo "翻译缺失: {$stats['translation_missing']}\n";

echo "\n下一步：\n";
echo "1. 运行 docker exec fitness_php_v2 php artisan exercise:sync-data 同步到 MySQL\n";
echo "2. 验证前端显示效果\n";
