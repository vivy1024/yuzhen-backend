<?php
/**
 * 统计清理 description 中 Correct Steps 后的影响
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';

$total = 0;
$hasCorrectSteps = 0;
$willBeEmpty = 0;
$willHaveContent = 0;
$noChange = 0;
$emptyExamples = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() === 'data.json') {
        $data = json_decode(file_get_contents($file->getPathname()), true);
        if (!$data) continue;
        
        $total++;
        $desc = $data['description'] ?? '';
        
        if (empty($desc)) {
            $noChange++;
            continue;
        }
        
        // 检查是否包含 Correct Steps
        if (stripos($desc, 'Correct Steps') === false) {
            $noChange++;
            continue;
        }
        
        $hasCorrectSteps++;
        
        // 移除 **Correct Steps:** 及之后的内容
        $cleaned = preg_replace('/\*\*Correct Steps:\*\*.*/s', '', $desc);
        $cleaned = trim(strip_tags($cleaned));
        
        if (empty($cleaned)) {
            $willBeEmpty++;
            if (count($emptyExamples) < 5) {
                $emptyExamples[] = [
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'name_zh' => $data['name_zh']
                ];
            }
        } else {
            $willHaveContent++;
        }
    }
}

echo "=== 清理 description 影响统计 ===\n\n";
echo "总文件数: $total\n";
echo "包含 Correct Steps 的: $hasCorrectSteps\n";
echo "不需要修改的: $noChange\n\n";

echo "清理后结果:\n";
echo "- 仍有内容: $willHaveContent (" . round($willHaveContent/$total*100, 1) . "%)\n";
echo "- 将变为空: $willBeEmpty (" . round($willBeEmpty/$total*100, 1) . "%)\n\n";

if (!empty($emptyExamples)) {
    echo "将变为空的示例:\n";
    foreach ($emptyExamples as $ex) {
        echo "  - #{$ex['id']}: {$ex['name_zh']} ({$ex['name']})\n";
    }
}
