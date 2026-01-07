<?php
/**
 * 第十五轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十五轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 动词 ==========
    'touching' => '触碰',
    'starting' => '起始',
    'rocking' => '摇晃',
    'attached' => '连接',
    'sitting' => '坐',
    'remove' => '消除',
    'feels' => '感觉',
    'flaring' => '外展',
    'grounded' => '支撑',
    'followed' => '跟随',
    'adding' => '增加',
    
    // ========== 名词 ==========
    'tension' => '张力',
    'pike' => '屈体',
    'key' => '关键',
    'forth' => '前后',
    'balls' => '球',
    'string' => '绳子',
    'waist' => '腰部',
    'second' => '秒',
    'rear' => '后',
    'weights' => '重量',
    'TIP' => '提示',
    
    // ========== 形容词/副词 ==========
    'tall' => '挺直',
    'harder' => '更难',
    'what' => '什么',
    'like' => '像',
];

$stats = ['files_scanned' => 0, 'files_updated' => 0, 'replacements' => 0];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($exercisesDir));

foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $stats['files_scanned']++;
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data) continue;
    
    $modified = false;
    $fieldsToCheck = ['name_zh', 'description_zh', 'correct_steps_zh', 'equipment_zh', 'primary_muscle_zh'];
    
    foreach ($fieldsToCheck as $field) {
        if (!isset($data[$field])) continue;
        
        if (is_array($data[$field])) {
            foreach ($data[$field] as $index => $value) {
                if (!is_string($value)) continue;
                $newValue = $value;
                foreach ($wordReplacements as $en => $zh) {
                    $pattern = '/\b' . preg_quote($en, '/') . '\b/i';
                    if (preg_match($pattern, $newValue)) {
                        $newValue = preg_replace($pattern, $zh, $newValue);
                        $stats['replacements']++;
                    }
                }
                $newValue = preg_replace('/\s+/', ' ', trim($newValue));
                if ($newValue !== $value) {
                    $data[$field][$index] = $newValue;
                    $modified = true;
                }
            }
        } else {
            $newValue = $data[$field];
            foreach ($wordReplacements as $en => $zh) {
                $pattern = '/\b' . preg_quote($en, '/') . '\b/i';
                if (preg_match($pattern, $newValue)) {
                    $newValue = preg_replace($pattern, $zh, $newValue);
                    $stats['replacements']++;
                }
            }
            $newValue = preg_replace('/\s+/', ' ', trim($newValue));
            if ($newValue !== $data[$field]) {
                $data[$field] = $newValue;
                $modified = true;
            }
        }
    }
    
    if ($modified) {
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $stats['files_updated']++;
    }
}

echo "扫描: {$stats['files_scanned']}, 更新: {$stats['files_updated']}, 替换: {$stats['replacements']}\n";
