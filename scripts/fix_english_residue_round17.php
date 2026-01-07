<?php
/**
 * 第十七轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十七轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 名词 ==========
    'fours' => '四肢',
    'trainer' => '教练',
    'therapist' => '治疗师',
    'theraband' => '弹力带',
    'strap' => '带子',
    'aid' => '辅助',
    'edge' => '边缘',
    'pain' => '疼痛',
    'pelvic' => '骨盆',
    'tilt' => '倾斜',
    
    // ========== 动词 ==========
    'till' => '直到',
    'performing' => '执行',
    'ask' => '请',
    'guide' => '指导',
    'stretching' => '拉伸',
    'Tug' => '拉',
    'tug' => '拉',
    'balancing' => '平衡',
    'Shift' => '转移',
    'shift' => '转移',
    'aligned' => '对齐',
    'flatten' => '压平',
    'being' => '被',
    
    // ========== 形容词/副词 ==========
    'stiff' => '僵硬',
    'sore' => '酸痛',
    'dynamically' => '动态地',
    'posterior' => '后',
    'maximum' => '最大',
    'good' => '良好',
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
