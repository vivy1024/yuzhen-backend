<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateTestUserSeeder extends Seeder
{
    /**
     * 创建测试用户（user_id=1）用于MVP测试
     */
    public function run(): void
    {
        // 1. 确保用户存在
        $userExists = DB::table('users')->where('id', 1)->exists();
        
        if (!$userExists) {
            DB::table('users')->insert([
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "✅ 用户创建成功: ID=1\n";
        } else {
            echo "✅ 用户已存在: ID=1\n";
        }

        // 2. 创建或更新用户档案（user_profiles表 - JSON格式）
        $profileExists = DB::table('user_profiles')->where('user_id', 1)->exists();
        
        $profileData = [
            'basic_info' => json_encode([
                'height' => 175,
                'weight' => 70,
                'age' => 25,
                'gender' => 'male',
            ]),
            'fitness_goals' => json_encode([
                'primary_goal' => 'muscle_gain',
                'training_experience' => 'intermediate',
            ]),
            'training_preferences' => json_encode([
                'training_frequency' => 4,
                'available_equipment' => ['哑铃', '杠铃', '固定器械'],
                'preferred_split_type' => 'upper_lower',
            ]),
            'preferred_rest_pattern' => 'train_4_rest_3',
            'health_status' => json_encode([
                'health_conditions' => [],
                'injury_history' => [],
            ]),
            'updated_at' => now(),
        ];
        
        if ($profileExists) {
            DB::table('user_profiles')->where('user_id', 1)->update($profileData);
            echo "✅ 用户档案更新成功: user_id=1\n";
        } else {
            $profileData['user_id'] = 1;
            $profileData['created_at'] = now();
            DB::table('user_profiles')->insert($profileData);
            echo "✅ 用户档案创建成功: user_id=1\n";
        }

        // 3. 创建或更新用户会员信息（user_memberships表）
        $membershipExists = DB::table('user_memberships')->where('user_id', 1)->exists();
        
        if ($membershipExists) {
            DB::table('user_memberships')->where('user_id', 1)->update([
                'membership_id' => 1, // 免费会员
                'is_active' => 1,
                'started_at' => now(),
                'expires_at' => now()->addYear(),
                'updated_at' => now(),
            ]);
            echo "✅ 用户会员信息更新成功: user_id=1\n";
        } else {
            DB::table('user_memberships')->insert([
                'user_id' => 1,
                'membership_id' => 1, // 免费会员
                'is_active' => 1,
                'started_at' => now(),
                'expires_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "✅ 用户会员信息创建成功: user_id=1\n";
        }

        echo "\n🎉 测试用户数据完整！\n";
        echo "   用户ID: 1\n";
        echo "   邮箱: test@example.com\n";
        echo "   密码: password\n";
    }
}
