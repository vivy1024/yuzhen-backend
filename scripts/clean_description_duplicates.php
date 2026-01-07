<?php
/**
 * 清理 description 中重复的 Correct Steps 内容
 * 
 * 规则：
 * 1. 移除 **Correct Steps:** 及之后的内容
 * 2. 如果清理后为空，将 description 设为 null
 * 3. 同时处理 description_zh
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';

$total = 0;
$modified = 0;
$descCleared = 0;
$descKept = 0;
$descZhCleared = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data) continue;
    
    $total++;
    $changed = false;
    
    // 处理 description
    $desc = $data['description'] ?? '';
    if (!empty($desc) && stripos($desc, 'Correct Steps') !== false) {
        // 移除 **Correct Steps:** 及之后的内容
        $cleaned = preg_replace('/\n*\*\*Correct Steps:\*\*.*/s', '', $desc);
        $cleaned = trim($cleaned);
        
        // 检查清理后是否为空（包括只剩HTML标签的情况）
        $textOnly = trim(strip_tags($cleaned));
        
        if (empty($textOnly)) {
            $data['description'] = null;
            $descCleared++;
        } else {
            $data['description'] = $cleaned;
            $descKept++;
        }
        $changed = true;
    }
    
    // 处理 description_zh（移除步骤部分）
    $descZh = $data['description_zh'] ?? '';
    if (!empty($descZh)) {
        // 尝试移除中文步骤部分
        $cleanedZh = preg_replace('/\s*正确步骤[：:]\s*\d+[。.].*/s', '', $descZh);
        $cleanedZh = trim($cleanedZh);
        
        $textOnlyZh = trim($cleanedZh);
        
        if (empty($textOnlyZh)) {
            $data['description_zh'] = null;
            $descZhCleared++;
            $changed = true;
        } elseif ($cleanedZh !== $descZh) {
            $data['description_zh'] = $cleanedZh;
            $changed = true;
        }
    }
    
    // 保存修改
    if ($changed) {
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $modified++;
    }
}

echo "=== 清理完成 ===\n\n";
echo "总文件数: $total\n";
echo "修改文件数: $modified\n\n";
echo "description 处理结果:\n";
echo "- 清空（设为null）: $descCleared\n";
echo "- 保留（有内容）: $descKept\n\n";
echo "description_zh 清空: $descZhCleared\n";
