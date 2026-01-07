<?php
/**
 * 完整修复英文残留 - 基于完整句子替换
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/fix_english_residue_complete.php
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "=" . str_repeat("=", 79) . "\n";
echo "完整修复英文残留\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 完整句子替换映射（英文混杂 => 纯中文）
$sentenceReplacements = [
    // correct_steps_zh 常见句子
    "弯曲肘部, 举起 your arms up to 肩膀高度, 在动作末端暂停." => "弯曲肘部，将手臂抬起至肩膀高度，在动作末端暂停。",
    "Tense your 臀部 and 举起 your hips towards the ceiling." => "收紧臀部，将髋部向天花板方向抬起。",
    "Resting the 壶铃 on your 骨盆, 举起 your 骨盆 until your stomach, 骨盆 and thighs are in line." => "将壶铃放在骨盆上，抬起骨盆直到腹部、骨盆和大腿成一条直线。",
    "缓慢降低 until you are in the 起始位置 然后重复." => "缓慢降低直到回到起始位置，然后重复。",
    "Stand with your feet 肩宽 apart holding the 壶铃 with 双手 in front of your thighs." => "双脚与肩同宽站立，双手握住壶铃放在大腿前方。",
    "Bend 前向 at the hips bringing the 壶铃 to the 地面 while you slightly bend your knees, keeping your 背部 straight." => "髋部前屈将壶铃放向地面，同时膝盖微屈，保持背部挺直。",
    "Hold the 壶铃 in 双手 directly above your 胸肌." => "双手握住壶铃举在胸部正上方。",
    "Lift the 壶铃 upwards towards your 胸肌 and lower - repeat." => "将壶铃向胸部方向抬起然后放下，重复。",
    "Stand with feet 肩宽 apart holding a 壶铃 with 一只手 at your side." => "双脚与肩同宽站立，一只手握住壶铃放在身侧。",
    "Holding the 壶铃 in 双手 engage your shoulder blades, as if you are trying to touch them together. Release the 耸肩." => "双手握住壶铃，收紧肩胛骨，就像要让它们碰在一起。然后放松耸肩。",
    "Sink down into the 深蹲, keeping your elbows inside the track of your knees." => "下蹲，保持肘部在膝盖内侧。",
    "推 through your heels while keeping your 胸肌 up and return to the 起始位置." => "脚跟发力，保持挺胸，回到起始位置。",
    "Stand straight with your feet slightly apart and hold a 壶铃 in 一只手." => "双脚略微分开站直，一只手握住壶铃。",
    "直立站好 with your feet 肩宽 apart." => "双脚与肩同宽直立站好。",
    "Hold the stretch for a few seconds then 回到起始位置." => "保持拉伸几秒钟，然后回到起始位置。",
    "在拉伸最高点保持, then slowly 回到起始位置." => "在拉伸最高点保持，然后缓慢回到起始位置。",
    "Sit on the 地面 and lay one leg flat and the other over the top." => "坐在地上，一条腿平放，另一条腿跨过。",
    "Hold your leg with the same side arm and slowly rotate your hips and 背部." => "用同侧手臂抱住腿，缓慢旋转髋部和背部。",
    "推举 the arm until it is straight and rotate your upper torso to engage the stretch even deeper." => "将手臂推直，旋转上半身以加深拉伸。",
    "Duration of these movements should be slow so that you do not utilize 惯性, enabling you to get the most out of the 练习." => "动作应该缓慢进行，避免借助惯性，以获得最佳训练效果。",
    "After a pause at the stretched position, start pulling yourself 背部 to the 起始位置. This should be a slow and controlled movement." => "在拉伸位置暂停后，开始将身体拉回起始位置。这应该是一个缓慢且可控的动作。",
    "Extend your heels upwards while keeping your knees 静止, and pause at the contracted position." => "保持膝盖不动，向上抬起脚跟，在收缩位置暂停。",
    "Slowly return to the 起始位置. 重复." => "缓慢回到起始位置。重复。",
    "Bring both of the handles to your 胸肌 and 确保 you are in the center of the 拉索 crossover." => "将两个把手拉到胸前，确保你站在龙门架中央。",
    "Use a handle attachment. The 拉索 should be 组 all the way to the bottom of the 器械." => "使用把手附件。将拉索调到器械最底部。",
    "Face away from the 拉索 器械. Stagger your stance so you have a better base of support." => "背对拉索器械站立。前后脚站立以获得更好的支撑。",
    "You can use any attachment. 拉索 should be 组 all the way to the top of the 器械." => "可以使用任何附件。将拉索调到器械最顶部。",
    
    // 更多常见句子
    "Flex your elbow until you feel your 肱二头肌 contract. Then 完全伸展 elbows." => "弯曲肘部直到感觉肱二头肌收缩，然后完全伸直肘部。",
    "Take a few steps away unt the band is taut." => "向后退几步直到弹力带绷紧。",
    "Take a few steps away until the band is taut." => "向后退几步直到弹力带绷紧。",
    
    // name_zh 替换
    "杠铃 银背 耸肩" => "杠铃银背耸肩",
    "银背 绳索耸肩" => "银背绳索耸肩",
    "拉力器帕洛夫按压" => "拉力器帕洛夫按压",
    "弹力带 贝叶斯 锤式弯举" => "弹力带贝叶斯锤式弯举",
    "弹力带 帕洛夫 按压" => "弹力带帕洛夫按压",
];

// 单词级别替换（用于处理剩余的混杂内容）
$wordReplacements = [
    // 动词
    'Stand' => '站立',
    'Sit' => '坐',
    'Hold' => '保持',
    'Bend' => '弯曲',
    'Lift' => '抬起',
    'Lower' => '放下',
    'Raise' => '抬起',
    'Push' => '推',
    'Pull' => '拉',
    'Extend' => '伸展',
    'Flex' => '弯曲',
    'Rotate' => '旋转',
    'Squeeze' => '挤压',
    'Contract' => '收缩',
    'Release' => '放松',
    'Return' => '返回',
    'Repeat' => '重复',
    'Pause' => '暂停',
    'Slowly' => '缓慢地',
    'Tense' => '收紧',
    'Engage' => '激活',
    'Keeping' => '保持',
    'Bringing' => '带动',
    'Holding' => '握住',
    'Resting' => '放置',
    'Sink' => '下沉',
    'Face' => '面向',
    'Stagger' => '前后错开',
    'Use' => '使用',
    'Attach' => '连接',
    
    // 名词
    'shoulder' => '肩膀',
    'shoulders' => '肩膀',
    'elbow' => '肘部',
    'elbows' => '肘部',
    'arm' => '手臂',
    'arms' => '手臂',
    'hand' => '手',
    'hands' => '双手',
    'wrist' => '手腕',
    'chest' => '胸部',
    'back' => '背部',
    'hip' => '髋部',
    'hips' => '髋部',
    'knee' => '膝盖',
    'knees' => '膝盖',
    'leg' => '腿',
    'legs' => '双腿',
    'foot' => '脚',
    'feet' => '双脚',
    'heel' => '脚跟',
    'heels' => '脚跟',
    'thigh' => '大腿',
    'thighs' => '大腿',
    'stomach' => '腹部',
    'torso' => '躯干',
    'pelvis' => '骨盆',
    'glutes' => '臀部',
    'biceps' => '肱二头肌',
    'triceps' => '肱三头肌',
    'floor' => '地面',
    'ground' => '地面',
    'ceiling' => '天花板',
    'position' => '位置',
    'movement' => '动作',
    'stretch' => '拉伸',
    'squat' => '深蹲',
    'stance' => '站姿',
    'handle' => '把手',
    'handles' => '把手',
    'attachment' => '附件',
    'cable' => '拉索',
    'machine' => '器械',
    'band' => '弹力带',
    'kettlebell' => '壶铃',
    'barbell' => '杠铃',
    'dumbbell' => '哑铃',
    'bench' => '凳子',
    'bar' => '杠',
    'crossover' => '龙门架',
    'blades' => '肩胛骨',
    'track' => '轨迹',
    'base' => '基础',
    'support' => '支撑',
    'center' => '中央',
    'top' => '顶部',
    'bottom' => '底部',
    'side' => '侧面',
    'line' => '直线',
    
    // 形容词/副词
    'straight' => '挺直',
    'flat' => '平放',
    'upright' => '直立',
    'forward' => '向前',
    'upwards' => '向上',
    'downwards' => '向下',
    'slowly' => '缓慢地',
    'slightly' => '略微',
    'directly' => '直接',
    'fully' => '完全',
    'controlled' => '可控的',
    'contracted' => '收缩的',
    'stretched' => '拉伸的',
    'stationary' => '静止的',
    'taut' => '绷紧的',
    'apart' => '分开',
    'together' => '一起',
    'inside' => '内侧',
    'above' => '上方',
    'below' => '下方',
    'away' => '远离',
    'deeper' => '更深',
    'better' => '更好',
    
    // 介词/连词
    'with' => '用',
    'from' => '从',
    'into' => '进入',
    'until' => '直到',
    'while' => '同时',
    'through' => '通过',
    'towards' => '朝向',
    'over' => '越过',
    'under' => '在下面',
    'between' => '之间',
    'behind' => '在后面',
    'in front of' => '在前面',
    'on' => '在...上',
    'at' => '在',
    'to' => '到',
    'of' => '的',
    'the' => '',
    'a' => '',
    'an' => '',
    'your' => '你的',
    'you' => '你',
    'and' => '和',
    'or' => '或',
    'so' => '所以',
    'that' => '那',
    'this' => '这',
    'it' => '它',
    'is' => '是',
    'are' => '是',
    'be' => '是',
    'should' => '应该',
    'can' => '可以',
    'do' => '',
    'not' => '不',
    'as' => '像',
    'if' => '如果',
    'then' => '然后',
    'other' => '另一个',
    'same' => '同一',
    'one' => '一个',
    'both' => '两个',
    'all' => '全部',
    'way' => '方式',
    'few' => '几',
    'seconds' => '秒',
    'set' => '设置',
    
    // 其他
    'Duration' => '持续时间',
    'After' => '之后',
    'Start' => '开始',
    'These' => '这些',
    'movements' => '动作',
    'enabling' => '使',
    'get' => '获得',
    'most' => '最大',
    'out' => '出',
    'have' => '有',
    'trying' => '尝试',
    'touch' => '触碰',
    'them' => '它们',
    'lay' => '放平',
    'even' => '甚至',
    'pulling' => '拉',
    'yourself' => '自己',
    'make' => '确保',
    'sure' => '',
    'any' => '任何',
];

// 统计
$stats = [
    'files_scanned' => 0,
    'files_updated' => 0,
    'sentence_replacements' => 0,
    'word_replacements' => 0,
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
    
    // 需要检查的字段
    $fieldsToCheck = ['name_zh', 'description_zh', 'correct_steps_zh', 'equipment_zh', 'primary_muscle_zh'];
    
    foreach ($fieldsToCheck as $field) {
        if (!isset($data[$field])) {
            continue;
        }
        
        if (is_array($data[$field])) {
            // 数组字段
            foreach ($data[$field] as $index => $value) {
                if (!is_string($value)) continue;
                
                $newValue = $value;
                
                // 先尝试完整句子替换
                foreach ($sentenceReplacements as $en => $zh) {
                    if ($newValue === $en || strpos($newValue, $en) !== false) {
                        $newValue = str_replace($en, $zh, $newValue);
                        $stats['sentence_replacements']++;
                    }
                }
                
                // 再进行单词替换
                foreach ($wordReplacements as $en => $zh) {
                    // 使用单词边界匹配
                    $pattern = '/\b' . preg_quote($en, '/') . '\b/i';
                    if (preg_match($pattern, $newValue)) {
                        $newValue = preg_replace($pattern, $zh, $newValue);
                        $stats['word_replacements']++;
                    }
                }
                
                // 清理多余空格和标点
                $newValue = preg_replace('/\s+/', ' ', $newValue);
                $newValue = preg_replace('/\s*,\s*/', '，', $newValue);
                $newValue = preg_replace('/\s*\.\s*$/', '。', $newValue);
                $newValue = trim($newValue);
                
                if ($newValue !== $value) {
                    $data[$field][$index] = $newValue;
                    $modified = true;
                }
            }
        } else {
            // 字符串字段
            $newValue = $data[$field];
            
            // 先尝试完整句子替换
            foreach ($sentenceReplacements as $en => $zh) {
                if ($newValue === $en || strpos($newValue, $en) !== false) {
                    $newValue = str_replace($en, $zh, $newValue);
                    $stats['sentence_replacements']++;
                }
            }
            
            // 再进行单词替换
            foreach ($wordReplacements as $en => $zh) {
                $pattern = '/\b' . preg_quote($en, '/') . '\b/i';
                if (preg_match($pattern, $newValue)) {
                    $newValue = preg_replace($pattern, $zh, $newValue);
                    $stats['word_replacements']++;
                }
            }
            
            // 清理
            $newValue = preg_replace('/\s+/', ' ', $newValue);
            $newValue = trim($newValue);
            
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
        
        $id = basename(dirname($filePath));
        if ($stats['files_updated'] <= 20 || $stats['files_updated'] % 50 == 0) {
            echo "✅ ID={$id}\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "修复完成\n";
echo str_repeat("=", 80) . "\n\n";

echo "扫描文件数: {$stats['files_scanned']}\n";
echo "更新文件数: {$stats['files_updated']}\n";
echo "句子替换: {$stats['sentence_replacements']}\n";
echo "单词替换: {$stats['word_replacements']}\n";

echo "\n下一步：运行 php scripts/scan_english_residue.php 检查剩余英文\n";
