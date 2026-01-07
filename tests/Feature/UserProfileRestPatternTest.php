<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 用户档案休息模式功能测试
 * 
 * 测试任务27的实现：前后端用户档案页面支持休息模式选择
 */
class UserProfileRestPatternTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private UserProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户（直接创建，不使用factory）
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // 创建用户档案
        $this->profile = UserProfile::create([
            'user_id' => $this->user->id,
            'basic_info' => [
                'nickname' => '测试用户',
                'age' => 25,
                'gender' => 'male',
                'height' => 175,
                'weight' => 70,
                'fitness_level' => 'intermediate',
            ],
            'fitness_goals' => [
                'primary_goals' => ['增肌'],
                'secondary_goals' => [],
                'goal_priority' => ['增肌' => 1],
                'training_split' => '推拉腿',
            ],
            'training_preferences' => [
                'training_split' => '推拉腿',
                'available_equipment' => ['杠铃', '哑铃'],
                'training_location' => '健身房',
            ],
            'health_status' => [
                'chronic_diseases' => [],
                'injury_history' => [],
                'medications' => [],
            ],
            'nutrition_profile' => [
                'user_settings' => [
                    'population_type' => '增肌期',
                    'dietary_preferences' => ['高蛋白'],
                    'allergies' => [],
                    'supplements' => [],
                    'budget' => 'moderate',
                ],
            ],
        ]);
    }

    /**
     * 测试：创建用户档案时可以设置休息模式
     */
    public function test_can_create_profile_with_rest_pattern()
    {
        $newUser = User::create([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        
        $profile = UserProfile::create([
            'user_id' => $newUser->id,
            'basic_info' => [
                'nickname' => '新用户',
                'age' => 30,
                'gender' => 'female',
                'height' => 165,
                'weight' => 55,
                'fitness_level' => 'beginner',
            ],
            'fitness_goals' => [
                'primary_goals' => ['减脂'],
                'secondary_goals' => [],
                'goal_priority' => ['减脂' => 1],
                'training_split' => '全身训练',
            ],
            'training_preferences' => [
                'training_split' => '全身训练',
                'available_equipment' => ['徒手'],
                'training_location' => '家里',
            ],
            'preferred_rest_pattern' => '练一休一',
            'health_status' => [
                'chronic_diseases' => [],
                'injury_history' => [],
                'medications' => [],
            ],
            'nutrition_profile' => [
                'user_settings' => [
                    'population_type' => '减脂期',
                    'dietary_preferences' => ['低碳水'],
                    'allergies' => [],
                    'supplements' => [],
                    'budget' => 'low',
                ],
            ],
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $newUser->id,
            'preferred_rest_pattern' => '练一休一',
        ]);

        $this->assertEquals('练一休一', $profile->preferred_rest_pattern);
    }

    /**
     * 测试：更新用户档案时可以修改休息模式
     */
    public function test_can_update_rest_pattern()
    {
        $this->profile->update([
            'preferred_rest_pattern' => '练三休一',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->user->id,
            'preferred_rest_pattern' => '练三休一',
        ]);

        $this->assertEquals('练三休一', $this->profile->fresh()->preferred_rest_pattern);
    }

    /**
     * 测试：获取推荐的休息模式（基于训练水平）
     */
    public function test_get_recommended_rest_pattern()
    {
        // 初学者
        $this->profile->update([
            'basic_info' => array_merge($this->profile->basic_info, [
                'fitness_level' => 'beginner',
            ]),
        ]);
        $this->assertEquals('练一休一', $this->profile->getRecommendedRestPattern());

        // 新手
        $this->profile->update([
            'basic_info' => array_merge($this->profile->basic_info, [
                'fitness_level' => 'novice',
            ]),
        ]);
        $this->assertEquals('练二休一', $this->profile->getRecommendedRestPattern());

        // 中级
        $this->profile->update([
            'basic_info' => array_merge($this->profile->basic_info, [
                'fitness_level' => 'intermediate',
            ]),
        ]);
        $this->assertEquals('练三休一', $this->profile->getRecommendedRestPattern());

        // 高级
        $this->profile->update([
            'basic_info' => array_merge($this->profile->basic_info, [
                'fitness_level' => 'advanced',
            ]),
        ]);
        $this->assertEquals('练四休一', $this->profile->getRecommendedRestPattern());
    }

    /**
     * 测试：休息模式验证规则（直接测试Request验证）
     */
    public function test_rest_pattern_validation()
    {
        $request = new \App\Modules\User\Requests\UpdateProfileRequest();
        
        $validator = \Illuminate\Support\Facades\Validator::make(
            [
                'preferred_rest_pattern' => '无效的休息模式',
            ],
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('preferred_rest_pattern'));
    }

    /**
     * 测试：休息模式可以为空
     */
    public function test_rest_pattern_can_be_null()
    {
        $this->profile->update([
            'preferred_rest_pattern' => null,
        ]);

        $this->assertNull($this->profile->fresh()->preferred_rest_pattern);
        
        // 当休息模式为空时，应该返回推荐的休息模式
        $this->assertEquals('练三休一', $this->profile->getRecommendedRestPattern());
    }

    /**
     * 测试：所有有效的休息模式选项
     */
    public function test_all_valid_rest_patterns()
    {
        $validPatterns = [
            '练一休一',
            '练二休一',
            '练三休一',
            '练四休一',
            '练五休一',
            '练六休一',
            '练七休一',
        ];

        foreach ($validPatterns as $pattern) {
            $this->profile->update([
                'preferred_rest_pattern' => $pattern,
            ]);

            $this->assertEquals($pattern, $this->profile->fresh()->preferred_rest_pattern);
        }
    }
}
