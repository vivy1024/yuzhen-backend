<?php
/**
 * 第七轮修复英文残留 - 更多词汇
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第七轮修复英文残留\n\n";

$wordReplacements = [
    // 瑜伽体式名
    'Urdhva' => '上',
    'Dhanurasana' => '弓式',
    'downward' => '下犬式',
    'dog' => '',
    'mountain' => '山式',
    'walking' => '行走',
    
    // 器械名
    'Bosu' => '波速球',
    'BOSU' => '波速球',
    'Ball' => '球',
    'Sissy' => '西西',
    
    // 动词
    'Allow' => '让',
    'allow' => '让',
    'hang' => '悬垂',
    'feel' => '感受',
    'Relax' => '放松',
    'relax' => '放松',
    'turn' => '转',
    'fold' => '折叠',
    'come' => '回来',
    
    // 名词
    'heart' => '心脏',
    'head' => '头部',
    'shins' => '小腿',
    'mat' => '垫子',
    'deep' => '深',
    
    // 形容词/副词
    'heavy' => '沉重',
    'inward' => '向内',
    'beneath' => '在下面',
    
    // 其他
    'desired' => '目标',
    'number' => '数量',
    'before' => '之前',
    'switching' => '切换',
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
