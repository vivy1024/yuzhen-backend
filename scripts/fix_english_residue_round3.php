<?php
/**
 * 第三轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第三轮修复英文残留\n\n";

$wordReplacements = [
    // 动词
    'break' => '弯曲',
    'place' => '放置',
    'lifting' => '抬起',
    'Keep' => '保持',
    'keep' => '保持',
    'straighten' => '伸直',
    'Take' => '迈出',
    'take' => '迈出',
    'planting' => '踩稳',
    'stepping' => '迈步',
    'step' => '步',
    'remain' => '保持',
    'lowering' => '降低',
    'using' => '使用',
    'engaged' => '收紧',
    'extended' => '伸展',
    'elevated' => '抬高的',
    'pointed' => '指向',
    
    // 名词
    'body' => '身体',
    'degree' => '度',
    'angle' => '角度',
    'surface' => '表面',
    'upper' => '上',
    'lower' => '下',
    
    // 形容词/副词
    'down' => '向下',
    'up' => '向上',
    'wider' => '更宽',
    'than' => '比',
    'about' => '大约',
    'outward' => '向外',
    'firmly' => '稳固地',
    'large' => '大',
    'big' => '大',
    'sturdy' => '稳固的',
    'opposite' => '对侧',
    'backwards' => '向后',
    
    // 介词
    'toward' => '朝向',
    
    // 专有名词
    'Spoto' => '斯波托',
    'Meadows' => '梅多斯',
    'Kroc' => '克罗克',
    'Helms' => '赫尔姆斯',
    'Tate' => '泰特',
    'JM' => 'JM',
    'Close-Grip' => '窄握',
    'Wide-Grip' => '宽握',
    'Snatch-Grip' => '抓举握法',
    'Clean-Grip' => '翻站握法',
    'Sumo' => '相扑式',
    'Conventional' => '传统式',
    'Stiff-Leg' => '直腿',
    'Single-Leg' => '单腿',
    'Split' => '分腿',
    'Front' => '前',
    'Back' => '后',
    'Overhead' => '过头',
    'Floor' => '地板',
    'Pause' => '暂停',
    'Tempo' => '节奏',
    'Touch-and-Go' => '触地即起',
    'Deficit' => '垫高',
    'Block' => '垫块',
    'Pin' => '插销',
    'Band' => '弹力带',
    'Chain' => '链条',
    'Accommodating' => '适应性',
    'Resistance' => '阻力',
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
