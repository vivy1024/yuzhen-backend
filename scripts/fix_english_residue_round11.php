<?php
/**
 * 第十一轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十一轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 形容词/副词 ==========
    'underneath' => '在下方',
    'fixed' => '固定的',
    'horizontal' => '水平',
    'sideways' => '侧向',
    'easy' => '曲杆',
    'near' => '靠近',
    'given' => '指定',
    
    // ========== 动词 ==========
    'Switch' => '切换',
    'vary' => '变化',
    'prefer' => '偏好',
    'leaving' => '保持',
    'hovering' => '悬停',
    'returning' => '返回',
    'getting' => '变得',
    'pitched' => '倾斜',
    'altering' => '改变',
    'completing' => '完成',
    'leaning' => '倾斜',
    'meet' => '碰到',
    'Walk' => '走',
    'walk' => '走',
    
    // ========== 名词 ==========
    'steps' => '步',
    'direction' => '方向',
    'abdomen' => '腹部',
    'plates' => '杠铃片',
    
    // ========== 代词/介词 ==========
    'Some' => '有些人',
    'Others' => '其他人',
    'beside' => '在旁边',
    'where' => '到',
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
