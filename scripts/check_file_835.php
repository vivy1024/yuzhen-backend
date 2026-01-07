<?php
$file = __DIR__ . '/../storage/app/public/exercises_v2/0800-0899/0830-0839/835/data.json';
$data = json_decode(file_get_contents($file), true);
echo "name_zh: " . $data['name_zh'] . "\n";
echo "name_en: " . ($data['name_en'] ?? 'N/A') . "\n";
