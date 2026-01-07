<?php
/**
 * 第十九轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十九轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 动词 ==========
    'Wrap' => '缠绕',
    'wrap' => '缠绕',
    'Elongate' => '伸长',
    'elongate' => '伸长',
    'crossing' => '交叉',
    'rolled' => '卷起的',
    'pausing' => '暂停',
    'absorb' => '吸收',
    'exert' => '施加',
    'supporting' => '支撑',
    'suspended' => '悬空',
    'letting' => '让',
    'Slide' => '滑动',
    'slide' => '滑动',
    
    // ========== 名词 ==========
    'ribbon' => '带子',
    'ends' => '末端',
    'wall' => '墙',
    'towel' => '毛巾',
    'impact' => '冲击',
    'calf' => '小腿',
    'phase' => '阶段',
    'fingers' => '手指',
    'middle' => '中间',
    'complaints' => '不适',
    'Palm' => '手掌',
    
    // ========== 形容词/副词 ==========
    'still' => '仍然',
    'soon' => '一旦',
    'needed' => '需要',
    'muscular' => '肌肉',
    'bare' => '赤裸',
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
