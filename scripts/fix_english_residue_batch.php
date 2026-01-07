<?php
/**
 * 批量修复英文残留 - 基于字符串替换
 * 
 * 分析发现大量英文残留是重复的短语，可以用简单的字符串替换快速处理
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/fix_english_residue_batch.php
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "=" . str_repeat("=", 79) . "\n";
echo "批量修复英文残留 - 字符串替换\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 定义替换规则（英文 => 中文）
$replacements = [
    // 常见短语
    'shoulder width' => '肩宽',
    'shoulder level' => '肩膀高度',
    'starting position' => '起始位置',
    'and repeat' => '然后重复',
    'Switch sides' => '换边',
    'Repeat as necessary' => '根据需要重复',
    'Repeat with your other arm' => '换另一只手臂重复',
    'Repeat with the other arm' => '换另一只手臂重复',
    'Hold the stretch for a few seconds then repeat with the other arm' => '保持拉伸几秒钟，然后换另一只手臂重复',
    'Hold the stretch for a few seconds then return to starting position' => '保持拉伸几秒钟，然后回到起始位置',
    'Hold at the peak of the stretch, then slowly return to starting position' => '在拉伸最高点保持，然后缓慢回到起始位置',
    'Hold at the peak of the stretch' => '在拉伸最高点保持',
    'Stand upright with your feet shoulder width apart' => '双脚与肩同宽站立',
    'Stand upright' => '直立站好',
    'Repeat' => '重复',
    
    // 动作相关
    'With bent elbows' => '弯曲肘部',
    'raise your arms up to' => '将手臂抬起至',
    'pausing at the at the end of the motion' => '在动作末端暂停',
    'pausing at the end of the motion' => '在动作末端暂停',
    'Tense your glutes' => '收紧臀部',
    'raise your hips towards the ceiling' => '将臀部向天花板方向抬起',
    'With straight arms unrack the bar' => '伸直手臂取下杠铃',
    'Lay flat on the bench with your feet on the ground' => '平躺在卧推凳上，双脚着地',
    'Resting the kettlebell on your pelvis' => '将壶铃放在骨盆上',
    'raise your pelvis until your stomach, pelvis and thighs are in line' => '抬起骨盆直到腹部、骨盆和大腿成一条直线',
    'Slowly lower until you are in the starting position' => '缓慢下降直到回到起始位置',
    'Stand with your feet shoulder width apart holding the kettlebell with both hands in front of your thighs' => '双脚与肩同宽站立，双手握住壶铃放在大腿前方',
    'Stand with feet shoulder width apart holding a kettlebell with one hand at your side' => '双脚与肩同宽站立，一只手握住壶铃放在身侧',
    'Bend forward at the hips bringing the kettlebell to the floor while you slightly bend your knees, keeping your back straight' => '髋部前屈将壶铃放向地面，同时膝盖微屈，保持背部挺直',
    'Return to the upright position' => '回到直立位置',
    'Hold the kettlebell in both hands directly above your chest' => '双手握住壶铃举在胸部正上方',
    'Lift the kettlebell upwards towards your chest and lower - repeat' => '将壶铃向胸部方向抬起然后放下，重复',
    'Stand straight with your feet slightly apart and hold a kettlebell in one hand' => '双脚略微分开站直，一只手握住壶铃',
    'Holding the kettlebell in both hands engage your shoulder blades, as if you are trying to touch them together. Release the shrug' => '双手握住壶铃，收紧肩胛骨，就像要让它们碰在一起。然后放松耸肩',
    'Sink down into the squat, keeping your elbows inside the track of your knees' => '下蹲，保持肘部在膝盖内侧',
    'Push through your heels while keeping your chest up and return to the starting position' => '脚跟发力，保持挺胸，回到起始位置',
    
    // 拉伸相关
    'Sit on the ground and lay one leg flat and the other over the top' => '坐在地上，一条腿平放，另一条腿跨过',
    'Hold your leg with the same side arm and slowly rotate your hips and back' => '用同侧手臂抱住腿，缓慢旋转髋部和背部',
    'Press the arm until it is straight and rotate your upper torso to engage the stretch even deeper' => '将手臂推直，旋转上半身以加深拉伸',
    'Lower your head towards the floor by bending your elbows' => '弯曲肘部将头部向地面方向下降',
    
    // 器械相关
    'Use a handle attachment. The cable should be set all the way to the bottom of the machine' => '使用把手附件。将拉索调到器械最底部',
    'Use a handle attachment. The cable should be set all the way to the top of the machine' => '使用把手附件。将拉索调到器械最顶部',
    'Face away from the cable machine. Stagger your stance so you have a better base of support' => '背对拉索器械站立。前后脚站立以获得更好的支撑',
    'You can use any attachment. Cable should be set all the way to the top of the machine' => '可以使用任何附件。将拉索调到器械最顶部',
    'Bring both of the handles to your chest and make sure you are in the center of the cable crossover' => '将两个把手拉到胸前，确保你站在龙门架中央',
    'Use a double handle attachment and set the cable all the way to the top' => '使用双把手附件，将拉索调到最顶部',
    'Attach the band to an anchor point that is about shoulder height. Take a few steps away until the band is taut' => '将弹力带固定在大约肩膀高度的锚点上。向后退几步直到弹力带绷紧',
    
    // 其他常见短语
    'Duration of these movements should be slow so that you do not utilize momentum, enabling you to get the most out of the exercise' => '动作应该缓慢进行，避免借助惯性，以获得最佳训练效果',
    'After a pause at the stretched position, start pulling yourself back to the starting position. This should be a slow and controlled movement' => '在拉伸位置暂停后，开始将身体拉回起始位置。这应该是一个缓慢且可控的动作',
    'Extend your heels upwards while keeping your knees stationary, and pause at the contracted position' => '保持膝盖不动，向上抬起脚跟，在收缩位置暂停',
    'Slowly return to the starting position. Repeat' => '缓慢回到起始位置。重复',
    'Flex your elbow until you feel your biceps contract. Then fully extend elbows' => '弯曲肘部直到感觉肱二头肌收缩。然后完全伸直肘部',
    'Take a few steps away until the band is taut' => '向后退几步直到弹力带绷紧',
    
    // 单词级别替换（放在最后，避免影响短语替换）
    'superman chest' => '超人挺胸',
    'inches' => '英寸',
    'pounds' => '磅',
    'reps' => '次',
    'secs' => '秒',
    'lockout' => '锁定',
    'ego lifting' => '炫耀式举重',
    'landmine' => '地雷架',
    'Silverback' => '银背',
    'Pallof' => '帕洛夫',
    'Bayesian' => '贝叶斯',
    'resting' => '放置',
    'lowered towards the floor' => '向地面下降',
];

// 统计
$stats = [
    'files_scanned' => 0,
    'files_updated' => 0,
    'replacements_made' => 0,
];

// 遍历所有动作文件
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($exercisesDir)
);

foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') {
        continue;
    }
    
    $stats['files_scanned']++;
    $filePath = $file->getPathname();
    
    // 读取文件
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (!$data) {
        continue;
    }
    
    $modified = false;
    $fileReplacements = 0;
    
    // 需要检查的字段
    $fieldsToCheck = ['name_zh', 'description_zh', 'correct_steps_zh', 'equipment_zh', 'primary_muscle_zh'];
    
    foreach ($fieldsToCheck as $field) {
        if (!isset($data[$field])) {
            continue;
        }
        
        if (is_array($data[$field])) {
            // 数组字段（如 correct_steps_zh）
            foreach ($data[$field] as $index => $value) {
                if (!is_string($value)) continue;
                
                $newValue = $value;
                foreach ($replacements as $en => $zh) {
                    if (strpos($newValue, $en) !== false) {
                        $newValue = str_replace($en, $zh, $newValue);
                        $fileReplacements++;
                    }
                }
                
                if ($newValue !== $value) {
                    $data[$field][$index] = $newValue;
                    $modified = true;
                }
            }
        } else {
            // 字符串字段
            $newValue = $data[$field];
            foreach ($replacements as $en => $zh) {
                if (strpos($newValue, $en) !== false) {
                    $newValue = str_replace($en, $zh, $newValue);
                    $fileReplacements++;
                }
            }
            
            if ($newValue !== $data[$field]) {
                $data[$field] = $newValue;
                $modified = true;
            }
        }
    }
    
    // 保存修改
    if ($modified) {
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $stats['files_updated']++;
        $stats['replacements_made'] += $fileReplacements;
        
        // 显示进度
        $id = basename(dirname($filePath));
        echo "✅ ID={$id}: {$fileReplacements} 处替换\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "批量替换完成\n";
echo str_repeat("=", 80) . "\n\n";

echo "扫描文件数: {$stats['files_scanned']}\n";
echo "更新文件数: {$stats['files_updated']}\n";
echo "替换次数: {$stats['replacements_made']}\n";

echo "\n下一步：\n";
echo "1. 运行 php scripts/scan_english_residue.php 检查剩余英文\n";
echo "2. 运行 php artisan exercise:sync-data 同步到 MySQL\n";
