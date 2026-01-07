<?php
// 清理MySQL exercises表固定值字段
// 运行: docker exec fitness_php_v2 php cleanup_mysql.php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$fields = ['key_nutrients', 'recommended_foods', 'nutrition_timing', 'safety_pre_check', 'equipment_risks'];

echo "=== 清理MySQL exercises表固定值字段 ===\n";

foreach ($fields as $field) {
    try {
        $count = DB::table('exercises')
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->update([$field => null]);
        echo "清空 {$field}: {$count} 条记录\n";
    } catch (Exception $e) {
        echo "字段 {$field} 错误: " . $e->getMessage() . "\n";
    }
}

echo "\nMySQL清理完成!\n";
