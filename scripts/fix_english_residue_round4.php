<?php
/**
 * 第四轮修复英文残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第四轮修复英文残留\n\n";

$wordReplacements = [
    // 动词
    'Bring' => '带',
    'bring' => '带',
    'Stop' => '停止',
    'stop' => '停止',
    'Grab' => '抓住',
    'grab' => '抓住',
    'Try' => '尝试',
    'try' => '尝试',
    'let' => '让',
    'Let' => '让',
    'Continue' => '继续',
    'continue' => '继续',
    'maintain' => '保持',
    'Maintain' => '保持',
    'stabilize' => '稳定',
    'drawing' => '收紧',
    'help' => '帮助',
    'reach' => '到达',
    'reaches' => '到达',
    'touches' => '触碰',
    'touch' => '触碰',
    'switching' => '切换',
    'remains' => '保持',
    'flair' => '外展',
    'flared' => '外展',
    'going' => '移动',
    
    // 名词
    'belly' => '腹部',
    'button' => '按钮',
    'joints' => '关节',
    'joint' => '关节',
    'variation' => '变式',
    'number' => '数量',
    'moment' => '片刻',
    
    // 形容词/副词
    'nearly' => '几乎',
    'slight' => '轻微',
    'deeply' => '深深地',
    'comfortable' => '舒适',
    'easier' => '更容易',
    'much' => '更',
    'tucked' => '收紧',
    'desired' => '目标',
    'throughout' => '整个过程中',
    'another' => '另一个',
    'before' => '在...之前',
    'Once' => '一旦',
    'once' => '一旦',
    'more' => '更',
    'will' => '会',
    
    // 其他
    'it\'s' => '它',
    'its' => '它的',
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
                    $pattern = '/\b' . preg_quote($en, '/') . '\b/';
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
                $pattern = '/\b' . preg_quote($en, '/') . '\b/';
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
