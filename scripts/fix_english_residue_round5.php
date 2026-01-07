<?php
/**
 * 第五轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第五轮修复英文残留\n\n";

$wordReplacements = [
    // 动词
    'pushing' => '推',
    'dip' => '下沉',
    'quickly' => '快速',
    'pick' => '拿起',
    'Pick' => '拿起',
    'locking' => '锁定',
    'maintaining' => '保持',
    'pivot' => '旋转',
    'generating' => '产生',
    'Drive' => '驱动',
    'drive' => '驱动',
    'making' => '确保',
    'pointing' => '指向',
    'planted' => '放置',
    'held' => '握住',
    'stacked' => '叠放',
    
    // 名词
    'plate' => '杠铃片',
    'palms' => '手掌',
    'manner' => '方式',
    'control' => '控制',
    'shape' => '形状',
    'banana' => '香蕉',
    'torque' => '扭矩',
    
    // 形容词/副词
    'mostly' => '大部分',
    'briefly' => '短暂地',
    'kneeling' => '跪姿',
    'too' => '太',
    
    // 专有名词
    'Rapunzel' => '长发公主',
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
