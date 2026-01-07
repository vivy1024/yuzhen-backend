<?php
/**
 * 分析 exercises_v2 源文件中 description 和 correct_steps 的数据分布
 */

$baseDir = '/var/www/html/storage/app/public/exercises_v2';
$total = 0;
$hasDesc = 0;
$hasDescZh = 0;
$hasSteps = 0;
$hasStepsZh = 0;
$descContainsSteps = 0;
$descZhContainsSteps = 0;
$exactDuplicates = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if ($file->getFilename() === 'data.json') {
        $data = json_decode(file_get_contents($file->getPathname()), true);
        if ($data) {
            $total++;
            $id = $data['id'] ?? 'unknown';
            
            if (!empty($data['description'])) $hasDesc++;
            if (!empty($data['description_zh'])) $hasDescZh++;
            if (!empty($data['correct_steps'])) $hasSteps++;
            if (!empty($data['correct_steps_zh'])) $hasStepsZh++;
            
            // 检查description是否包含Correct Steps文本
            if (!empty($data['description']) && stripos($data['description'], 'Correct Steps') !== false) {
                $descContainsSteps++;
            }
            
            // 检查description_zh是否包含步骤文本
            if (!empty($data['description_zh']) && (
                strpos($data['description_zh'], '正确步骤') !== false || 
                preg_match('/\d\.\s/', $data['description_zh'])
            )) {
                $descZhContainsSteps++;
            }
        }
    }
}

echo "=== 源文件数据分布分析 ===\n\n";
echo "总文件数: $total\n\n";
echo "字段覆盖情况:\n";
echo "- description (英文描述): $hasDesc (" . round($hasDesc/$total*100, 1) . "%)\n";
echo "- description_zh (中文描述): $hasDescZh (" . round($hasDescZh/$total*100, 1) . "%)\n";
echo "- correct_steps (英文步骤): $hasSteps (" . round($hasSteps/$total*100, 1) . "%)\n";
echo "- correct_steps_zh (中文步骤): $hasStepsZh (" . round($hasStepsZh/$total*100, 1) . "%)\n\n";
echo "数据重复情况:\n";
echo "- description 包含 'Correct Steps': $descContainsSteps (" . round($descContainsSteps/$total*100, 1) . "%)\n";
echo "- description_zh 包含步骤内容: $descZhContainsSteps (" . round($descZhContainsSteps/$total*100, 1) . "%)\n\n";

// 抽样检查3个文件的具体重复情况
echo "=== 抽样检查（前3个有重复的文件）===\n\n";
$samples = 0;
$iterator2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator2 as $file) {
    if ($file->getFilename() === 'data.json' && $samples < 3) {
        $data = json_decode(file_get_contents($file->getPathname()), true);
        if ($data && !empty($data['description']) && stripos($data['description'], 'Correct Steps') !== false) {
            $samples++;
            echo "--- 动作 #{$data['id']}: {$data['name_zh']} ---\n";
            
            // 提取description中的Correct Steps部分
            if (preg_match('/\*\*Correct Steps:\*\*(.+)$/s', $data['description'], $matches)) {
                $stepsInDesc = trim($matches[1]);
                echo "description中的步骤:\n" . substr($stepsInDesc, 0, 200) . "...\n\n";
            }
            
            // 显示correct_steps数组
            if (!empty($data['correct_steps'])) {
                echo "correct_steps数组 (" . count($data['correct_steps']) . " 步):\n";
                foreach (array_slice($data['correct_steps'], 0, 2) as $step) {
                    echo "  - " . substr($step['text'], 0, 80) . "...\n";
                }
                echo "\n";
            }
        }
    }
}
