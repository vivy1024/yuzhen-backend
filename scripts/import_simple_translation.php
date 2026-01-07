<?php
/**
 * 导入简化版翻译结果
 * 
 * 读取 translation_simple.json 中的翻译结果，更新源文件
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/import_simple_translation.php
 */

$translationFile = __DIR__ . '/../storage/app/translation_simple.json';
$originalFile = __DIR__ . '/../storage/app/translation_needed.json';
$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "=" . str_repeat("=", 79) . "\n";
echo "导入简化版翻译结果\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 读取翻译结果
$translations = json_decode(file_get_contents($translationFile), true);
if (!$translations) {
    echo "❌ 无法读取翻译文件\n";
    exit(1);
}

// 读取原始数据（获取 ID、字段、索引信息）
$original = json_decode(file_get_contents($originalFile), true);
if (!$original) {
    echo "❌ 无法读取原始文件\n";
    exit(1);
}

echo "📖 读取到 " . count($translations) . " 条翻译\n\n";

// 构建翻译映射
$translationMap = [];
foreach ($translations as $key => $item) {
    if (!empty($item['translation'])) {
        $translationMap[$key] = $item['translation'];
    }
}

echo "📊 有效翻译: " . count($translationMap) . " 条\n\n";

if (count($translationMap) === 0) {
    echo "⚠️ 没有找到有效的翻译结果\n";
    echo "请确保 translation_simple.json 中的 translation 字段已填写\n";
    exit(1);
}

// 获取动作文件路径
function getExerciseFilePath($id, $exercisesDir) {
    $folder1 = sprintf('%04d-%04d', floor($id / 100) * 100, floor($id / 100) * 100 + 99);
    $folder2 = sprintf('%04d-%04d', floor($id / 10) * 10, floor($id / 10) * 10 + 9);
    return "$exercisesDir/$folder1/$folder2/$id/data.json";
}

// 按 ID 分组
$updatesByID = [];
foreach ($original as $item) {
    $id = $item['id'];
    $field = $item['field'];
    $index = $item['index'];
    
    $key = $id . '_' . $field;
    if ($index !== null) {
        $key .= '_' . $index;
    }
    
    if (isset($translationMap[$key])) {
        if (!isset($updatesByID[$id])) {
            $updatesByID[$id] = [];
        }
        $updatesByID[$id][] = [
            'field' => $field,
            'index' => $index,
            'translation' => $translationMap[$key],
        ];
    }
}

echo "📊 涉及 " . count($updatesByID) . " 个动作\n\n";

// 统计
$stats = [
    'files_updated' => 0,
    'fields_updated' => 0,
    'files_not_found' => 0,
];

// 处理每个动作
foreach ($updatesByID as $id => $updates) {
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
    
    foreach ($updates as $update) {
        $field = $update['field'];
        $index = $update['index'];
        $translation = $update['translation'];
        
        // 更新字段
        if ($index !== null) {
            // 数组字段
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field][$index] = $translation;
                $updated = true;
                $stats['fields_updated']++;
            }
        } else {
            // 普通字段
            $data[$field] = $translation;
            $updated = true;
            $stats['fields_updated']++;
        }
    }
    
    // 保存文件
    if ($updated) {
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $stats['files_updated']++;
        echo "✅ ID={$id}: 已更新 " . count($updates) . " 个字段\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "导入完成\n";
echo str_repeat("=", 80) . "\n\n";

echo "更新文件数: {$stats['files_updated']}\n";
echo "更新字段数: {$stats['fields_updated']}\n";
echo "文件未找到: {$stats['files_not_found']}\n";

echo "\n下一步：\n";
echo "1. 运行 docker exec fitness_php_v2 php artisan exercise:sync-data 同步到 MySQL\n";
echo "2. 验证前端显示效果\n";
