<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportVivyUserSeeder extends Seeder
{
    /**
     * 导入Vivy用户的完整数据
     */
    public function run(): void
    {
        echo "🚀 开始导入Vivy用户数据...\n\n";
        
        // 1. 创建用户
        $userId = DB::table('users')->insertGetId([
            'name' => 'Vivy',
            'email' => '1765563156@qq.com',
            'phone' => '15314763713',
            'password' => Hash::make('vivy123456'), // 设置一个新密码
            'avatar' => null,
            'role' => 'user',
            'status' => 1,
            'del_flag' => 0,
            'is_active' => 1,
            'last_login_at' => '2025-11-05 09:12:15',
            'membership_tier' => 'free',
            'preferences' => null,
            'favorites' => '[]',
            'exercise_reviews' => '[]',
            'email_verified_at' => '2025-11-04 19:33:57',
            'onboarding_completed' => 1,
            'profile_completed_at' => '2025-11-04 19:58:40',
            'created_at' => '2025-11-04 19:33:57',
            'updated_at' => now(),
        ]);
        
        echo "✅ 用户创建成功 (ID: {$userId})\n";
        echo "   邮箱: 1765563156@qq.com\n";
        echo "   密码: vivy123456\n\n";
        
        // 2. 创建用户档案
        $profileData = [
            'user_id' => $userId,
            'basic_info' => json_encode([
                'nickname' => 'vivy',
                'age' => 22,
                'gender' => 'male',
                'height' => 170,
                'weight' => 64,
                'body_fat_percentage' => 15,
                'fitness_level' => 'intermediate',
                'region' => '西北地区',
                'sleep_hours' => 8,
            ]),
            'fitness_goals' => json_encode([
                'primary_goals' => ['增肌', '增强力量'],
                'secondary_goals' => ['减脂'],
                'goal_priority' => new \stdClass(),
                'training_split' => '推拉腿',
                'target_weight' => 70,
            ]),
            'training_preferences' => json_encode([
                'training_split' => '推拉腿',
                'available_equipment' => ['杠铃', '哑铃', '固定器械', '徒手', '自由重量架', '龙门架'],
                'training_location' => '健身房',
                'exercise_preferences' => ['复合动作', '力量训练', '固定器械'],
                'disliked_exercises' => ['推举'],
                'training_intensity' => 'moderate',
            ]),
            'strength_data' => json_encode([
                'dip' => ['one_rm' => 30],
                'squat' => ['one_rm' => 120],
                'pull_up' => ['one_rm' => 30],
                'deadlift' => ['one_rm' => 80],
                'bench_press' => ['one_rm' => 80],
                'overhead_press' => ['one_rm' => 50],
            ]),
            'health_status' => json_encode([
                'chronic_diseases' => ['无'],
                'injury_history' => ['无'],
                'medications' => ['无'],
                'other_notes' => '胸椎反曲',
            ]),
            'nutrition_profile' => json_encode([
                'user_settings' => [
                    'budget' => 'moderate',
                    'allergies' => ['无'],
                    'supplements' => ['肌酸', '蛋白粉', '维生素'],
                    'population_type' => '增肌期',
                    'dietary_preferences' => ['均衡饮食'],
                ],
                'auto_calculated' => null,
            ]),
            'ffmi_assessment' => json_encode([
                'bmi' => 22.1,
                'ffmi' => 18.8,
                'assessment' => '优秀',
                'bmi_status' => 'normal',
                'calculated_at' => '2025-11-05T06:20:19.555Z',
                'lean_body_mass' => 54.4,
                'normalized_ffmi' => 19.4,
                'natural_potential' => '还有很大空间',
                'used_estimated_bf' => false,
                'training_recommendation' => [
                    'focus' => '基础建设',
                    'suggestions' => [
                        '规律力量训练（每星期3-5次）',
                        '合理营养摄入（蛋白质每kg体重1.6-2g）',
                        '循序渐进，避免急功近利',
                        '重视恢复，确保充足睡眠',
                    ],
                ],
            ]),
            'version' => 1,
            'created_at' => '2025-11-04 19:33:57',
            'updated_at' => '2025-11-05 06:21:00',
        ];
        
        DB::table('user_profiles')->insert($profileData);
        echo "✅ 用户档案创建成功\n";
        echo "   昵称: vivy\n";
        echo "   年龄: 22岁 | 性别: 男\n";
        echo "   身高: 170cm | 体重: 64kg | 体脂: 15%\n";
        echo "   健身水平: 中级\n";
        echo "   主要目标: 增肌、增强力量\n";
        echo "   训练分化: 推拉腿\n";
        echo "   FFMI: 18.8 (优秀)\n\n";
        
        // 3. 设置免费会员
        DB::table('user_memberships')->insert([
            'user_id' => $userId,
            'membership_id' => 1, // 免费会员
            'is_active' => 1,
            'started_at' => now(),
            'expires_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ 会员状态设置成功 (免费会员)\n\n";
        
        echo "========================================\n";
        echo "✅ Vivy用户数据导入完成！\n";
        echo "========================================\n";
        echo "📧 邮箱: 1765563156@qq.com\n";
        echo "🔑 密码: vivy123456\n";
        echo "👤 用户ID: {$userId}\n";
        echo "========================================\n\n";
        
        // 4. 生成SQL导出文件
        $this->generateSQLExport($userId);
    }
    
    /**
     * 生成SQL导出文件
     */
    private function generateSQLExport(int $userId): void
    {
        echo "📝 生成SQL导出文件...\n";
        
        $exportPath = storage_path('app/vivy_user_export.sql');
        
        $sql = "-- BUILD_BODY 用户数据导出\n";
        $sql .= "-- 用户: Vivy (1765563156@qq.com)\n";
        $sql .= "-- 导出时间: " . now()->toDateTimeString() . "\n";
        $sql .= "-- 使用方法: mysql -u用户名 -p密码 数据库名 < vivy_user_export.sql\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET NAMES utf8mb4;\n\n";
        
        // 导出users表
        $user = DB::table('users')->where('id', $userId)->first();
        if ($user) {
            $sql .= "-- 用户基本信息\n";
            $sql .= $this->generateInsert('users', (array)$user) . ";\n\n";
        }
        
        // 导出user_profiles表
        $profile = DB::table('user_profiles')->where('user_id', $userId)->first();
        if ($profile) {
            $sql .= "-- 用户档案\n";
            $sql .= $this->generateInsert('user_profiles', (array)$profile) . ";\n\n";
        }
        
        // 导出user_memberships表
        $memberships = DB::table('user_memberships')->where('user_id', $userId)->get();
        if ($memberships->count() > 0) {
            $sql .= "-- 会员记录\n";
            foreach ($memberships as $membership) {
                $sql .= $this->generateInsert('user_memberships', (array)$membership) . ";\n";
            }
            $sql .= "\n";
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($exportPath, $sql);
        
        echo "✅ SQL文件已生成: {$exportPath}\n";
        echo "\n📋 服务器导入命令:\n";
        echo "   mysql -uroot -p数据库密码 fitness_app < vivy_user_export.sql\n\n";
    }
    
    /**
     * 生成INSERT语句
     */
    private function generateInsert(string $table, array $data): string
    {
        $columns = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $columns[] = "`{$column}`";
            
            if (is_null($value)) {
                $values[] = 'NULL';
            } elseif (is_numeric($value)) {
                $values[] = $value;
            } elseif (is_bool($value)) {
                $values[] = $value ? '1' : '0';
            } else {
                $escaped = addslashes($value);
                $values[] = "'{$escaped}'";
            }
        }
        
        $columnsStr = implode(', ', $columns);
        $valuesStr = implode(', ', $values);
        
        return "INSERT INTO `{$table}` ({$columnsStr}) VALUES ({$valuesStr})";
    }
}

