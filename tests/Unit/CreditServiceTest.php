<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CreditService;
use App\Models\UserCredit;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * CreditService 单元测试
 * 
 * 测试积分计算方法 calculateCredits() 和 getBalance()
 * 
 * @version v2.0.0
 * @date 2026-02-05
 * @requirements 1.1-1.5, 4.1-4.5
 */
class CreditServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private CreditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreditService();
    }
    
    /**
     * 创建测试用户
     */
    private function createTestUser(string $membershipTier = 'free'): User
    {
        return User::create([
            'name' => 'Test User ' . uniqid(),
            'email' => 'test' . uniqid() . '@example.com',
            'password' => Hash::make('Test@123456'),
            'email_verified_at' => now(),
            'membership_tier' => $membershipTier,
        ]);
    }

    /**
     * 测试最小积分消耗（0 tokens）
     * 
     * @requirements 1.5
     */
    public function test_minimum_credit_consumption_with_zero_tokens(): void
    {
        // 0 tokens 应该消耗 1 积分（最小消耗）
        $this->assertEquals(1, $this->service->calculateCredits(0, 'dag'));
        $this->assertEquals(1, $this->service->calculateCredits(0, 'agent'));
    }

    /**
     * 测试最小积分消耗（少量 tokens）
     * 
     * @requirements 1.5
     */
    public function test_minimum_credit_consumption_with_small_tokens(): void
    {
        // 1 token 应该消耗 1 积分
        $this->assertEquals(1, $this->service->calculateCredits(1, 'dag'));
        
        // 999 tokens 应该消耗 1 积分（ceil(999/1000) = 1）
        $this->assertEquals(1, $this->service->calculateCredits(999, 'dag'));
        
        // 1000 tokens 应该消耗 1 积分（ceil(1000/1000) = 1）
        $this->assertEquals(1, $this->service->calculateCredits(1000, 'dag'));
    }

    /**
     * 测试 DAG 模式积分计算（1.0x 倍率）
     * 
     * @requirements 1.1, 1.3
     */
    public function test_dag_mode_credit_calculation(): void
    {
        // 1001 tokens = ceil(1001 * 1.0 / 1000) = ceil(1.001) = 2 积分
        $this->assertEquals(2, $this->service->calculateCredits(1001, 'dag'));
        
        // 2000 tokens = ceil(2000 * 1.0 / 1000) = 2 积分
        $this->assertEquals(2, $this->service->calculateCredits(2000, 'dag'));
        
        // 2500 tokens = ceil(2500 * 1.0 / 1000) = ceil(2.5) = 3 积分
        $this->assertEquals(3, $this->service->calculateCredits(2500, 'dag'));
        
        // 5000 tokens = ceil(5000 * 1.0 / 1000) = 5 积分
        $this->assertEquals(5, $this->service->calculateCredits(5000, 'dag'));
    }

    /**
     * 测试 Agent 模式积分计算（1.5x 倍率）
     * 
     * @requirements 1.1, 1.2
     */
    public function test_agent_mode_credit_calculation(): void
    {
        // 1000 tokens = ceil(1000 * 1.5 / 1000) = ceil(1.5) = 2 积分
        $this->assertEquals(2, $this->service->calculateCredits(1000, 'agent'));
        
        // 667 tokens = ceil(667 * 1.5 / 1000) = ceil(1.0005) = 2 积分
        $this->assertEquals(2, $this->service->calculateCredits(667, 'agent'));
        
        // 666 tokens = ceil(666 * 1.5 / 1000) = ceil(0.999) = 1 积分
        $this->assertEquals(1, $this->service->calculateCredits(666, 'agent'));
        
        // 2000 tokens = ceil(2000 * 1.5 / 1000) = ceil(3.0) = 3 积分
        $this->assertEquals(3, $this->service->calculateCredits(2000, 'agent'));
        
        // 5000 tokens = ceil(5000 * 1.5 / 1000) = ceil(7.5) = 8 积分
        $this->assertEquals(8, $this->service->calculateCredits(5000, 'agent'));
    }

    /**
     * 测试向上取整（ceiling rounding）
     * 
     * @requirements 1.4
     */
    public function test_ceiling_rounding(): void
    {
        // DAG 模式：1001 tokens = ceil(1.001) = 2
        $this->assertEquals(2, $this->service->calculateCredits(1001, 'dag'));
        
        // Agent 模式：1001 tokens = ceil(1.5015) = 2
        $this->assertEquals(2, $this->service->calculateCredits(1001, 'agent'));
        
        // 边界测试：确保不会向下取整
        // 1500 tokens DAG = ceil(1.5) = 2
        $this->assertEquals(2, $this->service->calculateCredits(1500, 'dag'));
        
        // 1500 tokens Agent = ceil(2.25) = 3
        $this->assertEquals(3, $this->service->calculateCredits(1500, 'agent'));
    }

    /**
     * 测试模式大小写不敏感
     */
    public function test_mode_case_insensitive(): void
    {
        $tokens = 2000;
        
        // Agent 模式各种大小写
        $this->assertEquals(3, $this->service->calculateCredits($tokens, 'agent'));
        $this->assertEquals(3, $this->service->calculateCredits($tokens, 'Agent'));
        $this->assertEquals(3, $this->service->calculateCredits($tokens, 'AGENT'));
        
        // DAG 模式各种大小写
        $this->assertEquals(2, $this->service->calculateCredits($tokens, 'dag'));
        $this->assertEquals(2, $this->service->calculateCredits($tokens, 'DAG'));
        $this->assertEquals(2, $this->service->calculateCredits($tokens, 'Dag'));
    }

    /**
     * 测试未知模式默认使用 DAG 倍率
     */
    public function test_unknown_mode_uses_dag_multiplier(): void
    {
        $tokens = 2000;
        
        // 未知模式应该使用 DAG 倍率（1.0x）
        $this->assertEquals(2, $this->service->calculateCredits($tokens, 'unknown'));
        $this->assertEquals(2, $this->service->calculateCredits($tokens, ''));
        $this->assertEquals(2, $this->service->calculateCredits($tokens, 'other'));
    }

    /**
     * 测试大量 tokens 的计算
     */
    public function test_large_token_calculation(): void
    {
        // 100000 tokens DAG = ceil(100000 / 1000) = 100 积分
        $this->assertEquals(100, $this->service->calculateCredits(100000, 'dag'));
        
        // 100000 tokens Agent = ceil(100000 * 1.5 / 1000) = 150 积分
        $this->assertEquals(150, $this->service->calculateCredits(100000, 'agent'));
        
        // 1000000 tokens DAG = 1000 积分
        $this->assertEquals(1000, $this->service->calculateCredits(1000000, 'dag'));
        
        // 1000000 tokens Agent = 1500 积分
        $this->assertEquals(1500, $this->service->calculateCredits(1000000, 'agent'));
    }

    /**
     * 测试常量定义正确
     */
    public function test_constants_are_correct(): void
    {
        $this->assertEquals(1000, CreditService::TOKENS_PER_CREDIT);
        $this->assertEquals(1.5, CreditService::AGENT_MULTIPLIER);
        $this->assertEquals(1.0, CreditService::DAG_MULTIPLIER);
        $this->assertEquals(1, CreditService::MIN_CREDITS);
    }

    /**
     * 测试每日配额常量
     */
    public function test_daily_quota_constants(): void
    {
        $this->assertEquals(10, CreditService::DAILY_QUOTAS['free']);
        $this->assertEquals(50, CreditService::DAILY_QUOTAS['warmheart']);
        $this->assertEquals(200, CreditService::DAILY_QUOTAS['energy']);
    }

    // ==================== getBalance() 方法测试 ====================

    /**
     * 测试获取余额返回正确的数据结构
     * 
     * @requirements 4.1, 4.2, 4.3, 4.4
     */
    public function test_get_balance_returns_correct_structure(): void
    {
        $user = $this->createTestUser('warmheart');
        
        $balance = $this->service->getBalance($user->id);
        
        // 验证返回结构包含所有必要字段
        $this->assertArrayHasKey('daily_quota', $balance);
        $this->assertArrayHasKey('daily_consumed', $balance);
        $this->assertArrayHasKey('remaining', $balance);
        $this->assertArrayHasKey('total_consumed', $balance);
        $this->assertArrayHasKey('membership_tier', $balance);
        $this->assertArrayHasKey('is_mvp_phase', $balance);
        $this->assertArrayHasKey('low_balance_warning', $balance);
        $this->assertArrayHasKey('last_reset', $balance);
    }

    /**
     * 测试免费用户获取正确的每日配额
     * 
     * @requirements 2.1, 4.2
     */
    public function test_get_balance_free_user_quota(): void
    {
        $user = $this->createTestUser('free');
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertEquals(10, $balance['daily_quota']);
        $this->assertEquals('free', $balance['membership_tier']);
    }

    /**
     * 测试暖心会员获取正确的每日配额
     * 
     * @requirements 2.2, 4.2
     */
    public function test_get_balance_warmheart_member_quota(): void
    {
        $user = $this->createTestUser('warmheart');
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertEquals(50, $balance['daily_quota']);
        $this->assertEquals('warmheart', $balance['membership_tier']);
    }

    /**
     * 测试能量会员获取正确的每日配额
     * 
     * @requirements 2.3, 4.2
     */
    public function test_get_balance_energy_member_quota(): void
    {
        $user = $this->createTestUser('energy');
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertEquals(200, $balance['daily_quota']);
        $this->assertEquals('energy', $balance['membership_tier']);
    }

    /**
     * 测试剩余积分计算正确
     * 
     * @requirements 4.4
     */
    public function test_get_balance_remaining_calculation(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 创建积分记录并消耗一些积分
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 12;
        $userCredit->save();
        
        $balance = $this->service->getBalance($user->id);
        
        // remaining = daily_quota - daily_consumed = 50 - 12 = 38
        $this->assertEquals(50, $balance['daily_quota']);
        $this->assertEquals(12, $balance['daily_consumed']);
        $this->assertEquals(38, $balance['remaining']);
    }

    /**
     * 测试低余额警告（剩余低于20%时触发）
     * 
     * @requirements 4.5
     */
    public function test_get_balance_low_balance_warning(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 消耗超过80%的配额（50 * 0.8 = 40）
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 45; // 剩余5，低于20%（10）
        $userCredit->save();
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertTrue($balance['low_balance_warning']);
        $this->assertEquals(5, $balance['remaining']);
    }

    /**
     * 测试正常余额时无警告
     * 
     * @requirements 4.5
     */
    public function test_get_balance_no_warning_when_sufficient(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 消耗少于80%的配额
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 30; // 剩余20，高于20%（10）
        $userCredit->save();
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertFalse($balance['low_balance_warning']);
        $this->assertEquals(20, $balance['remaining']);
    }

    /**
     * 测试MVP阶段标志为false（已结束）
     */
    public function test_get_balance_mvp_phase_is_false(): void
    {
        $user = $this->createTestUser('free');
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertFalse($balance['is_mvp_phase']);
    }

    /**
     * 测试newbie会员等级标准化为free
     */
    public function test_get_balance_newbie_normalized_to_free(): void
    {
        $user = $this->createTestUser('newbie');
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertEquals('free', $balance['membership_tier']);
        $this->assertEquals(10, $balance['daily_quota']);
    }

    /**
     * 测试last_reset日期格式正确
     */
    public function test_get_balance_last_reset_date_format(): void
    {
        $user = $this->createTestUser('free');
        
        $balance = $this->service->getBalance($user->id);
        
        // 验证日期格式为 Y-m-d
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $balance['last_reset']);
    }

    /**
     * 测试total_consumed累计正确
     */
    public function test_get_balance_total_consumed(): void
    {
        $user = $this->createTestUser('warmheart');
        
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->total_consumed = 1250;
        $userCredit->save();
        
        $balance = $this->service->getBalance($user->id);
        
        $this->assertEquals(1250, $balance['total_consumed']);
    }

    // ==================== recordTransaction() 方法测试 ====================

    /**
     * 测试记录交易成功
     * 
     * @requirements 3.1, 3.2, 3.3, 5.1
     */
    public function test_record_transaction_success(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 确保用户有足够的积分
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $this->assertEquals(50, $userCredit->daily_quota);
        $this->assertEquals(0, $userCredit->daily_consumed);
        
        $data = [
            'tokens' => 2500,
            'mode' => 'dag',
            'template_name' => 'exercise_optimization',
            'conversation_id' => 'conv_test_123',
            'input_tokens' => 800,
            'output_tokens' => 1700,
            'description' => '测试交易',
        ];
        
        $transaction = $this->service->recordTransaction($user->id, $data);
        
        // 验证交易记录
        $this->assertNotNull($transaction);
        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals(3, $transaction->credits); // ceil(2500/1000) = 3
        $this->assertEquals(2500, $transaction->tokens);
        $this->assertEquals('dag', $transaction->mode);
        $this->assertEquals('exercise_optimization', $transaction->template_name);
        $this->assertEquals('conv_test_123', $transaction->conversation_id);
        $this->assertEquals(800, $transaction->input_tokens);
        $this->assertEquals(1700, $transaction->output_tokens);
        $this->assertEquals('测试交易', $transaction->description);
        
        // 验证积分已扣除
        $userCredit->refresh();
        $this->assertEquals(3, $userCredit->daily_consumed);
        $this->assertEquals(3, $userCredit->total_consumed);
        $this->assertEquals(47, $userCredit->remaining);
    }

    /**
     * 测试Agent模式记录交易（1.5x倍率）
     * 
     * @requirements 1.2, 3.1, 5.1
     */
    public function test_record_transaction_agent_mode(): void
    {
        $user = $this->createTestUser('energy');
        
        $data = [
            'tokens' => 2000,
            'mode' => 'agent',
            'template_name' => null,
            'conversation_id' => 'conv_agent_123',
        ];
        
        $transaction = $this->service->recordTransaction($user->id, $data);
        
        // Agent模式：ceil(2000 * 1.5 / 1000) = 3 积分
        $this->assertEquals(3, $transaction->credits);
        $this->assertEquals('agent', $transaction->mode);
        
        // 验证积分已扣除
        $userCredit = UserCredit::getByUserId($user->id);
        $this->assertEquals(3, $userCredit->daily_consumed);
    }

    /**
     * 测试积分不足时抛出异常
     * 
     * @requirements 5.2
     */
    public function test_record_transaction_insufficient_credits(): void
    {
        $user = $this->createTestUser('free'); // 免费用户只有10积分
        
        // 消耗大部分积分
        $userCredit = UserCredit::getOrCreate($user->id, 'free');
        $userCredit->daily_consumed = 9;
        $userCredit->save();
        
        $data = [
            'tokens' => 5000, // 需要5积分，但只剩1积分
            'mode' => 'dag',
        ];
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('积分不足');
        
        $this->service->recordTransaction($user->id, $data);
    }

    /**
     * 测试缺少必要参数时抛出异常
     */
    public function test_record_transaction_missing_tokens(): void
    {
        $user = $this->createTestUser('free');
        
        $data = [
            'mode' => 'dag',
            // 缺少 tokens
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tokens和mode参数是必需的');
        
        $this->service->recordTransaction($user->id, $data);
    }

    /**
     * 测试缺少mode参数时抛出异常
     */
    public function test_record_transaction_missing_mode(): void
    {
        $user = $this->createTestUser('free');
        
        $data = [
            'tokens' => 1000,
            // 缺少 mode
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tokens和mode参数是必需的');
        
        $this->service->recordTransaction($user->id, $data);
    }

    /**
     * 测试可选参数默认值
     * 
     * @requirements 3.1
     */
    public function test_record_transaction_optional_params_defaults(): void
    {
        $user = $this->createTestUser('warmheart');
        
        $data = [
            'tokens' => 1000,
            'mode' => 'dag',
            // 不提供可选参数
        ];
        
        $transaction = $this->service->recordTransaction($user->id, $data);
        
        $this->assertNull($transaction->template_name);
        $this->assertNull($transaction->conversation_id);
        $this->assertEquals(0, $transaction->input_tokens);
        $this->assertEquals(0, $transaction->output_tokens);
        $this->assertNull($transaction->description);
    }

    /**
     * 测试交易记录的原子性（事务回滚）
     * 
     * @requirements 3.1, 5.1
     */
    public function test_record_transaction_atomicity(): void
    {
        $user = $this->createTestUser('free');
        
        // 获取初始状态
        $userCredit = UserCredit::getOrCreate($user->id, 'free');
        $initialConsumed = $userCredit->daily_consumed;
        
        $data = [
            'tokens' => 50000, // 需要50积分，但免费用户只有10积分
            'mode' => 'dag',
        ];
        
        try {
            $this->service->recordTransaction($user->id, $data);
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            // 验证积分没有被扣除（事务回滚）
            $userCredit->refresh();
            $this->assertEquals($initialConsumed, $userCredit->daily_consumed);
            
            // 验证没有创建交易记录
            $transactionCount = \App\Models\CreditTransaction::where('user_id', $user->id)->count();
            $this->assertEquals(0, $transactionCount);
        }
    }

    /**
     * 测试多次交易累计消耗
     * 
     * @requirements 3.1, 5.1
     */
    public function test_record_transaction_cumulative_consumption(): void
    {
        $user = $this->createTestUser('energy'); // 200积分配额
        
        // 第一次交易
        $this->service->recordTransaction($user->id, [
            'tokens' => 2000,
            'mode' => 'dag',
        ]);
        
        // 第二次交易
        $this->service->recordTransaction($user->id, [
            'tokens' => 3000,
            'mode' => 'agent',
        ]);
        
        // 验证累计消耗
        $userCredit = UserCredit::getByUserId($user->id);
        // 第一次：ceil(2000/1000) = 2
        // 第二次：ceil(3000*1.5/1000) = 5
        // 总计：7
        $this->assertEquals(7, $userCredit->daily_consumed);
        $this->assertEquals(7, $userCredit->total_consumed);
        $this->assertEquals(193, $userCredit->remaining);
    }

    /**
     * 测试最小积分消耗（1积分）
     * 
     * @requirements 1.5, 3.1
     */
    public function test_record_transaction_minimum_credit(): void
    {
        $user = $this->createTestUser('warmheart');
        
        $data = [
            'tokens' => 100, // 很少的tokens
            'mode' => 'dag',
        ];
        
        $transaction = $this->service->recordTransaction($user->id, $data);
        
        // 最小消耗为1积分
        $this->assertEquals(1, $transaction->credits);
    }

    // ==================== resetDailyQuota() 方法测试 ====================

    /**
     * 测试重置免费用户每日配额
     * 
     * @requirements 2.1, 2.4
     */
    public function test_reset_daily_quota_free_user(): void
    {
        $user = $this->createTestUser('free');
        
        // 创建积分记录并消耗一些积分
        $userCredit = UserCredit::getOrCreate($user->id, 'free');
        $userCredit->daily_consumed = 8;
        $userCredit->last_reset_date = now()->subDay(); // 设置为昨天
        $userCredit->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证重置结果
        $userCredit->refresh();
        $this->assertEquals(10, $userCredit->daily_quota); // 免费用户10积分
        $this->assertEquals(0, $userCredit->daily_consumed); // 消耗重置为0
        $this->assertEquals(today()->toDateString(), $userCredit->last_reset_date->toDateString());
    }

    /**
     * 测试重置暖心会员每日配额
     * 
     * @requirements 2.2, 2.4
     */
    public function test_reset_daily_quota_warmheart_member(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 创建积分记录并消耗一些积分
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 45;
        $userCredit->last_reset_date = now()->subDay();
        $userCredit->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证重置结果
        $userCredit->refresh();
        $this->assertEquals(50, $userCredit->daily_quota); // 暖心会员50积分
        $this->assertEquals(0, $userCredit->daily_consumed);
        $this->assertEquals(today()->toDateString(), $userCredit->last_reset_date->toDateString());
    }

    /**
     * 测试重置能量会员每日配额
     * 
     * @requirements 2.3, 2.4
     */
    public function test_reset_daily_quota_energy_member(): void
    {
        $user = $this->createTestUser('energy');
        
        // 创建积分记录并消耗一些积分
        $userCredit = UserCredit::getOrCreate($user->id, 'energy');
        $userCredit->daily_consumed = 180;
        $userCredit->last_reset_date = now()->subDay();
        $userCredit->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证重置结果
        $userCredit->refresh();
        $this->assertEquals(200, $userCredit->daily_quota); // 能量会员200积分
        $this->assertEquals(0, $userCredit->daily_consumed);
        $this->assertEquals(today()->toDateString(), $userCredit->last_reset_date->toDateString());
    }

    /**
     * 测试今天已重置过不会重复重置
     * 
     * @requirements 2.4
     */
    public function test_reset_daily_quota_no_duplicate_reset(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 创建积分记录，设置为今天已重置
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 20;
        $userCredit->last_reset_date = today(); // 今天已重置
        $userCredit->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证消耗没有被重置（因为今天已经重置过）
        $userCredit->refresh();
        $this->assertEquals(20, $userCredit->daily_consumed); // 保持不变
    }

    /**
     * 测试会员升级后配额更新
     * 
     * @requirements 2.1, 2.2, 2.3
     */
    public function test_reset_daily_quota_membership_upgrade(): void
    {
        $user = $this->createTestUser('free');
        
        // 创建积分记录
        $userCredit = UserCredit::getOrCreate($user->id, 'free');
        $userCredit->daily_quota = 10; // 免费用户配额
        $userCredit->daily_consumed = 5;
        $userCredit->last_reset_date = today(); // 今天已重置
        $userCredit->save();
        
        // 模拟用户升级为暖心会员
        $user->membership_tier = 'warmheart';
        $user->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证配额已更新（即使今天已重置，配额也应该更新）
        $userCredit->refresh();
        $this->assertEquals(50, $userCredit->daily_quota); // 升级为暖心会员配额
        $this->assertEquals(5, $userCredit->daily_consumed); // 消耗保持不变
    }

    /**
     * 测试新用户首次重置会创建记录
     * 
     * @requirements 2.1, 2.4
     */
    public function test_reset_daily_quota_creates_record_for_new_user(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 确保没有积分记录
        UserCredit::where('user_id', $user->id)->delete();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证创建了新记录
        $userCredit = UserCredit::getByUserId($user->id);
        $this->assertNotNull($userCredit);
        $this->assertEquals(50, $userCredit->daily_quota);
        $this->assertEquals(0, $userCredit->daily_consumed);
        $this->assertEquals(today()->toDateString(), $userCredit->last_reset_date->toDateString());
    }

    /**
     * 测试重置保留total_consumed历史
     * 
     * @requirements 2.4
     */
    public function test_reset_daily_quota_preserves_total_consumed(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 创建积分记录，设置历史消耗
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 30;
        $userCredit->total_consumed = 1500;
        $userCredit->last_reset_date = now()->subDay();
        $userCredit->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证total_consumed保持不变
        $userCredit->refresh();
        $this->assertEquals(0, $userCredit->daily_consumed); // 每日消耗重置
        $this->assertEquals(1500, $userCredit->total_consumed); // 历史总消耗保持
    }

    /**
     * 测试跨多天重置
     * 
     * @requirements 2.4
     */
    public function test_reset_daily_quota_after_multiple_days(): void
    {
        $user = $this->createTestUser('energy');
        
        // 创建积分记录，设置为3天前
        $userCredit = UserCredit::getOrCreate($user->id, 'energy');
        $userCredit->daily_consumed = 150;
        $userCredit->last_reset_date = now()->subDays(3);
        $userCredit->save();
        
        // 执行重置
        $this->service->resetDailyQuota($user->id);
        
        // 验证重置结果
        $userCredit->refresh();
        $this->assertEquals(200, $userCredit->daily_quota);
        $this->assertEquals(0, $userCredit->daily_consumed);
        $this->assertEquals(today()->toDateString(), $userCredit->last_reset_date->toDateString());
    }

    // ==================== checkSufficientCredits() 方法测试 ====================

    /**
     * 测试积分充足时返回正确结果
     * 
     * @requirements 5.2
     */
    public function test_check_sufficient_credits_when_sufficient(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 确保用户有足够的积分
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 10; // 消耗10，剩余40
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        $this->assertTrue($result['sufficient']);
        $this->assertEquals('积分充足', $result['message']);
        $this->assertEquals(40, $result['remaining']);
        $this->assertArrayNotHasKey('required', $result);
        $this->assertArrayNotHasKey('membership_tier', $result);
    }

    /**
     * 测试积分不足时返回正确结果和升级提示（免费用户）
     * 
     * @requirements 5.2, 5.5
     */
    public function test_check_sufficient_credits_insufficient_free_user(): void
    {
        $user = $this->createTestUser('free');
        
        // 消耗大部分积分
        $userCredit = UserCredit::getOrCreate($user->id, 'free');
        $userCredit->daily_consumed = 8; // 消耗8，剩余2
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        $this->assertFalse($result['sufficient']);
        $this->assertStringContainsString('今日积分已用完', $result['message']);
        $this->assertStringContainsString('剩余2积分', $result['message']);
        $this->assertStringContainsString('需要5积分', $result['message']);
        $this->assertStringContainsString('升级为暖心会员可获得每日50积分', $result['message']);
        $this->assertEquals(2, $result['remaining']);
        $this->assertEquals(5, $result['required']);
        $this->assertEquals('free', $result['membership_tier']);
    }

    /**
     * 测试积分不足时返回正确结果和升级提示（暖心会员）
     * 
     * @requirements 5.2, 5.5
     */
    public function test_check_sufficient_credits_insufficient_warmheart_member(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 消耗大部分积分
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 48; // 消耗48，剩余2
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        $this->assertFalse($result['sufficient']);
        $this->assertStringContainsString('升级为能量会员可获得每日200积分', $result['message']);
        $this->assertEquals('warmheart', $result['membership_tier']);
    }

    /**
     * 测试积分不足时返回正确结果（能量会员）
     * 
     * @requirements 5.2, 5.5
     */
    public function test_check_sufficient_credits_insufficient_energy_member(): void
    {
        $user = $this->createTestUser('energy');
        
        // 消耗大部分积分
        $userCredit = UserCredit::getOrCreate($user->id, 'energy');
        $userCredit->daily_consumed = 198; // 消耗198，剩余2
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        $this->assertFalse($result['sufficient']);
        $this->assertStringContainsString('明天将重置每日配额', $result['message']);
        $this->assertEquals('energy', $result['membership_tier']);
    }

    /**
     * 测试积分刚好足够的边界情况
     * 
     * @requirements 5.2
     */
    public function test_check_sufficient_credits_exact_amount(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 设置剩余积分刚好等于需要的积分
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 45; // 消耗45，剩余5
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        $this->assertTrue($result['sufficient']);
        $this->assertEquals('积分充足', $result['message']);
        $this->assertEquals(5, $result['remaining']);
    }

    /**
     * 测试积分为0时的情况
     * 
     * @requirements 5.2, 5.5
     */
    public function test_check_sufficient_credits_zero_remaining(): void
    {
        $user = $this->createTestUser('free');
        
        // 消耗所有积分
        $userCredit = UserCredit::getOrCreate($user->id, 'free');
        $userCredit->daily_consumed = 10; // 消耗10，剩余0
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 1);
        
        $this->assertFalse($result['sufficient']);
        $this->assertStringContainsString('剩余0积分', $result['message']);
        $this->assertEquals(0, $result['remaining']);
    }

    /**
     * 测试需要重置配额时自动重置
     * 
     * @requirements 5.2, 2.4
     */
    public function test_check_sufficient_credits_auto_reset(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 设置为昨天的记录（需要重置）
        $userCredit = UserCredit::getOrCreate($user->id, 'warmheart');
        $userCredit->daily_consumed = 50; // 昨天消耗完了
        $userCredit->last_reset_date = now()->subDay();
        $userCredit->save();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        // 应该自动重置，所以积分充足
        $this->assertTrue($result['sufficient']);
        $this->assertEquals(50, $result['remaining']); // 重置后剩余50
    }

    /**
     * 测试新用户首次检查会创建记录
     * 
     * @requirements 5.2
     */
    public function test_check_sufficient_credits_new_user(): void
    {
        $user = $this->createTestUser('warmheart');
        
        // 确保没有积分记录
        UserCredit::where('user_id', $user->id)->delete();
        
        $result = $this->service->checkSufficientCredits($user->id, 5);
        
        // 新用户应该有完整配额
        $this->assertTrue($result['sufficient']);
        $this->assertEquals(50, $result['remaining']); // 暖心会员50积分
        
        // 验证创建了记录
        $userCredit = UserCredit::getByUserId($user->id);
        $this->assertNotNull($userCredit);
    }

    /**
     * 测试getUpgradeMessage方法返回正确的升级提示
     */
    public function test_get_upgrade_message(): void
    {
        // 使用反射访问protected方法
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getUpgradeMessage');
        $method->setAccessible(true);
        
        $this->assertEquals('升级为暖心会员可获得每日50积分！', $method->invoke($this->service, 'free'));
        $this->assertEquals('升级为能量会员可获得每日200积分！', $method->invoke($this->service, 'warmheart'));
        $this->assertEquals('明天将重置每日配额。', $method->invoke($this->service, 'energy'));
        $this->assertEquals('升级会员可获得更多每日积分！', $method->invoke($this->service, 'unknown'));
    }
}
