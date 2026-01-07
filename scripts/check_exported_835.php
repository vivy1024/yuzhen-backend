<?php
$file = __DIR__ . '/../storage/app/enhanced_perfect_exercises_dataset.json';
$data = json_decode(file_get_contents($file), true);

foreach ($data['enhanced_perfect_exercises'] as $ex) {
    if ($ex['id'] == 835) {
        echo "ID: " . $ex['id'] . "\n";
        echo "name_zh: " . $ex['name_zh'] . "\n";
        echo "name_en: " . $ex['name_en'] . "\n";
        break;
    }
}
