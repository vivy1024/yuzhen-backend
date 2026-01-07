<?php
/**
 * 导出需要翻译的英文残留内容
 * 
 * 输出格式：JSON，包含 id、字段名、原文、英文原文（用于翻译参考）
 * 
 * 运行方式：
 * docker exec fitness_php_v2 php scripts/export_for_translation.php
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';

// 英文单词检测
function hasEnglishWords($text) {
    if (empty($text)) return false;
    
    $excludePatterns = [
        'ROM', 'TRX', 'HIIT', 'BMI', 'RPE', 'RM', 'PR', 'PB',
        'kg', 'lb', 'cm', 'mm', 'ml', 'sec', 'min',
        'vs', 'etc', 'OK', 'ok', 'N/A', 'n/a', 'Ty'
    ];
    
    preg_match_all('/\b[A-Za-z]{3,}\b/', $text, $matches);
    
    if (empty($matches[0])) return false;
    
    $englishWords = array_filter($matches[0], function($word) use ($excludePatterns) {
        return !in_array($word, $excludePatterns) && !in_array(strtoupper($word), array_map('strtoupper', $excludePatterns));
    });
    
    return !empty($englishWords);
}

$toTranslate = [];
$total = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data || !isset($data['id'])) continue;
    
    $total++;
    $id = $data['id'];
    
    // 检查 correct_steps_zh
    if (!empty($data['correct_steps_zh']) && is_array($data['correct_steps_zh'])) {
        foreach ($data['correct_steps_zh'] as $index => $step) {
            if (hasEnglishWords($step)) {
                // 获取对应的英文原文
                $englishStep = $data['correct_steps_en'][$index] ?? '';
                $toTranslate[] = [
                    'id' => $id,
                    'field' => 'correct_steps_zh',
                    'index' => $index,
                    'current_zh' => $step,
                    'english_source' => $englishStep
                ];
            }
        }
    }
    
    // 检查 name_zh
    if (!empty($data['name_zh']) && hasEnglishWords($data['name_zh'])) {
        $toTranslate[] = [
            'id' => $id,
            'field' => 'name_zh',
            'index' => null,
            'current_zh' => $data['name_zh'],
            'english_source' => $data['name_en'] ?? $data['name'] ?? ''
        ];
    }
    
    // 检查 equipment_zh
    if (!empty($data['equipment_zh']) && hasEnglishWords($data['equipment_zh'])) {
        $toTranslate[] = [
            'id' => $id,
            'field' => 'equipment_zh',
            'index' => null,
            'current_zh' => $data['equipment_zh'],
            'english_source' => $data['equipment_en'] ?? $data['equipment'] ?? ''
        ];
    }
    
    // 检查 primary_muscle_zh
    if (!empty($data['primary_muscle_zh']) && hasEnglishWords($data['primary_muscle_zh'])) {
        $toTranslate[] = [
            'id' => $id,
            'field' => 'primary_muscle_zh',
            'index' => null,
            'current_zh' => $data['primary_muscle_zh'],
            'english_source' => $data['primary_muscle_en'] ?? ''
        ];
    }
    
    // 检查 description_zh（清理HTML后再检查）
    if (!empty($data['description_zh']) && hasEnglishWords($data['description_zh'])) {
        $toTranslate[] = [
            'id' => $id,
            'field' => 'description_zh',
            'index' => null,
            'current_zh' => $data['description_zh'],
            'english_source' => $data['description_en'] ?? ''
        ];
    }
    
    if ($total % 200 === 0) {
        echo "已扫描: {$total} 个文件\n";
    }
}

// 按字段分组统计
$byField = [];
foreach ($toTranslate as $item) {
    $byField[$item['field']] = ($byField[$item['field']] ?? 0) + 1;
}

echo "\n=== 导出完成 ===\n";
echo "总文件数: {$total}\n";
echo "需翻译条目: " . count($toTranslate) . "\n\n";

echo "=== 按字段统计 ===\n";
foreach ($byField as $field => $count) {
    echo "  {$field}: {$count}\n";
}

// 保存导出文件
$outputPath = '/var/www/html/storage/app/translation_needed.json';
file_put_contents($outputPath, json_encode($toTranslate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n导出文件: {$outputPath}\n";

// 同时生成一个简化版本，只包含需要翻译的英文原文
$simpleExport = [];
foreach ($toTranslate as $item) {
    if (!empty($item['english_source'])) {
        $key = $item['id'] . '_' . $item['field'] . ($item['index'] !== null ? '_' . $item['index'] : '');
        $simpleExport[$key] = [
            'id' => $item['id'],
            'field' => $item['field'],
            'index' => $item['index'],
            'to_translate' => $item['english_source']
        ];
    }
}

$simpleOutputPath = '/var/www/html/storage/app/translation_source.json';
file_put_contents($simpleOutputPath, json_encode($simpleExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "简化导出: {$simpleOutputPath}\n";
