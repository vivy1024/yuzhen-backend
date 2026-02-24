<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Membership;
use App\Models\UserMembership;
use Carbon\Carbon;

/**
 * 从前端localStorage数据恢复用户
 * 
 * 使用方法：
 * 1. 从前端提取localStorage数据（运行 extract_user_data.js）
 * 2. 将数据粘贴到 $frontendData 变量中
 * 3. 运行: php artisan db:seed --class=RestoreUserFromFrontendSeeder
 * 
 * @version 1.0.0
 * @date 2025-11-04
 */
class RestoreUserFromFrontendSeeder extends Seeder
{
    /**
     * 从前端提取的数据（粘贴到这里）
     * 
     * 格式：
    {
  "user_info": {
    "id": 1,
    "name": "Vivy",
    "email": "1765563156@qq.com",
    "role": "admin",
    "email_verified_at": "2025-11-01T18:01:03.000000Z",
    "onboarding_completed": 0,
    "status": 1,
    "del_flag": "0",
    "phone": "15314763713",
    "avatar": null,
    "is_active": 1,
    "last_login_at": "2025-11-03T19:57:55.000000Z",
    "membership_tier": "newbie",
    "preferences": null,
    "profile_completed_at": null,
    "favorites": null,
    "exercise_reviews": null,
    "created_at": "2025-11-01T18:01:03.000000Z",
    "updated_at": "2025-11-03T19:57:55.000000Z"
  },
  "user_profile_v2": {
    "userId": 1,
    "profile": {
      "user_id": 1,
      "basic_info": {
        "nickname": "vivy",
        "age": 25,
        "gender": "male",
        "height": 170,
        "weight": 64,
        "body_fat_percentage": 15,
        "fitness_level": "intermediate",
        "region": "西北地区",
        "sleep_hours": 8
      },
      "fitness_goals": {
        "primary_goals": [
          "增肌",
          "增强力量"
        ],
        "secondary_goals": [
          "减脂"
        ],
        "goal_priority": {},
        "training_split": "推拉腿",
        "target_weight": 70
      },
      "training_preferences": {
        "training_split": "推拉腿",
        "available_equipment": [
          "杠铃",
          "哑铃",
          "固定器械",
          "徒手",
          "自由重量架",
          "龙门架"
        ],
        "training_location": "健身房",
        "exercise_preferences": [
          "复合动作",
          "力量训练",
          "固定器械"
        ],
        "disliked_exercises": [
          "推举"
        ],
        "training_intensity": "moderate"
      },
      "strength_data": {
        "dip": {
          "one_rm": 30
        },
        "squat": {
          "one_rm": 120
        },
        "pull_up": {
          "one_rm": 30
        },
        "deadlift": {
          "one_rm": 80
        },
        "bench_press": {
          "one_rm": 80
        },
        "overhead_press": {
          "one_rm": 50
        }
      },
      "health_status": {
        "chronic_diseases": [
          "无"
        ],
        "injury_history": [
          "无"
        ],
        "medications": [
          "无"
        ],
        "other_notes": "胸椎反曲"
      },
      "nutrition_profile": {
        "user_settings": {
          "budget": "moderate",
          "allergies": [
            "无"
          ],
          "supplements": [
            "肌酸",
            "蛋白粉",
            "维生素"
          ],
          "population_type": "增肌期",
          "dietary_preferences": [
            "均衡饮食"
          ]
        },
        "auto_calculated": null
      },
      "ffmi_assessment": {
        "bmi": 22.1,
        "ffmi": 18.8,
        "assessment": "优秀",
        "bmi_status": "normal",
        "calculated_at": "2025-11-03T18:22:22.831Z",
        "lean_body_mass": 54.4,
        "normalized_ffmi": 19.4,
        "natural_potential": "还有很大空间",
        "used_estimated_bf": false,
        "training_recommendation": {
          "focus": "基础建设",
          "suggestions": [
            "规律力量训练（每星期3-5次）",
            "合理营养摄入（蛋白质每kg体重1.6-2g）",
            "循序渐进，避免急功近利",
            "重视恢复，确保充足睡眠"
          ]
        }
      },
      "created_at": "2025-11-01T18:01:03.000000Z",
      "updated_at": "2025-11-03T18:29:25.000000Z",
      "version": 1
    },
    "savedAt": "2025-11-03T19:58:33.667Z",
    "syncedToServer": true
  },
  "current_user_id": 1,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDIiLCJpYXQiOjE3NjIxOTk4NzUsImV4cCI6MTc2MjIwMzQ3NSwidXNlcl9pZCI6MSwidXNlcm5hbWUiOm51bGwsInJvbGUiOiJhZG1pbiJ9.Y8kXsWjv3oV3dw4aM4On6ALDq13tUZB3Na2fmgE0WqM"
}
     */
    private $frontendData = null;
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ============================================
        // 📝 请将从前端提取的JSON数据粘贴到下面
        // ============================================
        
