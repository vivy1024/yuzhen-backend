<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Membership;
use App\Models\UserMembership;
use Carbon\Carbon;

/**
 * 恢复Vivy用户数据
 * 从前端localStorage提取的数据
 * 
 * 运行: docker exec fitness_php_v2 php artisan db:seed --class=RestoreVivyUserSeeder
 */
class RestoreVivyUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 开始恢复Vivy用户数据...');
        $this->command->info('');
        
        try {
            DB::beginTransaction();
            
            // 用户数据
            $userData = [
                'id' => 2,
                'name' => 'Vivy',
                'email' => '1765563156@qq.com',
                'phone' => '15314763713',
                'avatar' => null,
                'role' => 'admin',
                'status' => 1,
                'del_flag' => '0',
                'is_active' => 1,
                'membership_tier' => 'energy',
                'preferences' => null,
                'favorites' => json_encode([]),
                'exercise_reviews' => json_encode([]),
                'email_verified_at' => '2025-11-04 19:33:57',
                'onboarding_completed' => 1,
                'profile_completed_at' => '2025-11-04 19:58:40',
                'password' => Hash::make('password123'),
                'created_at' => '2025-11-04 19:33:57',
                'updated_at' => '2025-12-16 08:07:50',
            ];
            
            // 用户档案数据
            $profileData = [
                'user_id' => 2,
                'basic_info' => json_encode([
                    'nickname' => 'vivy',
                    'age' => 22,
                    'gender' => 'male',
                    'height' => 170,
                    'weight' => 62,
                    'body_fat_percentage' => 15,
                    'fitness_level' => 'intermediate',
                    'region' => '西北地区',
                    'sleep_hours' => 8
                ]),
                'fitness_goals' => json_encode([
                    'primary_goals' => ['增肌', '增强力量'],
                    'secondary_goals' => ['减脂'],
                    'goal_priority' => new \stdClass(),
                    'training_split' => '推拉腿',
                    'target_weight' => 70
                ]),
                'training_preferences' => json_encode([
                    'training_split' => '推拉腿',
                    'available_equipment' => ['杠铃', '哑铃', '自由重量架', '固定器械', '龙门架', '徒手', '史密斯架'],
                    'training_location' => '健身房',
                    'exercise_preferences' => ['复合动作', '固定器械', '力量训练'],
                    'disliked_exercises' => ['推举'],
                    'training_intensity' => 'moderate'
                ]),
                'strength_data' => json_encode([
                    'dip' => ['one_rm' => 30],
                    'squat' => ['one_rm' => 105],
                    'pull_up' => ['one_rm' => 30],
                    'deadlift' => ['one_rm' => 100],
                    'bench_press' => ['one_rm' => 80],
                    'overhead_press' => ['one_rm' => 50]
                ]),
                'health_status' => json_encode([
                    'chronic_diseases' => ['无'],
                    'injury_history' => ['无'],
                    'medications' => ['无'],
                    'other_notes' => '胸椎反曲'
                ]),
                'nutrition_profile' => json_encode([
                    'user_settings' => [
                        'budget' => 'low',
                        'allergies' => ['无'],
                        'supplements' => ['蛋白粉', '维生素', '肌酸'],
                        'population_type' => '恢复期',
                        'dietary_preferences' => ['均衡饮食']
                    ],
                    'auto_calculated' => null
                ]),
                'ffmi_assessment' => null,
                'version' => 1,
                'sync_status' => 'synced',
                'is_mcp_temp' => false,
                'created_at' => '2025-11-04 19:33:57',
                'updated_at' => '2025-12-16 09:03:02',
            ];
            
            // 聊天主题数据
            $chatTopics = [
                [
                    'user_id' => 2,
                    'title' => '新话题 1',
                    'message_count' => 3,
                    'last_message_at' => '2025-12-20 14:25:19',
                    'created_at' => '2025-12-20 14:24:12',
                    'updated_at' => '2025-12-20 14:25:19',
                ]
            ];
            
            // 1. 恢复用户
            $existingUser = DB::table('users')->where('email', $userData['email'])->first();
            if ($existingUser) {
                $this->command->warn("  ⚠️  用户已存在（ID: {$existingUser->id}），将更新数据");
                DB::table('users')->where('id', $existingUser->id)->update($userData);
                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId($userData);
                $this->command->info("  ✓ 用户创建成功（ID: {$userId}）");
            }
            
            // 2. 恢复用户档案
            $existingProfile = DB::table('user_profiles')->where('user_id', $userId)->first();
            if ($existingProfile) {
                DB::table('user_profiles')->where('user_id', $userId)->update($profileData);
                $this->command->info('  ✓ 用户档案更新成功');
            } else {
                DB::table('user_profiles')->insert($profileData);
                $this->command->info('  ✓ 用户档案创建成功');
            }
            
            // 3. 分配会员等级
            $membership = Membership::findByTier('energy');
            if ($membership) {
                $existingMembership = DB::table('user_memberships')
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();
                
                if ($existingMembership) {
                    DB::table('user_memberships')
                        ->where('id', $existingMembership->id)
                        ->update([
                            'membership_id' => $membership->id,
                            'started_at' => now(),
                            'expires_at' => null,
                            'is_active' => true,
                        ]);
                } else {
                    DB::table('user_memberships')->insert([
                        'user_id' => $userId,
                        'membership_id' => $membership->id,
                        'order_id' => null,
                        'started_at' => now(),
                        'expires_at' => null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $this->command->info("  ✓ 会员等级已分配: {$membership->name}");
            }
            
            // 4. 聊天记录暂时跳过（表结构不匹配，前端localStorage已保留）
            $this->command->info('  ⚠️  聊天记录保留在前端localStorage中');
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('✅ Vivy用户数据恢复完成！');
            $this->command->info('');
            $this->command->info("👤 用户ID: {$userId}");
            $this->command->info("📧 邮箱: {$userData['email']}");
            $this->command->info("🔑 密码: password123");
            $this->command->info("🎫 会员等级: 能量会员（永久）");
            $this->command->info('');
            $this->command->info('📊 用户档案摘要:');
            $this->command->info('   昵称: vivy');
            $this->command->info('   年龄: 22 岁');
            $this->command->info('   身高: 170 cm');
            $this->command->info('   体重: 62 kg');
            $this->command->info('   健身水平: intermediate');
            $this->command->info('   训练目标: 增肌、增强力量');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ 恢复失败: ' . $e->getMessage());
            throw $e;
        }
    }
}
