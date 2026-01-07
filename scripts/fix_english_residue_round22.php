<?php
/**
 * 第二十二轮修复英文残留 - 最后一批
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第二十二轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 动词 ==========
    'recommended' => '推荐的',
    'engagement' => '收紧',
    'tell' => '判断',
    'squatting' => '深蹲',
    'finishing' => '完成',
    'flexed' => '弯曲',
    'rests' => '停靠',
    'Brace' => '收紧',
    'brace' => '收紧',
    'ensuring' => '确保',
    'adjusting' => '调整',
    'done' => '完成',
    
    // ========== 名词 ==========
    'hooks' => '挂钩',
    'length' => '长度',
    'apex' => '顶点',
    'hack' => '哈克',
    'Gluteator' => '臀肌训练器',
    'cushion' => '靠垫',
    
    // ========== 形容词/副词 ==========
    'slow' => '缓慢',
    'again' => '再次',
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