        $this->frontendData = <<<'JSON'
{
  "user_info": {
    "id": 1,
    "name": "Vivy",
    "email": "1765563156@qq.com",
    "role": "admin",
    "email_verified_at": "2025-11-01T18:01:03.000000Z",
    "onboarding_completed": 0,
    "status": 1,
    "del_flag": "0",
    "phone": "15314763713",
    "avatar": null,
    "is_active": 1,
    "last_login_at": "2025-11-03T19:57:55.000000Z",
    "membership_tier": "newbie",
    "preferences": null,
    "profile_completed_at": null,
    "favorites": null,
    "exercise_reviews": null,
    "created_at": "2025-11-01T18:01:03.000000Z",
    "updated_at": "2025-11-03T19:57:55.000000Z"
  },
  "user_profile_v2": {
    "userId": 1,
    "profile": {
      "user_id": 1,
      "basic_info": {
        "nickname": "vivy",
        "age": 25,
        "gender": "male",
        "height": 170,
        "weight": 64,
        "body_fat_percentage": 15,
        "fitness_level": "intermediate",
        "region": "西北地区",
        "sleep_hours": 8
      },
      "fitness_goals": {
        "primary_goals": [
          "增肌",
          "增强力量"
        ],
        "secondary_goals": [
          "减脂"
        ],
        "goal_priority": {},
        "training_split": "推拉腿",
        "target_weight": 70
      },
      "training_preferences": {
        "training_split": "推拉腿",
        "available_equipment": [
          "杠铃",
          "哑铃",
          "固定器械",
          "徒手",
          "自由重量架",
          "龙门架"
        ],
        "training_location": "健身房",
        "exercise_preferences": [
          "复合动作",
          "力量训练",
          "固定器械"
        ],
        "disliked_exercises": [
          "推举"
        ],
        "training_intensity": "moderate"
      },
      "strength_data": {
        "dip": {
          "one_rm": 30
        },
        "squat": {
          "one_rm": 120
        },
        "pull_up": {
          "one_rm": 30
        },
        "deadlift": {
          "one_rm": 80
        },
        "bench_press": {
          "one_rm": 80
        },
        "overhead_press": {
          "one_rm": 50
        }
      },
      "health_status": {
        "chronic_diseases": [
          "无"
        ],
        "injury_history": [
          "无"
        ],
        "medications": [
          "无"
        ],
        "other_notes": "胸椎反曲"
      },
      "nutrition_profile": {
        "user_settings": {
          "budget": "moderate",
          "allergies": [
            "无"
          ],
          "supplements": [
            "肌酸",
            "蛋白粉",
            "维生素"
          ],
          "population_type": "增肌期",
          "dietary_preferences": [
            "均衡饮食"
          ]
        },
        "auto_calculated": null
      },
      "ffmi_assessment": {
        "bmi": 22.1,
        "ffmi": 18.8,
        "assessment": "优秀",
        "bmi_status": "normal",
        "calculated_at": "2025-11-03T18:22:22.831Z",
        "lean_body_mass": 54.4,
        "normalized_ffmi": 19.4,
        "natural_potential": "还有很大空间",
        "used_estimated_bf": false,
        "training_recommendation": {
          "focus": "基础建设",
          "suggestions": [
            "规律力量训练（每星期3-5次）",
            "合理营养摄入（蛋白质每kg体重1.6-2g）",
            "循序渐进，避免急功近利",
            "重视恢复，确保充足睡眠"
          ]
        }
      },
      "created_at": "2025-11-01T18:01:03.000000Z",
      "updated_at": "2025-11-03T18:29:25.000000Z",
      "version": 1
    },
    "savedAt": "2025-11-03T19:58:33.667Z",
    "syncedToServer": true
  },
  "current_user_id": 1
}
JSON;
        
