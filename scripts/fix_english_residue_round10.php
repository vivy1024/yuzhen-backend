<?php
/**
 * 第十轮修复英文残留 - 基于最新扫描报告
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 介词/副词 ==========
    'just' => '刚好',
    'against' => '靠着',
    'upon' => '在...上',
    'without' => '不要',
    'far' => '远',
    'whole' => '整个',
    'higher' => '更高',
    'original' => '原始',
    
    // ========== 名词 ==========
    'ankles' => '脚踝',
    'amount' => '量',
    'time' => '时间',
    'count' => '计数',
    'rack' => '架位',
    'degrees' => '度',
    'glute' => '臀肌',
    'pads' => '垫',
    
    // ========== 动词 ==========
    'allotted' => '分配的',
    'braced' => '收紧',
    'moving' => '移动的',
    'working' => '工作的',
    'Think' => '想象',
    'think' => '想象',
    'Lean' => '靠',
    'lean' => '靠',
    'driving' => '驱动',
    'extending' => '伸展',
    'flare' => '外展',
    'locked' => '锁定',
    'lock' => '锁定',
    'mid' => '中',
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
