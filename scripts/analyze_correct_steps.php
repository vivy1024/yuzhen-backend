<?php
/**
 * 分析 correct_steps 和 correct_steps_zh 的关系
 */

require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

$dataPath = storage_path('app/public/exercises_v2');

$stats = [
    'both' => 0,           // 有 correct_steps 和 correct_steps_zh
    'steps_only' => 0,     // 仅有 correct_steps
    'steps_zh_only' => 0,  // 仅有 correct_steps_zh
    'neither' => 0,        // 都没有
    'total' => 0,
];

$duplicateExamples = [];
$stepsOnlyExamples = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dataPath, RecursiveDirectoryIterator::SKIP_DOTS)
);

echo "🔍 分析原始数据中 correct_steps 和 correct_steps_zh 的关系...\n\n";

foreach ($iterator as $file) {
    if ($file->getFilename() === 'data.json' && $file->isFile()) {
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);

        if (!isset($data['id'])) continue;

        $stats['total']++;
        $exerciseId = $data['id'];
        $steps = $data['correct_steps'] ?? null;
        $stepsZh = $data['correct_steps_zh'] ?? null;
        $description = $data['description'] ?? '';
        $descriptionZh = $data['description_zh'] ?? '';

        if ($steps && $stepsZh) {
            $stats['both']++;
        } elseif ($steps && !$stepsZh) {
            $stats['steps_only']++;
            
            // 检查 correct_steps 是否和 description 重复
            $stepsText = is_array($steps) ? json_encode($steps) : $steps;
            if (strpos($description, 'Correct Steps') !== false || strpos($description, 'correct') !== false) {
                if (count($duplicateExamples) < 5) {
                    $duplicateExamples[] = [
                        'id' => $exerciseId,
                        'hasSteps' => is_array($steps) && count($steps) > 0,
                        'hasDescription' => !empty($description),
                    ];
                }
            }
            
            if (count($stepsOnlyExamples) < 5) {
                $stepsOnlyExamples[] = [
                    'id' => $exerciseId,
                    'steps_count' => is_array($steps) ? count($steps) : 0,
                    'description_has_steps' => strpos(strtolower($description), 'correct') !== false,
                ];
            }
        } elseif (!$steps && $stepsZh) {
            $stats['steps_zh_only']++;
        } else {
            $stats['neither']++;
        }
    }
}

echo "📊 原始数据统计:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "总数: {$stats['total']}\n";
echo "✅ 两个都有 (correct_steps + correct_steps_zh): {$stats['both']}\n";
echo "⚠️  仅有英文 (correct_steps only): {$stats['steps_only']}\n";
echo "⚠️  仅有中文 (correct_steps_zh only): {$stats['steps_zh_only']}\n";
echo "❌ 都没有: {$stats['neither']}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🔍 关键发现:\n";
echo "如果 correct_steps_zh 为空，correct_steps 也应该为空\n";
echo "现在有 {$stats['steps_only']} 条记录只有 correct_steps，这可能是:\n";
echo "1. correct_steps 从 description 复制过来\n";
echo "2. 原始数据的中文翻译缺失\n\n";

echo "📝 仅有 correct_steps 的示例:\n";
foreach ($stepsOnlyExamples as $example) {
    echo "   Exercise {$example['id']}: {$example['steps_count']} 步, ";
    echo "description包含步骤信息: " . ($example['description_has_steps'] ? '是' : '否') . "\n";
}
echo "\n";

// 检查数据库中是否 correct_steps 和 description 重复
echo "🔄 检查数据库中的重复情况...\n";
$dbExamples = DB::table('exercises')
    ->whereNotNull('correct_steps')
    ->limit(3)
    ->get();

foreach ($dbExamples as $exercise) {
    $steps = json_decode($exercise->correct_steps, true);
    $stepsStr = json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    echo "\n📌 Exercise {$exercise->id}:\n";
    echo "   description: " . substr($exercise->description, 0, 80) . "...\n";
    echo "   correct_steps: " . substr($stepsStr, 0, 150) . "...\n";
}

echo "\n\n✅ 分析完成！\n";
