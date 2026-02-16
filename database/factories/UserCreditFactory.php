<?php

namespace Database\Factories;

use App\Models\UserCredit;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * UserCredit工厂 - 用于测试的积分记录生成
 *
 * @extends Factory<UserCredit>
 * @version v1.0.0
 * @date 2026-02-16
 */
class UserCreditFactory extends Factory
{
    protected $model = UserCredit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'daily_quota' => UserCredit::DAILY_QUOTAS['free'],
            'daily_consumed' => 0,
            'total_consumed' => 0,
            'last_reset_date' => today(),
        ];
    }

    /**
     * 暖心会员配额
     */
    public function warmheart(): static
    {
        return $this->state(fn () => [
            'daily_quota' => UserCredit::DAILY_QUOTAS['warmheart'],
        ]);
    }

    /**
     * 能量会员配额
     */
    public function energy(): static
    {
        return $this->state(fn () => [
            'daily_quota' => UserCredit::DAILY_QUOTAS['energy'],
        ]);
    }
}
