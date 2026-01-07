<?php
/**
 * 扫描name_en字段中包含中文的记录
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exercise;

echo "扫描name_en字段中包含中文的记录\n\n";

// 查找name_en包含中文的记录
$exercises = Exercise::all();
$problems = [];

foreach ($exercises as $exercise) {
    $nameEn = $exercise->name_en ?? '';
    // 检查是否包含中文字符
    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $nameEn)) {
        $problems[] = [
            'id' => $exercise->id,
            'name_zh' => $exercise->name_zh,
            'name_en' => $nameEn,
        ];
    }
}

echo "发现 " . count($problems) . " 条name_en包含中文的记录:\n\n";

foreach ($problems as $p) {
    echo "ID: {$p['id']}\n";
    echo "  name_zh: {$p['name_zh']}\n";
    echo "  name_en: {$p['name_en']}\n\n";
}