        // ============================================
        
        $data = json_decode($this->frontendData, true);
        
        if (!$data || !isset($data['user_profile_v2'])) {
            $this->command->error('❌ 数据格式错误！请检查 $frontendData 变量');
            $this->command->info('');
            $this->command->info('📖 使用方法：');
            $this->command->info('1. 在浏览器控制台运行 extract_user_data.js');
            $this->command->info('2. 复制输出的JSON数据');
            $this->command->info('3. 粘贴到此文件的 $frontendData 变量中');
            $this->command->info('4. 重新运行此Seeder');
            return;
        }
        
        $this->command->info('🔄 开始恢复用户数据...');
        $this->command->info('');
        
        try {
            DB::beginTransaction();
            
            // 1. 恢复用户基本信息
            $userId = $this->restoreUserInfo($data);
            
            // 2. 恢复用户档案
            $this->restoreUserProfile($userId, $data);
            
            // 3. 分配会员等级
            $this->assignMembership($userId, $data);
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('✅ 用户数据恢复完成！');
            $this->command->info('');
            $this->command->info("👤 用户ID: {$userId}");
            $this->command->info("📧 邮箱: {$data['user_info']['email']}");
            $this->command->info("🔑 默认密码: password123");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ 恢复失败: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 恢复用户基本信息
     */
    private function restoreUserInfo(array $data): int
    {
        $userInfo = $data['user_info'] ?? null;
        
        if (!$userInfo) {
            throw new \Exception('缺少用户基本信息');
        }
        
        $this->command->info('📝 恢复用户基本信息...');
        
        // 检查用户是否已存在
        $existingUser = DB::table('users')->where('email', $userInfo['email'])->first();
        if ($existingUser) {
            $this->command->warn("  ⚠️  用户已存在（ID: {$existingUser->id}），将更新数据");
            
            DB::table('users')->where('id', $existingUser->id)->update([
                'name' => $userInfo['nickname'] ?? $userInfo['name'],
                'email' => $userInfo['email'],
                'phone' => $userInfo['phone'] ?? null,
                'avatar' => $userInfo['avatar'] ?? null,
                'membership_tier' => $userInfo['membership_tier'] ?? 'free',
                'preferences' => isset($userInfo['preferences']) ? json_encode($userInfo['preferences']) : null,
                'favorites' => isset($userInfo['favorites']) ? json_encode($userInfo['favorites']) : json_encode([]),
                'onboarding_completed' => true,
                'profile_completed_at' => now(),
                'updated_at' => now(),
            ]);
            
            return $existingUser->id;
        }
        
        // 创建新用户
        $userId = DB::table('users')->insertGetId([
            'name' => $userInfo['nickname'] ?? $userInfo['name'],
            'email' => $userInfo['email'],
            'phone' => $userInfo['phone'] ?? null,
            'avatar' => $userInfo['avatar'] ?? null,
            'role' => 'user',
            'status' => '1',
            'del_flag' => '0',
            'is_active' => true,
            'membership_tier' => $userInfo['membership_tier'] ?? 'free',
            'preferences' => isset($userInfo['preferences']) ? json_encode($userInfo['preferences']) : null,
            'favorites' => isset($userInfo['favorites']) ? json_encode($userInfo['favorites']) : json_encode([]),
            'exercise_reviews' => json_encode([]),
            'email_verified_at' => now(),
            'onboarding_completed' => true,
            'profile_completed_at' => now(),
            'password' => Hash::make('password123'), // 默认密码
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->command->info("  ✓ 用户创建成功（ID: {$userId}）");
        
        return $userId;
    }
    
    /**
     * 恢复用户档案
     */
    private function restoreUserProfile(int $userId, array $data): void
    {
        $profileData = $data['user_profile_v2'] ?? null;
        
        if (!$profileData || !isset($profileData['profile'])) {
            $this->command->warn('  ⚠️  未找到用户档案数据，跳过');
            return;
        }
        
        $this->command->info('📊 恢复用户档案...');
        
        $profile = $profileData['profile'];
        
        // 检查档案是否已存在
        $existingProfile = DB::table('user_profiles')->where('user_id', $userId)->first();
        
        $profileRecord = [
            'user_id' => $userId,
            'basic_info' => isset($profile['basic_info']) ? json_encode($profile['basic_info']) : null,
            'fitness_goals' => isset($profile['fitness_goals']) ? json_encode($profile['fitness_goals']) : null,
            'training_preferences' => isset($profile['training_preferences']) ? json_encode($profile['training_preferences']) : null,
            'health_status' => isset($profile['health_status']) ? json_encode($profile['health_status']) : null,
            'nutrition_profile' => isset($profile['nutrition_profile']) ? json_encode($profile['nutrition_profile']) : null,
            'strength_data' => isset($profile['strength_data']) ? json_encode($profile['strength_data']) : null,
            'ffmi_assessment' => isset($profile['ffmi_assessment']) ? json_encode($profile['ffmi_assessment']) : null,
            'version' => $profile['version'] ?? 1,
            'sync_status' => 'synced',
            'is_mcp_temp' => false,
            'updated_at' => now(),
        ];
        
        if ($existingProfile) {
            DB::table('user_profiles')->where('user_id', $userId)->update($profileRecord);
            $this->command->info('  ✓ 用户档案更新成功');
        } else {
            $profileRecord['created_at'] = now();
            DB::table('user_profiles')->insert($profileRecord);
            $this->command->info('  ✓ 用户档案创建成功');
        }
        
        // 显示档案摘要
        if (isset($profile['basic_info'])) {
            $basic = $profile['basic_info'];
            $this->command->info("     昵称: {$basic['nickname']}");
            $this->command->info("     年龄: {$basic['age']} 岁");
            $this->command->info("     身高: {$basic['height']} cm");
            $this->command->info("     体重: {$basic['weight']} kg");
            $this->command->info("     健身水平: {$basic['fitness_level']}");
        }
    }
    
    /**
     * 分配会员等级
     */
    private function assignMembership(int $userId, array $data): void
    {
        $this->command->info('🎫 分配会员等级...');
        
        // 从前端数据获取会员等级标识
        $membershipTier = $data['user_info']['membership_tier'] ?? 'newbie';
        
        // 映射前端的tier到数据库的tier
        $tierMapping = [
            'newbie' => 'free',
            'free' => 'free',
            'warmheart' => 'warmheart',
            'energy' => 'energy',
        ];
        
        $dbTier = $tierMapping[$membershipTier] ?? 'free';
        
        // 查找对应的会员等级
        $membership = Membership::findByTier($dbTier);
        
        if (!$membership) {
            $this->command->warn("  ⚠️  未找到会员等级 {$dbTier}，将分配免费会员");
            $membership = Membership::findByTier('free');
        }
        
        if (!$membership) {
            $this->command->error('  ❌ 数据库中没有会员等级数据，请先运行 MembershipSeeder');
            return;
        }
        
        // 检查用户是否已有会员关联
        $existingMembership = UserMembership::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
        
        if ($existingMembership) {
            // 更新现有会员关联
            $existingMembership->update([
                'membership_id' => $membership->id,
                'started_at' => now(),
                'expires_at' => $membership->duration_days >= 365 * 10 
                    ? null  // 永久会员
                    : Carbon::now()->addDays($membership->duration_days),
                'is_active' => true,
            ]);
            
            $this->command->info("  ✓ 会员等级已更新: {$membership->name}");
        } else {
            // 创建新的会员关联
            UserMembership::create([
                'user_id' => $userId,
                'membership_id' => $membership->id,
                'order_id' => null,  // 恢复的用户没有订单ID
                'started_at' => now(),
                'expires_at' => $membership->duration_days >= 365 * 10 
                    ? null  // 永久会员
                    : Carbon::now()->addDays($membership->duration_days),
                'is_active' => true,
            ]);
            
            $this->command->info("  ✓ 会员等级已分配: {$membership->name}");
        }
        
        // 同步更新users表的membership_tier字段
        DB::table('users')->where('id', $userId)->update([
            'membership_tier' => $dbTier,
        ]);
        
        $this->command->info("     等级: {$membership->name}");
        $this->command->info("     权益: " . ($membership->unlock_all_exercises ? '完整动作库' : '基础功能'));
    }
}

