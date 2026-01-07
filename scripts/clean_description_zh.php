<?php
/**
 * 清理 description_zh 中重复的步骤内容
 * 
 * 规则：
 * 1. 移除 "正确步骤：" 及之后的内容
 * 2. 如果清理后为空，将 description_zh 设为 null
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';

$total = 0;
$modified = 0;
$cleared = 0;
$kept = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data) continue;
    
    $total++;
    
    $descZh = $data['description_zh'] ?? '';
    if (empty($descZh)) continue;
    
    // 检查是否包含步骤内容
    // 匹配模式：正确步骤：/正确步骤:  或者 数字。/数字.
    $hasSteps = preg_match('/正确步骤[：:]/u', $descZh) || 
                preg_match('/\s+\d+[。.]\s*/u', $descZh);
    
    if (!$hasSteps) continue;
    
    // 移除 "正确步骤：" 及之后的内容
    $cleaned = preg_replace('/\s*正确步骤[：:].*/su', '', $descZh);
    $cleaned = trim($cleaned);
    
    if (empty($cleaned)) {
        $data['description_zh'] = null;
        $cleared++;
    } else {
        $data['description_zh'] = $cleaned;
        $kept++;
    }
    
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $modified++;
}

echo "=== description_zh 清理完成 ===\n\n";
echo "总文件数: $total\n";
echo "修改文件数: $modified\n\n";
echo "处理结果:\n";
echo "- 清空（设为null）: $cleared\n";
echo "- 保留（有内容）: $kept\n";
