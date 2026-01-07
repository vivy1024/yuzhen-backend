<?php
/**
 * 第六轮修复英文残留 - 瑜伽和健身相关词汇
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第六轮修复英文残留\n\n";

$wordReplacements = [
    // 瑜伽相关
    'pose' => '体式',
    'Pose' => '体式',
    'breaths' => '呼吸',
    'breath' => '呼吸',
    'tabletop' => '桌面式',
    'Tadasana' => '山式',
    'Crescent' => '新月',
    'Moon' => '月亮',
    'prayer' => '祈祷',
    
    // 动词
    'switch' => '切换',
    'sides' => '两侧',
    'reach' => '伸展',
    'Reach' => '伸展',
    'Look' => '看',
    'look' => '看',
    'Tuck' => '收',
    'tuck' => '收',
    'spread' => '展开',
    'try' => '尝试',
    'roll' => '滚动',
    'grab' => '抓住',
    'Grab' => '抓住',
    'hinge' => '髋屈',
    'Gently' => '轻轻地',
    'gently' => '轻轻地',
    'pressing' => '按压',
    'deepen' => '加深',
    'releasing' => '释放',
    'straightening' => '伸直',
    'placing' => '放置',
    'feeling' => '感受',
    'lifted' => '抬起',
    'balance' => '平衡',
    'Continue' => '继续',
    'continue' => '继续',
    
    // 名词
    'stirrups' => '脚环',
    'straps' => '带子',
    'forehead' => '前额',
    'ears' => '耳朵',
    'chin' => '下巴',
    'neck' => '颈部',
    'ankle' => '脚踝',
    'buttocks' => '臀部',
    'quad' => '股四头肌',
    'palm' => '手掌',
    'results' => '效果',
    'level' => '水平',
    
    // 形容词/副词
    'staggered' => '交错',
    'nearby' => '附近',
    'highest' => '最高',
    'several' => '几个',
    'long' => '长',
    'comfortable' => '舒适',
    'possible' => '可能',
    'outside' => '外侧',
    'next' => '旁边',
    'closer' => '更近',
    'high' => '高',
    'flared' => '外展',
    
    // 其他
    'When' => '当',
    'when' => '当',
    'onto' => '到',
    'ing' => '',
    'will' => '会',
    'you\'ll' => '你会',
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
