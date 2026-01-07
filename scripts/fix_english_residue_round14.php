<?php
/**
 * 第十四轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第十四轮修复英文残留\n\n";

$wordReplacements = [
    // ========== 品牌/专有名词 ==========
    'Assault' => '突击',
    'Bike' => '单车',
    'assault' => '突击',
    'bike' => '单车',
    
    // ========== 动词 ==========
    'doing' => '做',
    'jump' => '跳跃',
    'bending' => '弯曲',
    'balanced' => '平衡',
    'Adjust' => '调整',
    'adjust' => '调整',
    'Swing' => '摆动',
    'swing' => '摆动',
    'loading' => '蓄力',
    'explode' => '爆发',
    'land' => '落地',
    'Land' => '落地',
    'initiating' => '开始',
    'strapped' => '固定',
    'Leap' => '跳跃',
    'leap' => '跳跃',
    'placed' => '放置',
    
    // ========== 名词 ==========
    'seat' => '座椅',
    'handlebar' => '把手',
    'comfort' => '舒适',
    'posture' => '姿势',
    'power' => '力量',
    'air' => '空中',
    'burpee' => '波比跳',
    'pole' => '杆',
    'eyes' => '眼睛',
    
    // ========== 形容词/副词 ==========
    'halfway' => '一半',
    'short' => '短',
    'regular' => '常规',
    'explosively' => '爆发性地',
    'proper' => '正确',
    'explosive' => '爆发性',
    'softly' => '轻柔地',
    'only' => '仅',
    'around' => '绕',
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
