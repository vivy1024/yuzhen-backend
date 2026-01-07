<?php
/**
 * 分析增强版数据集中哪些字段是固定值（所有记录相同）
 */

$data = json_decode(file_get_contents('/var/www/html/../daml-rag-server/data/enhanced_perfect_exercises_dataset.json'), true);

if (!$data || !isset($data['enhanced_perfect_exercises'])) {
    // 尝试另一个路径
    $data = json_decode(file_get_contents('/app/data/enhanced_perfect_exercises_dataset.json'), true);
}

if (!$data) {
    echo "无法读取数据文件\n";
    exit(1);
}

$exercises = $data['enhanced_perfect_exercises'];
$total = count($exercises);

echo "=== 分析增强版数据集固定值 ===\n";
echo "总记录数: $total\n\n";

// 收集每个字段的所有唯一值
$fieldValues = [];
foreach ($exercises as $ex) {
    foreach ($ex as $key => $value) {
        if (!isset($fieldValues[$key])) {
            $fieldValues[$key] = [];
        }
        $valueStr = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        if (!in_array($valueStr, $fieldValues[$key])) {
            $fieldValues[$key][] = $valueStr;
        }
    }
}

echo "【固定值字段（所有记录相同）】\n";
echo str_repeat("-", 50) . "\n";
foreach ($fieldValues as $field => $values) {
    if (count($values) == 1) {
        $val = $values[0];
        if (strlen($val) > 60) $val = substr($val, 0, 60) . "...";
        echo "$field: $val\n";
    }
}

echo "\n【少量变化字段（2-5个唯一值）】\n";
echo str_repeat("-", 50) . "\n";
foreach ($fieldValues as $field => $values) {
    $count = count($values);
    if ($count >= 2 && $count <= 5) {
        echo "$field: $count 个唯一值\n";
        foreach (array_slice($values, 0, 3) as $v) {
            if (strlen($v) > 50) $v = substr($v, 0, 50) . "...";
            echo "  - $v\n";
        }
    }
}

echo "\n【多样化字段（>5个唯一值）】\n";
echo str_repeat("-", 50) . "\n";
foreach ($fieldValues as $field => $values) {
    $count = count($values);
    if ($count > 5) {
        echo "$field: $count 个唯一值\n";
    }
}
