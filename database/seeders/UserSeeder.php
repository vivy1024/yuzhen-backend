<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 用户测试数据Seeder
 * 
 * 创建测试用户，仅在开发环境使用
 * 
 * 使用：
 * php artisan db:seed --class=UserSeeder
 * 
 * @version 1.0.0
 * @date 2025-11-04
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 仅在开发环境运行
        if (config('app.env') === 'production') {
            $this->command->warn('⚠️  生产环境不应运行此Seeder');
            return;
        }
        
        $this->command->info('📝 创建测试用户...');
        
        $users = [
            [
                'name' => '测试管理员',
                'email' => 'admin@buildxbody.com',
                'phone' => '13800138000',
                'avatar' => null,
                'role' => 'admin',
                'status' => '1',
                'del_flag' => '0',
                'is_active' => true,
                'membership_tier' => 'energy',
                'preferences' => json_encode([
                    'language' => 'zh-CN',
                    'theme' => 'auto',
                    'notifications' => true
                ]),
                'favorites' => json_encode([]),
                'exercise_reviews' => json_encode([]),
                'email_verified_at' => now(),
                'onboarding_completed' => true,
                'profile_completed_at' => now(),
                'password' => Hash::make('password123'), // 开发环境统一密码
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '健身小白',
                'email' => 'beginner@test.com',
                'phone' => '13900139001',
                'avatar' => null,
                'role' => 'user',
                'status' => '1',
                'del_flag' => '0',
                'is_active' => true,
                'membership_tier' => 'free',
                'preferences' => json_encode([
                    'language' => 'zh-CN',
                    'theme' => 'light'
                ]),
                'favorites' => json_encode([1, 5, 10]), // 收藏了3个动作
                'exercise_reviews' => json_encode([]),
                'email_verified_at' => now(),
                'onboarding_completed' => false,
                'profile_completed_at' => null,
                'password' => Hash::make('password123'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '健身达人',
                'email' => 'advanced@test.com',
                'phone' => '13900139002',
                'avatar' => null,
                'role' => 'user',
                'status' => '1',
                'del_flag' => '0',
                'is_active' => true,
                'membership_tier' => 'warmheart',
                'preferences' => json_encode([
                    'language' => 'zh-CN',
                    'theme' => 'dark',
                    'notifications' => true
                ]),
                'favorites' => json_encode([20, 30, 40, 50]),
                'exercise_reviews' => json_encode([
                    ['exercise_id' => 1, 'rating' => 5, 'comment' => '非常好的动作！']
                ]),
                'email_verified_at' => now(),
                'onboarding_completed' => true,
                'profile_completed_at' => now(),
                'password' => Hash::make('password123'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        foreach ($users as $user) {
            $userId = DB::table('users')->insertGetId($user);
            
            // 为已完成引导的用户创建档案
            if ($user['onboarding_completed']) {
                $this->createUserProfile($userId, $user['name']);
            }
            
            $this->command->info("  ✓ 创建用户: {$user['name']} ({$user['email']})");
        }
        
        $this->command->info('');
        $this->command->info('✅ 测试用户创建完成！');
        $this->command->info('');
        $this->command->info('🔑 登录凭据（开发环境）：');
        $this->command->info('   管理员: admin@buildxbody.com / password123');
        $this->command->info('   小白: beginner@test.com / password123');
        $this->command->info('   达人: advanced@test.com / password123');
    }
    
    /**
     * 创建用户档案
     */
    private function createUserProfile(int $userId, string $userName): void
    {
        // 根据用户类型创建不同的档案数据
        if (strpos($userName, '管理员') !== false) {
            $profile = $this->getAdminProfile();
        } elseif (strpos($userName, '小白') !== false) {
            $profile = $this->getBeginnerProfile();
        } else {
            $profile = $this->getAdvancedProfile();
        }
        
        DB::table('user_profiles')->insert([
            'user_id' => $userId,
            'basic_info' => $profile['basic_info'],
            'fitness_goals' => $profile['fitness_goals'],
            'training_preferences' => $profile['training_preferences'],
            'health_status' => $profile['health_status'],
            'nutrition_profile' => $profile['nutrition_profile'],
            'strength_data' => $profile['strength_data'],
            'ffmi_assessment' => $profile['ffmi_assessment'],
            'version' => 1,
            'sync_status' => 'synced',
            'is_mcp_temp' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * 管理员档案
     */
    private function getAdminProfile(): array
    {
        return [
            'basic_info' => json_encode([
                'nickname' => '测试管理员',
                'age' => 30,
                'gender' => 'male',
                'height' => 178,
                'weight' => 75,
                'body_fat_percentage' => 15,
                'fitness_level' => 'intermediate',
                'sleep_hours' => 7.5
            ], JSON_UNESCAPED_UNICODE),
            'fitness_goals' => json_encode([
                'primary_goals' => ['增肌', '增强力量'],
                'target_weight' => 80,
                'goal_priority' => ['增肌' => 1, '增强力量' => 2],
                'training_split' => '推拉腿'
            ], JSON_UNESCAPED_UNICODE),
            'training_preferences' => json_encode([
                'training_split' => '推拉腿',
                'available_equipment' => ['杠铃', '哑铃', '固定器械'],
                'training_location' => '健身房',
                'training_intensity' => 'high'
            ], JSON_UNESCAPED_UNICODE),
            'health_status' => json_encode([
                'chronic_diseases' => [],
                'injury_history' => [],
                'medications' => []
            ], JSON_UNESCAPED_UNICODE),
            'nutrition_profile' => json_encode([
                'user_settings' => [
                    'population_type' => '增肌期',
                    'dietary_preferences' => ['高蛋白'],
                    'allergies' => [],
                    'supplements' => ['蛋白粉', '肌酸'],
                    'budget' => 'high'
                ],
                'auto_calculated' => [
                    'daily_calories' => 2800,
                    'daily_protein' => 150,
                    'daily_carbs' => 350,
                    'daily_fats' => 80
                ]
            ], JSON_UNESCAPED_UNICODE),
            'strength_data' => json_encode([
                'bench_press' => ['one_rm' => 100, 'three_rm' => 95],
                'squat' => ['one_rm' => 140, 'three_rm' => 130],
                'deadlift' => ['one_rm' => 180, 'three_rm' => 170]
            ]),
            'ffmi_assessment' => json_encode([
                'bmi' => 23.7,
                'bmi_status' => 'normal',
                'lean_body_mass' => 63.75,
                'ffmi' => 20.1,
                'normalized_ffmi' => 20.3,
                'assessment' => '良好',
                'natural_potential' => '还有很大空间',
                'used_estimated_bf' => false,
                'calculated_at' => now()->toIso8601String()
            ]),
        ];
    }
    
    /**
     * 初学者档案
     */
    private function getBeginnerProfile(): array
    {
        return [
            'basic_info' => json_encode([
                'nickname' => '健身小白',
                'age' => 22,
                'gender' => 'male',
                'height' => 172,
                'weight' => 65,
                'body_fat_percentage' => 20,
                'fitness_level' => 'novice',
                'sleep_hours' => 7
            ], JSON_UNESCAPED_UNICODE),
            'fitness_goals' => json_encode([
                'primary_goals' => ['增肌', '减脂'],
                'target_weight' => 70,
                'goal_priority' => ['增肌' => 1, '减脂' => 2],
                'training_split' => '全身训练'
            ], JSON_UNESCAPED_UNICODE),
            'training_preferences' => json_encode([
                'training_split' => '全身训练',
                'available_equipment' => ['哑铃', '徒手'],
                'training_location' => '家里',
                'training_intensity' => 'moderate'
            ], JSON_UNESCAPED_UNICODE),
            'health_status' => json_encode([
                'chronic_diseases' => [],
                'injury_history' => [],
                'medications' => []
            ], JSON_UNESCAPED_UNICODE),
            'nutrition_profile' => json_encode([
                'user_settings' => [
                    'population_type' => '普通人群',
                    'dietary_preferences' => ['均衡饮食'],
                    'allergies' => [],
                    'supplements' => [],
                    'budget' => 'low'
                ]
            ], JSON_UNESCAPED_UNICODE),
            'strength_data' => null,
            'ffmi_assessment' => null,
        ];
    }
    
    /**
     * 高级用户档案
     */
    private function getAdvancedProfile(): array
    {
        return [
            'basic_info' => json_encode([
                'nickname' => '健身达人',
                'age' => 28,
                'gender' => 'male',
                'height' => 175,
                'weight' => 78,
                'body_fat_percentage' => 12,
                'fitness_level' => 'intermediate',
                'sleep_hours' => 8
            ], JSON_UNESCAPED_UNICODE),
            'fitness_goals' => json_encode([
                'primary_goals' => ['增强力量', '塑形'],
                'target_weight' => 80,
                'goal_priority' => ['增强力量' => 1, '塑形' => 2],
                'training_split' => '推拉腿'
            ], JSON_UNESCAPED_UNICODE),
            'training_preferences' => json_encode([
                'training_split' => '推拉腿',
                'available_equipment' => ['杠铃', '哑铃', '固定器械', '龙门架'],
                'training_location' => '健身房',
                'training_intensity' => 'high'
            ], JSON_UNESCAPED_UNICODE),
            'health_status' => json_encode([
                'chronic_diseases' => [],
                'injury_history' => ['肩部损伤'],
                'medications' => []
            ], JSON_UNESCAPED_UNICODE),
            'nutrition_profile' => json_encode([
                'user_settings' => [
                    'population_type' => '增肌期',
                    'dietary_preferences' => ['高蛋白', '低碳水'],
                    'allergies' => [],
                    'supplements' => ['蛋白粉', '肌酸', '支链氨基酸'],
                    'budget' => 'moderate'
                ],
                'auto_calculated' => [
                    'daily_calories' => 2600,
                    'daily_protein' => 156,
                    'daily_carbs' => 280,
                    'daily_fats' => 70
                ]
            ], JSON_UNESCAPED_UNICODE),
            'strength_data' => json_encode([
                'bench_press' => ['one_rm' => 90, 'three_rm' => 85],
                'squat' => ['one_rm' => 130, 'three_rm' => 120],
                'deadlift' => ['one_rm' => 160, 'three_rm' => 150],
                'overhead_press' => ['one_rm' => 60, 'three_rm' => 55]
            ]),
            'ffmi_assessment' => json_encode([
                'bmi' => 25.5,
                'bmi_status' => 'overweight',
                'lean_body_mass' => 68.64,
                'ffmi' => 22.4,
                'normalized_ffmi' => 22.2,
                'assessment' => '很好',
                'natural_potential' => '接近自然极限',
                'used_estimated_bf' => false,
                'calculated_at' => now()->toIso8601String()
            ]),
        ];
    }
}



































