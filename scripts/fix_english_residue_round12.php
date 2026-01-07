<?php
/**
 * 第十二轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十二轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 名词 ==========
    'rope' => '绳索',
    'thumb' => '拇指',
    'pinky' => '小指',
    'anchor' => '锚点',
    'contraction' => '收缩',
    'midline' => '中线',
    
    // ========== 动词 ==========
    'Imagine' => '想象',
    'imagine' => '想象',
    'leave' => '保持',
    'fall' => '落入',
    'Abduct' => '外展',
    'abduct' => '外展',
    'adduct' => '内收',
    'stay' => '保持',
    'move' => '移动',
    
    // ========== 副词/代词 ==========
    'somewhat' => '稍微',
    'there' => '那里',
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
