<?php
// 同步广告动作修复到MySQL
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$fixes = [
    306 => [
        'name_zh' => '杠铃交错站姿硬拉',
        'description_zh' => '杠铃交错站姿硬拉是一种单侧训练变体，通过前后脚站位增加核心稳定性挑战，主要锻炼臀部和腿后肌群。',
        'primary_muscle_zh' => '臀部'
    ],
    308 => [
        'name_zh' => '杠铃单腿硬拉',
        'description_zh' => '杠铃单腿硬拉是一种高级单侧训练动作，通过单腿支撑增强平衡能力和核心稳定性，主要锻炼臀部和腿后肌群。',
        'primary_muscle_zh' => '臀部'
    ],
    1224 => [
        'name_zh' => 'Y字伸展',
        'description_zh' => 'Y字伸展是一种肩部康复和强化动作，通过俯卧抬臂锻炼三角肌后束和肩袖肌群。',
        'primary_muscle_zh' => '三角肌后束'
    ]
];

echo "=== 更新MySQL exercises表 ===\n";

foreach ($fixes as $id => $data) {
    $count = DB::table('exercises')->where('id', $id)->update($data);
    echo "更新 ID {$id}: {$data['name_zh']} - {$count} 条\n";
}

echo "\nMySQL更新完成!\n";
