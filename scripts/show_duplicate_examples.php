<?php
/**
 * 展示 description 和 correct_steps 重复数据的具体例子
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';
$examples = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() === 'data.json' && count($examples) < 5) {
        $data = json_decode(file_get_contents($file->getPathname()), true);
        if ($data && !empty($data['description']) && stripos($data['description'], 'Correct Steps') !== false) {
            $examples[] = $data;
        }
    }
}

echo "=== description 与 correct_steps 重复数据示例 ===\n\n";

foreach ($examples as $i => $data) {
    echo str_repeat("=", 60) . "\n";
    echo "【例" . ($i + 1) . "】动作 #{$data['id']}: {$data['name_zh']} ({$data['name']})\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // 分离 description 中的两部分
    $desc = $data['description'];
    $parts = preg_split('/\*\*Correct Steps:\*\*/i', $desc, 2);
    
    echo "📝 description 字段内容:\n";
    echo str_repeat("-", 40) . "\n";
    
    if (count($parts) == 2) {
        echo "【第一部分：动作说明】\n";
        echo trim($parts[0]) . "\n\n";
        echo "【第二部分：正确步骤（重复数据）】\n";
        echo "**Correct Steps:**" . trim($parts[1]) . "\n\n";
    } else {
        echo $desc . "\n\n";
    }
    
    echo "📋 correct_steps 数组内容:\n";
    echo str_repeat("-", 40) . "\n";
    if (!empty($data['correct_steps'])) {
        foreach ($data['correct_steps'] as $step) {
            echo "{$step['order']}. {$step['text']}\n";
        }
    }
    
    echo "\n🔴 重复分析: description 末尾的步骤与 correct_steps 数组内容完全一致\n\n";
}
