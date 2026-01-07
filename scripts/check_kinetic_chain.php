<?php
// 统计 kinetic_chain_type 分布
$data = json_decode(file_get_contents(__DIR__ . '/../storage/app/enhanced_perfect_exercises_dataset.json'), true);
$exercises = $data['enhanced_perfect_exercises'];

$kinetic = [];
$technique = [];

foreach ($exercises as $e) {
    // kinetic_chain_type
    $val = $e['kinetic_chain_type'] ?? 'null';
    $kinetic[$val] = ($kinetic[$val] ?? 0) + 1;
    
    // technique_checkpoints
    $hasCheckpoints = isset($e['technique_checkpoints']) && !empty($e['technique_checkpoints']) ? 'has_data' : 'empty';
    $technique[$hasCheckpoints] = ($technique[$hasCheckpoints] ?? 0) + 1;
}

echo "=== kinetic_chain_type 分布 ===\n";
foreach ($kinetic as $k => $v) {
    echo "  $k: $v\n";
}

echo "\n=== technique_checkpoints 分布 ===\n";
foreach ($technique as $k => $v) {
    echo "  $k: $v\n";
}

echo "\nTotal: " . count($exercises) . "\n";
