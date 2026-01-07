<?php
/**
 * 扫描中文字段中的英文残留
 * 
 * 检测字段：name_zh, description_zh, correct_steps_zh, equipment_zh, primary_muscle_zh
 * 
 * 运行方式：
 * docker exec fitness_php_v2 php scripts/scan_english_residue.php
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';

// 统计
$total = 0;
$issues = [];

// 英文单词检测正则（排除常见缩写和数字）
function hasEnglishWords($text) {
    if (empty($text)) return false;
    
    // 匹配连续3个以上英文字母的单词（排除常见缩写）
    $excludePatterns = [
        'ROM', 'TRX', 'HIIT', 'BMI', 'RPE', 'RM', 'PR', 'PB',
        'kg', 'lb', 'cm', 'mm', 'ml', 'g', 's', 'min', 'sec',
        'vs', 'etc', 'OK', 'ok', 'N/A', 'n/a'
    ];
    
    // 查找英文单词
    preg_match_all('/\b[A-Za-z]{3,}\b/', $text, $matches);
    
    if (empty($matches[0])) return false;
    
    // 过滤掉常见缩写
    $englishWords = array_filter($matches[0], function($word) use ($excludePatterns) {
        return !in_array(strtoupper($word), array_map('strtoupper', $excludePatterns));
    });
    
    return !empty($englishWords) ? $englishWords : false;
}

// 检查单个字段
function checkField($data, $fieldName, $exerciseId, &$issues) {
    $value = $data[$fieldName] ?? null;
    
    if (is_array($value)) {
        // 数组字段（如 correct_steps_zh）
        foreach ($value as $index => $item) {
            $text = is_string($item) ? $item : ($item['text'] ?? '');
            $englishWords = hasEnglishWords($text);
            if ($englishWords) {
                $issues[] = [
                    'id' => $exerciseId,
                    'field' => "{$fieldName}[{$index}]",
                    'english_words' => $englishWords,
                    'content' => mb_substr($text, 0, 100) . (mb_strlen($text) > 100 ? '...' : '')
                ];
            }
        }
    } else if (is_string($value)) {
        $englishWords = hasEnglishWords($value);
        if ($englishWords) {
            $issues[] = [
                'id' => $exerciseId,
                'field' => $fieldName,
                'english_words' => $englishWords,
                'content' => mb_substr($value, 0, 100) . (mb_strlen($value) > 100 ? '...' : '')
            ];
        }
    }
}

// 遍历所有文件
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data || !isset($data['id'])) continue;
    
    $total++;
    $exerciseId = $data['id'];
    
    // 检查各个中文字段
    checkField($data, 'name_zh', $exerciseId, $issues);
    checkField($data, 'description_zh', $exerciseId, $issues);
    checkField($data, 'correct_steps_zh', $exerciseId, $issues);
    checkField($data, 'equipment_zh', $exerciseId, $issues);
    checkField($data, 'primary_muscle_zh', $exerciseId, $issues);
    
    if ($total % 200 === 0) {
        echo "已扫描: {$total} 个文件\n";
    }
}

// 输出结果
echo "\n=== 扫描完成 ===\n\n";
echo "总文件数: {$total}\n";
echo "问题记录数: " . count($issues) . "\n\n";

// 按字段分组统计
$byField = [];
foreach ($issues as $issue) {
    $fieldBase = preg_replace('/\[\d+\]$/', '', $issue['field']);
    $byField[$fieldBase] = ($byField[$fieldBase] ?? 0) + 1;
}

echo "=== 按字段统计 ===\n";
foreach ($byField as $field => $count) {
    echo "  {$field}: {$count}\n";
}

// 输出前20个问题示例
echo "\n=== 问题示例（前20个）===\n";
$shown = 0;
foreach ($issues as $issue) {
    if ($shown >= 20) break;
    echo "\nID: {$issue['id']}\n";
    echo "字段: {$issue['field']}\n";
    echo "英文词: " . implode(', ', $issue['english_words']) . "\n";
    echo "内容: {$issue['content']}\n";
    $shown++;
}

// 保存完整报告
$reportPath = '/var/www/html/storage/app/english_residue_report.json';
file_put_contents($reportPath, json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n完整报告已保存到: {$reportPath}\n";
