<?php
/**
 * 清理中文字段中的 HTML 标签
 * 
 * 运行方式：
 * docker exec fitness_php_v2 php scripts/clean_html_tags.php
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';

$total = 0;
$modified = 0;
$htmlCleaned = 0;

function cleanHtmlTags($text) {
    if (empty($text)) return $text;
    
    // 移除所有 HTML 标签
    $cleaned = strip_tags($text);
    
    // 移除 &nbsp; 等 HTML 实体
    $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cleaned = str_replace(['&nbsp;', '&amp;', '&lt;', '&gt;'], [' ', '&', '<', '>'], $cleaned);
    
    // 清理多余空白
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    $cleaned = trim($cleaned);
    
    return $cleaned;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data) continue;
    
    $total++;
    $changed = false;
    
    // 清理 description_zh
    if (!empty($data['description_zh']) && preg_match('/<[^>]+>/', $data['description_zh'])) {
        $data['description_zh'] = cleanHtmlTags($data['description_zh']);
        $htmlCleaned++;
        $changed = true;
    }
    
    // 清理 description_en (如果有)
    if (!empty($data['description_en']) && preg_match('/<[^>]+>/', $data['description_en'])) {
        $data['description_en'] = cleanHtmlTags($data['description_en']);
        $changed = true;
    }
    
    if ($changed) {
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $modified++;
    }
    
    if ($total % 200 === 0) {
        echo "已处理: {$total} 个文件\n";
    }
}

echo "\n=== HTML标签清理完成 ===\n";
echo "总文件数: {$total}\n";
echo "修改文件数: {$modified}\n";
echo "清理HTML标签: {$htmlCleaned}\n";
